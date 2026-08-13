<?php

namespace RoxAppointmentBooking\Modules\Email\Services;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;

/**
 * Class ReminderCronService
 *
 * @package RoxAppointmentBooking\Modules\Email\Services
 * @description The hourly scan that sends `booking_reminder` ahead of an
 *              appointment. Customer and agent are fully independent — each has
 *              its own lead time, its own enable switch and its own sent-stamp
 *              column — so the scan runs once per recipient type.
 */
class ReminderCronService
{
    /**
     * WP-Cron hook this service listens on.
     */
    public const HOOK = 'rox_appointment_booking_send_reminders';

    /**
     * Most bookings handled in a single scan, per recipient. A cron request has
     * to finish inside the web request that triggered it, so a backlog is worked
     * off over several runs rather than risking a timeout.
     */
    private const BATCH_SIZE = 100;

    /**
     * Statuses that never get a reminder.
     *
     * @var string[]
     */
    private const SKIP_STATUSES = ['cancelled', 'no_show'];

    /**
     * Which sent-stamp column belongs to which recipient type.
     *
     * @var array<string, string>
     */
    private const SENT_COLUMN = [
        EmailTemplateRegistry::RECIPIENT_CUSTOMER => 'reminder_sent_customer_at',
        EmailTemplateRegistry::RECIPIENT_AGENT    => 'reminder_sent_agent_at',
    ];

    /**
     * Which setting holds which recipient's lead time.
     *
     * @var array<string, string>
     */
    private const HOURS_SETTING = [
        EmailTemplateRegistry::RECIPIENT_CUSTOMER => 'reminder_hours_before_customer',
        EmailTemplateRegistry::RECIPIENT_AGENT    => 'reminder_hours_before_agent',
    ];

    /**
     * Run the scan for every recipient type. Entry point for the cron hook.
     *
     * @return void
     */
    public static function run(): void
    {
        // Reminders are a paid feature. EmailService::isEnabled() would already
        // block every send, but bailing here saves the queries too.
        if (EmailTemplateRegistry::isProLocked('booking_reminder')) {
            return;
        }

        foreach (array_keys(self::SENT_COLUMN) as $recipientType) {
            self::runFor($recipientType);
        }
    }

    /**
     * Send this recipient type's reminders for everything inside its window.
     *
     * @param string $recipientType Recipient type.
     * @return void
     */
    private static function runFor(string $recipientType): void
    {
        if (!EmailService::isEnabled('booking_reminder', $recipientType)) {
            return;
        }

        foreach (self::due($recipientType) as $appointment) {
            // Stamped before the send, never after: a wp_mail() that hangs and
            // takes the request down with it must not leave the booking eligible
            // again on the next run, or one bad send becomes a mail storm.
            $appointment->update([
                self::SENT_COLUMN[$recipientType] => current_time('mysql'),
            ]);

            do_action(
                'rox_appointment_booking_email_event',
                'booking_reminder',
                [
                    'appointment_ids' => [(int) $appointment->id],
                    'customer_id'     => (int) $appointment->customer_id,
                ],
                [$recipientType]
            );
        }
    }

    /**
     * The bookings this recipient type still owes a reminder for.
     *
     * @param string $recipientType Recipient type.
     * @return AppointmentModel[]
     */
    private static function due(string $recipientType): array
    {
        $now    = current_time('mysql');
        $cutoff = gmdate('Y-m-d H:i:s', strtotime($now) + self::leadHours($recipientType) * HOUR_IN_SECONDS);

        $query = AppointmentModel::query()
            ->whereNotIn('status', self::SKIP_STATUSES)
            ->whereNull(self::SENT_COLUMN[$recipientType])
            // The per-booking opt-out already on the admin booking form.
            ->where('reminder_notification', 1)
            ->whereBetween('start_time', [$now, $cutoff])
            ->orderBy('start_time', 'asc')
            ->limit(self::BATCH_SIZE);

        // An agent-less booking (service-capacity mode) has nobody to remind.
        if ($recipientType === EmailTemplateRegistry::RECIPIENT_AGENT) {
            $query->whereNotNull('agent_id');
        }

        return $query->get()->all();
    }

    /**
     * How many hours ahead this recipient type is reminded.
     *
     * @param string $recipientType Recipient type.
     * @return int
     */
    private static function leadHours(string $recipientType): int
    {
        $settings = get_option('rox_appointment_booking_email_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }

        $legacy = (int) ($settings['reminder_hours_before'] ?? 24);
        $hours  = (int) ($settings[self::HOURS_SETTING[$recipientType]] ?? $legacy);

        // Same clamp the settings endpoint applies, in case the option was
        // written by hand or carried over from an older build.
        return max(1, min(168, $hours));
    }
}
