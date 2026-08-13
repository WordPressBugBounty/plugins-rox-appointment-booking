<?php

namespace RoxAppointmentBooking\Modules\Email\Services;

defined('ABSPATH') || exit;

/**
 * Class EmailTemplateRegistry
 *
 * @package RoxAppointmentBooking\Modules\Email\Services
 * @description Catalogue of every e-mail the plugin can send — default subject,
 *              body and placeholders per event x recipient, addressed by the flat
 *              key "<event>__<recipient>". Admin overrides are merged over these
 *              by EmailService.
 */
class EmailTemplateRegistry
{
    /**
     * Recipient types, in display order.
     */
    public const RECIPIENT_CUSTOMER = 'customer';
    public const RECIPIENT_AGENT    = 'agent';
    public const RECIPIENT_ADMIN    = 'admin';

    /**
     * Build the flat template key used by the settings option and the UI.
     *
     * @param string $eventKey Event key (e.g. 'booking_confirmed').
     * @param string $recipientType Recipient type (e.g. 'agent').
     * @return string
     */
    public static function makeKey(string $eventKey, string $recipientType): string
    {
        return $eventKey . '__' . $recipientType;
    }

    /**
     * Split a flat template key back into [event, recipient].
     *
     * @param string $flatKey Flat template key.
     * @return array{0: string, 1: string} Empty strings when the key is malformed.
     */
    public static function splitKey(string $flatKey): array
    {
        $parts = explode('__', $flatKey);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return ['', ''];
        }

        return [$parts[0], $parts[1]];
    }

    /**
     * Every registered event, keyed by event key.
     *
     * Third parties (and a future Pro plugin) may register their own events by
     * filtering this catalogue.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function events(): array
    {
        /**
         * Filter the e-mail template catalogue.
         *
         * @param array $events Event key => event definition.
         */
        return apply_filters('rox_appointment_booking_email_templates', self::defaultEvents());
    }

    /**
     * A single event definition.
     *
     * @param string $eventKey Event key.
     * @return array<string, mixed>|null
     */
    public static function event(string $eventKey): ?array
    {
        return self::events()[$eventKey] ?? null;
    }

    /**
     * The default (shipped) template for one event x recipient pair.
     *
     * @param string $eventKey Event key.
     * @param string $recipientType Recipient type.
     * @return array{subject: string, body: string, enabled: bool, locked: bool}|null
     */
    public static function template(string $eventKey, string $recipientType): ?array
    {
        $event = self::event($eventKey);

        if (!$event || !isset($event['recipients'][$recipientType])) {
            return null;
        }

        $template = $event['recipients'][$recipientType];

        return [
            'subject' => $template['subject'] ?? '',
            'body'    => $template['body'] ?? '',
            // Every e-mail ships enabled (see plan decision D5).
            'enabled' => true,
            // A locked template cannot be switched off — disabling it would
            // break a flow the plugin depends on (password reset).
            'locked'  => !empty($template['locked']),
        ];
    }

    /**
     * The recipient types registered for an event, in display order.
     *
     * @param string $eventKey Event key.
     * @return string[]
     */
    public static function recipientsFor(string $eventKey): array
    {
        $event = self::event($eventKey);

        return $event ? array_keys($event['recipients']) : [];
    }

    /**
     * Every valid flat template key.
     *
     * @return string[]
     */
    public static function keys(): array
    {
        $keys = [];

        foreach (self::events() as $eventKey => $event) {
            foreach (array_keys($event['recipients']) as $recipientType) {
                $keys[] = self::makeKey($eventKey, $recipientType);
            }
        }

        return $keys;
    }

    /**
     * Whether a template's enable/disable switch is locked on.
     *
     * @param string $eventKey Event key.
     * @param string $recipientType Recipient type.
     * @return bool
     */
    public static function isLocked(string $eventKey, string $recipientType): bool
    {
        $template = self::template($eventKey, $recipientType);

        return $template ? $template['locked'] : false;
    }

    /**
     * Whether an event is a Pro-only feature.
     *
     * @param string $eventKey Event key.
     * @return bool
     */
    public static function isPro(string $eventKey): bool
    {
        $event = self::event($eventKey);

        return $event ? !empty($event['pro']) : false;
    }

    /**
     * Whether an event is a Pro-only feature this site is not licensed for.
     *
     * A locked event stays in the catalogue so the settings UI can show the
     * upsell, but it can never be switched on, saved or sent.
     *
     * @param string $eventKey Event key.
     * @return bool
     */
    public static function isProLocked(string $eventKey): bool
    {
        return self::isPro($eventKey) && !rox_appointment_booking_is_pro_user();
    }

    /**
     * The placeholder tokens valid for an event, for the settings UI's
     * placeholder picker.
     *
     * @param string $eventKey Event key.
     * @return string[]
     */
    public static function placeholdersFor(string $eventKey): array
    {
        $event = self::event($eventKey);

        return $event['placeholders'] ?? [];
    }

    /**
     * The shipped catalogue.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function defaultEvents(): array
    {
        return [
            'booking_confirmed'      => self::eventBookingConfirmed(),
            'booking_rescheduled'    => self::eventBookingRescheduled(),
            'booking_cancelled'      => self::eventBookingCancelled(),
            'booking_status_changed' => self::eventBookingStatusChanged(),
            'booking_reminder'       => self::eventBookingReminder(),
            'payment_received'       => self::eventPaymentReceived(),
            'payment_failed'         => self::eventPaymentFailed(),
            'payment_refunded'       => self::eventPaymentRefunded(),
            'account_credentials'    => self::eventAccountCredentials(),
            'password_reset'         => self::eventPasswordReset(),
            'password_changed'       => self::eventPasswordChanged(),
        ];
    }

    /* ---------------------------------------------------------------------
     * Placeholder groups — composed into each event's `placeholders` list.
     * ------------------------------------------------------------------ */

    /**
     * Site + company tokens, valid for every event.
     *
     * @return string[]
     */
    private static function siteTokens(): array
    {
        return [
            '{site_name}',
            '{site_url}',
            '{company_name}',
            '{company_phone}',
            '{company_email}',
            '{company_address}',
        ];
    }

    /**
     * Customer tokens.
     *
     * @return string[]
     */
    private static function customerTokens(): array
    {
        return [
            '{customer_name}',
            '{customer_first_name}',
            '{customer_last_name}',
            '{customer_email}',
            '{customer_phone}',
        ];
    }

    /**
     * Agent tokens.
     *
     * @return string[]
     */
    private static function agentTokens(): array
    {
        return [
            '{agent_name}',
            '{agent_email}',
            '{agent_phone}',
        ];
    }

    /**
     * Appointment tokens.
     *
     * @return string[]
     */
    private static function appointmentTokens(): array
    {
        return [
            '{service_name}',
            '{category_name}',
            '{location_name}',
            '{appointment_date}',
            '{appointment_start_time}',
            '{appointment_end_time}',
            '{appointment_status}',
            '{appointment_id}',
            '{appointment_count}',
            '{meet_link}',
            '{internal_notes}',
            '{appointments_table}',
        ];
    }

    /**
     * Order + payment tokens.
     *
     * @return string[]
     */
    private static function orderTokens(): array
    {
        return [
            '{order_number}',
            '{order_status}',
            '{order_total}',
            '{amount_paid}',
            '{amount_due}',
            '{deposit_amount}',
            '{payment_method}',
            '{payment_status}',
            '{transaction_id}',
            '{refund_amount}',
            '{refund_reason}',
            '{payment_details_table}',
            '{custom_fields_table}',
        ];
    }

    /**
     * Panel/deep-link tokens.
     *
     * @return string[]
     */
    private static function linkTokens(): array
    {
        return [
            '{my_bookings_url}',
            '{admin_appointment_url}',
        ];
    }

    /* ---------------------------------------------------------------------
     * Shared HTML fragments used by the default bodies.
     * ------------------------------------------------------------------ */

    /**
     * The "Customer Information" block, identical to the one the current
     * booking-confirmation e-mail renders.
     *
     * @return string
     */
    private static function customerInfoBlock(): string
    {
        return '<h3>' . esc_html__('Customer Information:', 'rox-appointment-booking') . '</h3>' .
            '<table style="border-collapse: collapse; width: 100%;">' .
            '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>' . esc_html__('Name:', 'rox-appointment-booking') . '</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{customer_name}</td></tr>' .
            '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>' . esc_html__('Email:', 'rox-appointment-booking') . '</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{customer_email}</td></tr>' .
            '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>' . esc_html__('Phone:', 'rox-appointment-booking') . '</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{customer_phone}</td></tr>' .
            '</table>';
    }

    /**
     * Compact appointment summary for the shorter templates (reschedule /
     * cancel / reminder), where the full 8-column table would be too heavy.
     *
     * @return string
     */
    private static function appointmentSummaryBlock(): string
    {
        return '<table style="border-collapse: collapse; width: 100%;">' .
            '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>' . esc_html__('Service:', 'rox-appointment-booking') . '</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{service_name}</td></tr>' .
            '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>' . esc_html__('Agent:', 'rox-appointment-booking') . '</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{agent_name}</td></tr>' .
            '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>' . esc_html__('Date:', 'rox-appointment-booking') . '</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{appointment_date}</td></tr>' .
            '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>' . esc_html__('Time:', 'rox-appointment-booking') . '</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{appointment_start_time} - {appointment_end_time}</td></tr>' .
            '</table>';
    }

    /* ---------------------------------------------------------------------
     * Event definitions.
     * ------------------------------------------------------------------ */

    /**
     * Booking created — from the frontend panel or the admin panel.
     *
     * The customer body is the CURRENT booking-confirmation e-mail expressed in
     * placeholders. It must keep rendering byte-identically (plan decision D6),
     * so do not reformat it.
     *
     * @return array<string, mixed>
     */
    private static function eventBookingConfirmed(): array
    {
        return [
            'label'        => __('Booking Confirmed', 'rox-appointment-booking'),
            'description'  => __('Sent when a new booking is created, from the booking panel or the admin panel.', 'rox-appointment-booking'),
            'placeholders' => array_merge(
                self::siteTokens(),
                self::customerTokens(),
                self::agentTokens(),
                self::appointmentTokens(),
                self::orderTokens(),
                self::linkTokens()
            ),
            'recipients'   => [
                self::RECIPIENT_CUSTOMER => [
                    // translators: {order_number} is a placeholder token, do not translate it.
                    'subject' => __('Booking Confirmation - Order {order_number}', 'rox-appointment-booking'),
                    'body'    => '<h2>' . esc_html__('Booking Confirmation', 'rox-appointment-booking') . '</h2>' .
                        '<p>' . esc_html__('Dear', 'rox-appointment-booking') . ' <strong>{customer_name}</strong>,</p>' .
                        '<p>' . esc_html__('Your booking has been confirmed successfully.', 'rox-appointment-booking') . '</p>' .
                        '<h3>' . esc_html__('Order Details:', 'rox-appointment-booking') . '</h3>' .
                        '<table style="border-collapse: collapse; width: 100%;">' .
                        '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>' . esc_html__('Order Number:', 'rox-appointment-booking') . '</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{order_number}</td></tr>' .
                        '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>' . esc_html__('Order Status:', 'rox-appointment-booking') . '</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{order_status}</td></tr>' .
                        '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>' . esc_html__('Total Appointments:', 'rox-appointment-booking') . '</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{appointment_count}</td></tr>' .
                        '</table>' .
                        '<h3>' . esc_html__('Appointments:', 'rox-appointment-booking') . '</h3>' .
                        '{appointments_table}' .
                        '<h3>' . esc_html__('Payment Details:', 'rox-appointment-booking') . '</h3>' .
                        '{payment_details_table}' .
                        self::customerInfoBlock() .
                        '{custom_fields_table}' .
                        '<p>' . esc_html__('Thank you for your booking!', 'rox-appointment-booking') . '</p>',
                ],
                self::RECIPIENT_AGENT    => [
                    // translators: {customer_name} is a placeholder token, do not translate it.
                    'subject' => __('New booking from {customer_name}', 'rox-appointment-booking'),
                    'body'    => '<h2>' . esc_html__('New Booking', 'rox-appointment-booking') . '</h2>' .
                        '<p>' . esc_html__('Hello', 'rox-appointment-booking') . ' <strong>{agent_name}</strong>,</p>' .
                        '<p>' . esc_html__('A new appointment has been booked with you.', 'rox-appointment-booking') . '</p>' .
                        '<h3>' . esc_html__('Appointments:', 'rox-appointment-booking') . '</h3>' .
                        '{appointments_table}' .
                        self::customerInfoBlock() .
                        '{custom_fields_table}',
                ],
                self::RECIPIENT_ADMIN    => [
                    // translators: {order_number} is a placeholder token, do not translate it.
                    'subject' => __('New booking received - Order {order_number}', 'rox-appointment-booking'),
                    'body'    => '<h2>' . esc_html__('New Booking Received', 'rox-appointment-booking') . '</h2>' .
                        '<p>' . esc_html__('A new booking has been placed.', 'rox-appointment-booking') . '</p>' .
                        '<h3>' . esc_html__('Order Details:', 'rox-appointment-booking') . '</h3>' .
                        '<table style="border-collapse: collapse; width: 100%;">' .
                        '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>' . esc_html__('Order Number:', 'rox-appointment-booking') . '</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{order_number}</td></tr>' .
                        '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>' . esc_html__('Order Status:', 'rox-appointment-booking') . '</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{order_status}</td></tr>' .
                        '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>' . esc_html__('Total:', 'rox-appointment-booking') . '</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{order_total}</td></tr>' .
                        '</table>' .
                        '<h3>' . esc_html__('Appointments:', 'rox-appointment-booking') . '</h3>' .
                        '{appointments_table}' .
                        self::customerInfoBlock() .
                        '{custom_fields_table}' .
                        '<p><a href="{admin_appointment_url}">' . esc_html__('View in the dashboard', 'rox-appointment-booking') . '</a></p>',
                ],
            ],
        ];
    }

    /**
     * Booking date/time changed.
     *
     * @return array<string, mixed>
     */
    private static function eventBookingRescheduled(): array
    {
        $change = '<table style="border-collapse: collapse; width: 100%;">' .
            '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>' . esc_html__('Previously:', 'rox-appointment-booking') . '</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{old_date} {old_time}</td></tr>' .
            '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>' . esc_html__('New schedule:', 'rox-appointment-booking') . '</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{new_date} {new_time}</td></tr>' .
            '</table>';

        return [
            'label'        => __('Booking Rescheduled', 'rox-appointment-booking'),
            'description'  => __('Sent when an appointment\'s date or time is changed, from the admin form, the calendar, or a customer/agent panel.', 'rox-appointment-booking'),
            'placeholders' => array_merge(
                self::siteTokens(),
                self::customerTokens(),
                self::agentTokens(),
                self::appointmentTokens(),
                ['{old_date}', '{old_time}', '{new_date}', '{new_time}'],
                self::linkTokens()
            ),
            'recipients'   => [
                self::RECIPIENT_CUSTOMER => [
                    'subject' => __('Your appointment has been rescheduled', 'rox-appointment-booking'),
                    'body'    => '<h2>' . esc_html__('Appointment Rescheduled', 'rox-appointment-booking') . '</h2>' .
                        '<p>' . esc_html__('Dear', 'rox-appointment-booking') . ' <strong>{customer_name}</strong>,</p>' .
                        '<p>' . esc_html__('Your appointment has been moved to a new date and time.', 'rox-appointment-booking') . '</p>' .
                        $change .
                        '<h3>' . esc_html__('Appointment Details:', 'rox-appointment-booking') . '</h3>' .
                        self::appointmentSummaryBlock() .
                        '<p><a href="{my_bookings_url}">' . esc_html__('View my bookings', 'rox-appointment-booking') . '</a></p>',
                ],
                self::RECIPIENT_AGENT    => [
                    'subject' => __('An appointment has been rescheduled', 'rox-appointment-booking'),
                    'body'    => '<h2>' . esc_html__('Appointment Rescheduled', 'rox-appointment-booking') . '</h2>' .
                        '<p>' . esc_html__('Hello', 'rox-appointment-booking') . ' <strong>{agent_name}</strong>,</p>' .
                        '<p>' . esc_html__('An appointment in your schedule has been moved.', 'rox-appointment-booking') . '</p>' .
                        $change .
                        '<h3>' . esc_html__('Appointment Details:', 'rox-appointment-booking') . '</h3>' .
                        self::appointmentSummaryBlock() .
                        self::customerInfoBlock(),
                ],
                self::RECIPIENT_ADMIN    => [
                    'subject' => __('Appointment rescheduled - {customer_name}', 'rox-appointment-booking'),
                    'body'    => '<h2>' . esc_html__('Appointment Rescheduled', 'rox-appointment-booking') . '</h2>' .
                        '<p>' . esc_html__('An appointment has been moved to a new date and time.', 'rox-appointment-booking') . '</p>' .
                        $change .
                        '<h3>' . esc_html__('Appointment Details:', 'rox-appointment-booking') . '</h3>' .
                        self::appointmentSummaryBlock() .
                        self::customerInfoBlock() .
                        '<p><a href="{admin_appointment_url}">' . esc_html__('View in the dashboard', 'rox-appointment-booking') . '</a></p>',
                ],
            ],
        ];
    }

    /**
     * Booking cancelled.
     *
     * @return array<string, mixed>
     */
    private static function eventBookingCancelled(): array
    {
        return [
            'label'        => __('Booking Cancelled', 'rox-appointment-booking'),
            'description'  => __('Sent when an appointment is cancelled, from the admin panel or by the customer.', 'rox-appointment-booking'),
            'placeholders' => array_merge(
                self::siteTokens(),
                self::customerTokens(),
                self::agentTokens(),
                self::appointmentTokens(),
                self::orderTokens(),
                self::linkTokens()
            ),
            'recipients'   => [
                self::RECIPIENT_CUSTOMER => [
                    'subject' => __('Your appointment has been cancelled', 'rox-appointment-booking'),
                    'body'    => '<h2>' . esc_html__('Appointment Cancelled', 'rox-appointment-booking') . '</h2>' .
                        '<p>' . esc_html__('Dear', 'rox-appointment-booking') . ' <strong>{customer_name}</strong>,</p>' .
                        '<p>' . esc_html__('The following appointment has been cancelled.', 'rox-appointment-booking') . '</p>' .
                        self::appointmentSummaryBlock() .
                        '<p>' . esc_html__('If this was not expected, please contact us.', 'rox-appointment-booking') . '</p>',
                ],
                self::RECIPIENT_AGENT    => [
                    'subject' => __('An appointment has been cancelled', 'rox-appointment-booking'),
                    'body'    => '<h2>' . esc_html__('Appointment Cancelled', 'rox-appointment-booking') . '</h2>' .
                        '<p>' . esc_html__('Hello', 'rox-appointment-booking') . ' <strong>{agent_name}</strong>,</p>' .
                        '<p>' . esc_html__('An appointment has been removed from your schedule.', 'rox-appointment-booking') . '</p>' .
                        self::appointmentSummaryBlock() .
                        self::customerInfoBlock(),
                ],
                self::RECIPIENT_ADMIN    => [
                    'subject' => __('Appointment cancelled - {customer_name}', 'rox-appointment-booking'),
                    'body'    => '<h2>' . esc_html__('Appointment Cancelled', 'rox-appointment-booking') . '</h2>' .
                        '<p>' . esc_html__('An appointment has been cancelled.', 'rox-appointment-booking') . '</p>' .
                        self::appointmentSummaryBlock() .
                        self::customerInfoBlock() .
                        '<p><a href="{admin_appointment_url}">' . esc_html__('View in the dashboard', 'rox-appointment-booking') . '</a></p>',
                ],
            ],
        ];
    }

    /**
     * Any appointment status change other than cancellation.
     *
     * @return array<string, mixed>
     */
    private static function eventBookingStatusChanged(): array
    {
        return [
            'label'        => __('Booking Status Changed', 'rox-appointment-booking'),
            'description'  => __('Sent when an appointment\'s status changes to anything other than cancelled (for example Completed or No Show).', 'rox-appointment-booking'),
            'placeholders' => array_merge(
                self::siteTokens(),
                self::customerTokens(),
                self::agentTokens(),
                self::appointmentTokens(),
                self::linkTokens()
            ),
            'recipients'   => [
                self::RECIPIENT_CUSTOMER => [
                    'subject' => __('Your appointment is now {appointment_status}', 'rox-appointment-booking'),
                    'body'    => '<h2>' . esc_html__('Appointment Update', 'rox-appointment-booking') . '</h2>' .
                        '<p>' . esc_html__('Dear', 'rox-appointment-booking') . ' <strong>{customer_name}</strong>,</p>' .
                        '<p>' . esc_html__('The status of your appointment is now:', 'rox-appointment-booking') . ' <strong>{appointment_status}</strong></p>' .
                        self::appointmentSummaryBlock() .
                        '<p><a href="{my_bookings_url}">' . esc_html__('View my bookings', 'rox-appointment-booking') . '</a></p>',
                ],
                self::RECIPIENT_AGENT    => [
                    'subject' => __('Appointment status changed to {appointment_status}', 'rox-appointment-booking'),
                    'body'    => '<h2>' . esc_html__('Appointment Update', 'rox-appointment-booking') . '</h2>' .
                        '<p>' . esc_html__('Hello', 'rox-appointment-booking') . ' <strong>{agent_name}</strong>,</p>' .
                        '<p>' . esc_html__('The status of an appointment in your schedule is now:', 'rox-appointment-booking') . ' <strong>{appointment_status}</strong></p>' .
                        self::appointmentSummaryBlock() .
                        self::customerInfoBlock(),
                ],
                self::RECIPIENT_ADMIN    => [
                    'subject' => __('Appointment status changed - {customer_name}', 'rox-appointment-booking'),
                    'body'    => '<h2>' . esc_html__('Appointment Update', 'rox-appointment-booking') . '</h2>' .
                        '<p>' . esc_html__('An appointment status is now:', 'rox-appointment-booking') . ' <strong>{appointment_status}</strong></p>' .
                        self::appointmentSummaryBlock() .
                        self::customerInfoBlock() .
                        '<p><a href="{admin_appointment_url}">' . esc_html__('View in the dashboard', 'rox-appointment-booking') . '</a></p>',
                ],
            ],
        ];
    }

    /**
     * Reminder ahead of the appointment (WP-Cron driven).
     *
     * @return array<string, mixed>
     */
    private static function eventBookingReminder(): array
    {
        return [
            'label'        => __('Booking Reminder', 'rox-appointment-booking'),
            'description'  => __('Sent a configurable number of hours before the appointment starts. Requires WP-Cron to run.', 'rox-appointment-booking'),
            // Pro-only: without a licence the reminder cron never runs and the
            // Reminders settings section renders as an upsell.
            'pro'          => true,
            'placeholders' => array_merge(
                self::siteTokens(),
                self::customerTokens(),
                self::agentTokens(),
                self::appointmentTokens(),
                self::linkTokens()
            ),
            'recipients'   => [
                self::RECIPIENT_CUSTOMER => [
                    'subject' => __('Reminder: your appointment is coming up', 'rox-appointment-booking'),
                    'body'    => '<h2>' . esc_html__('Appointment Reminder', 'rox-appointment-booking') . '</h2>' .
                        '<p>' . esc_html__('Dear', 'rox-appointment-booking') . ' <strong>{customer_name}</strong>,</p>' .
                        '<p>' . esc_html__('This is a reminder about your upcoming appointment.', 'rox-appointment-booking') . '</p>' .
                        self::appointmentSummaryBlock() .
                        '<p>{meet_link}</p>' .
                        '<p><a href="{my_bookings_url}">' . esc_html__('View my bookings', 'rox-appointment-booking') . '</a></p>',
                ],
                self::RECIPIENT_AGENT    => [
                    'subject' => __('Reminder: upcoming appointment', 'rox-appointment-booking'),
                    'body'    => '<h2>' . esc_html__('Appointment Reminder', 'rox-appointment-booking') . '</h2>' .
                        '<p>' . esc_html__('Hello', 'rox-appointment-booking') . ' <strong>{agent_name}</strong>,</p>' .
                        '<p>' . esc_html__('You have an upcoming appointment.', 'rox-appointment-booking') . '</p>' .
                        self::appointmentSummaryBlock() .
                        self::customerInfoBlock(),
                ],
            ],
        ];
    }

    /**
     * Payment marked paid.
     *
     * @return array<string, mixed>
     */
    private static function eventPaymentReceived(): array
    {
        return [
            'label'        => __('Payment Received', 'rox-appointment-booking'),
            'description'  => __('Sent when a payment is marked as paid.', 'rox-appointment-booking'),
            'placeholders' => array_merge(
                self::siteTokens(),
                self::customerTokens(),
                self::orderTokens(),
                self::linkTokens()
            ),
            'recipients'   => [
                self::RECIPIENT_CUSTOMER => [
                    'subject' => __('Payment received - Order {order_number}', 'rox-appointment-booking'),
                    'body'    => '<h2>' . esc_html__('Payment Received', 'rox-appointment-booking') . '</h2>' .
                        '<p>' . esc_html__('Dear', 'rox-appointment-booking') . ' <strong>{customer_name}</strong>,</p>' .
                        '<p>' . esc_html__('Thank you — we have received your payment.', 'rox-appointment-booking') . '</p>' .
                        '<h3>' . esc_html__('Payment Details:', 'rox-appointment-booking') . '</h3>' .
                        '{payment_details_table}',
                ],
                self::RECIPIENT_ADMIN    => [
                    'subject' => __('Payment received - Order {order_number}', 'rox-appointment-booking'),
                    'body'    => '<h2>' . esc_html__('Payment Received', 'rox-appointment-booking') . '</h2>' .
                        '<p>' . esc_html__('A payment has been received.', 'rox-appointment-booking') . '</p>' .
                        '<h3>' . esc_html__('Payment Details:', 'rox-appointment-booking') . '</h3>' .
                        '{payment_details_table}' .
                        self::customerInfoBlock(),
                ],
            ],
        ];
    }

    /**
     * Payment marked failed.
     *
     * @return array<string, mixed>
     */
    private static function eventPaymentFailed(): array
    {
        return [
            'label'        => __('Payment Failed', 'rox-appointment-booking'),
            'description'  => __('Sent when a payment is marked as failed.', 'rox-appointment-booking'),
            'placeholders' => array_merge(
                self::siteTokens(),
                self::customerTokens(),
                self::orderTokens(),
                self::linkTokens()
            ),
            'recipients'   => [
                self::RECIPIENT_CUSTOMER => [
                    'subject' => __('Payment failed - Order {order_number}', 'rox-appointment-booking'),
                    'body'    => '<h2>' . esc_html__('Payment Failed', 'rox-appointment-booking') . '</h2>' .
                        '<p>' . esc_html__('Dear', 'rox-appointment-booking') . ' <strong>{customer_name}</strong>,</p>' .
                        '<p>' . esc_html__('Unfortunately your payment could not be processed.', 'rox-appointment-booking') . '</p>' .
                        '<h3>' . esc_html__('Payment Details:', 'rox-appointment-booking') . '</h3>' .
                        '{payment_details_table}' .
                        '<p>' . esc_html__('Please try again or contact us for help.', 'rox-appointment-booking') . '</p>',
                ],
                self::RECIPIENT_ADMIN    => [
                    'subject' => __('Payment failed - Order {order_number}', 'rox-appointment-booking'),
                    'body'    => '<h2>' . esc_html__('Payment Failed', 'rox-appointment-booking') . '</h2>' .
                        '<p>' . esc_html__('A payment attempt has failed.', 'rox-appointment-booking') . '</p>' .
                        '<h3>' . esc_html__('Payment Details:', 'rox-appointment-booking') . '</h3>' .
                        '{payment_details_table}' .
                        self::customerInfoBlock(),
                ],
            ],
        ];
    }

    /**
     * Refund processed.
     *
     * @return array<string, mixed>
     */
    private static function eventPaymentRefunded(): array
    {
        $refundBlock = '<table style="border-collapse: collapse; width: 100%;">' .
            '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>' . esc_html__('Order Number:', 'rox-appointment-booking') . '</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{order_number}</td></tr>' .
            '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>' . esc_html__('Refunded Amount:', 'rox-appointment-booking') . '</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{refund_amount}</td></tr>' .
            '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>' . esc_html__('Reason:', 'rox-appointment-booking') . '</strong></td><td style="padding: 8px; border: 1px solid #ddd;">{refund_reason}</td></tr>' .
            '</table>';

        return [
            'label'        => __('Payment Refunded', 'rox-appointment-booking'),
            'description'  => __('Sent when a refund is processed on an order.', 'rox-appointment-booking'),
            'placeholders' => array_merge(
                self::siteTokens(),
                self::customerTokens(),
                self::orderTokens(),
                self::linkTokens()
            ),
            'recipients'   => [
                self::RECIPIENT_CUSTOMER => [
                    'subject' => __('Refund processed - Order {order_number}', 'rox-appointment-booking'),
                    'body'    => '<h2>' . esc_html__('Refund Processed', 'rox-appointment-booking') . '</h2>' .
                        '<p>' . esc_html__('Dear', 'rox-appointment-booking') . ' <strong>{customer_name}</strong>,</p>' .
                        '<p>' . esc_html__('A refund has been issued for your order.', 'rox-appointment-booking') . '</p>' .
                        $refundBlock,
                ],
                self::RECIPIENT_ADMIN    => [
                    'subject' => __('Refund processed - Order {order_number}', 'rox-appointment-booking'),
                    'body'    => '<h2>' . esc_html__('Refund Processed', 'rox-appointment-booking') . '</h2>' .
                        '<p>' . esc_html__('A refund has been issued.', 'rox-appointment-booking') . '</p>' .
                        $refundBlock .
                        self::customerInfoBlock(),
                ],
            ],
        ];
    }

    /**
     * A WordPress account was auto-created for a customer or an agent.
     *
     * The customer body is the CURRENT credentials e-mail expressed in
     * placeholders — keep it rendering identically (plan decision D6).
     *
     * @return array<string, mixed>
     */
    private static function eventAccountCredentials(): array
    {
        return [
            'label'        => __('Account Credentials', 'rox-appointment-booking'),
            'description'  => __('Sent when a login account is created for a customer or an agent, with a link to set their password.', 'rox-appointment-booking'),
            'placeholders' => array_merge(
                self::siteTokens(),
                self::customerTokens(),
                self::agentTokens(),
                ['{username}', '{set_password_url}', '{login_url}']
            ),
            'recipients'   => [
                self::RECIPIENT_CUSTOMER => [
                    'subject' => __('Booking Engine Login Credentials', 'rox-appointment-booking'),
                    'body'    => '<p>' . esc_html__('Hello', 'rox-appointment-booking') . ' <strong>{customer_name}</strong>,</p>' .
                        '<p>' . esc_html__('Your account has been created.', 'rox-appointment-booking') . '</p>' .
                        '<p><strong>' . esc_html__('Username:', 'rox-appointment-booking') . '</strong> {username}</p>' .
                        '<p><a href="{set_password_url}">' . esc_html__('Set your password', 'rox-appointment-booking') . '</a></p>',
                ],
                self::RECIPIENT_AGENT    => [
                    'subject' => __('Booking Engine Login Credentials', 'rox-appointment-booking'),
                    'body'    => '<p>' . esc_html__('Hello', 'rox-appointment-booking') . ' <strong>{agent_name}</strong>,</p>' .
                        '<p>' . esc_html__('An account has been created for you.', 'rox-appointment-booking') . '</p>' .
                        '<p><strong>' . esc_html__('Username:', 'rox-appointment-booking') . '</strong> {username}</p>' .
                        '<p><a href="{set_password_url}">' . esc_html__('Set your password', 'rox-appointment-booking') . '</a></p>',
                ],
            ],
        ];
    }

    /**
     * Password reset link from the login form.
     *
     * Locked on: switching it off would silently break the login form's
     * forgot-password flow.
     *
     * @return array<string, mixed>
     */
    private static function eventPasswordReset(): array
    {
        return [
            'label'        => __('Password Reset', 'rox-appointment-booking'),
            'description'  => __('Sent when someone requests a password reset from the login form. This e-mail cannot be disabled.', 'rox-appointment-booking'),
            'placeholders' => array_merge(
                self::siteTokens(),
                self::customerTokens(),
                ['{username}', '{set_password_url}', '{login_url}']
            ),
            'recipients'   => [
                self::RECIPIENT_CUSTOMER => [
                    'locked'  => true,
                    'subject' => __('Password Reset Request', 'rox-appointment-booking'),
                    'body'    => '<p>' . esc_html__('Hello', 'rox-appointment-booking') . ' <strong>{customer_name}</strong>,</p>' .
                        '<p>' . esc_html__('We received a request to reset the password for your account.', 'rox-appointment-booking') . '</p>' .
                        '<p><a href="{set_password_url}">' . esc_html__('Reset your password', 'rox-appointment-booking') . '</a></p>' .
                        '<p>' . esc_html__('If you did not request a password reset, you can safely ignore this email.', 'rox-appointment-booking') . '</p>',
                ],
            ],
        ];
    }

    /**
     * An admin set a new password from the Agent / Customer edit form.
     *
     * {new_password_block} carries the password itself and is empty unless the
     * admin ticked "Send the new password to their email", so one template
     * covers both cases — see EmailPlaceholderResolver::newPasswordBlock().
     *
     * @return array<string, mixed>
     */
    private static function eventPasswordChanged(): array
    {
        return [
            'label'        => __('Password Changed', 'rox-appointment-booking'),
            'description'  => __('Sent when an administrator sets a new password from the Agent or Customer edit form. The password itself is only included when the administrator chooses to send it.', 'rox-appointment-booking'),
            'placeholders' => array_merge(
                self::siteTokens(),
                self::customerTokens(),
                self::agentTokens(),
                ['{new_password_block}', '{login_url}']
            ),
            'recipients'   => [
                self::RECIPIENT_CUSTOMER => [
                    'subject' => __('Your password has been changed', 'rox-appointment-booking'),
                    'body'    => '<h2>' . esc_html__('Password Changed', 'rox-appointment-booking') . '</h2>' .
                        '<p>' . esc_html__('Hello', 'rox-appointment-booking') . ' <strong>{customer_name}</strong>,</p>' .
                        '<p>' . esc_html__('An administrator has set a new password for your account on', 'rox-appointment-booking') . ' <strong>{site_name}</strong>.</p>' .
                        '{new_password_block}' .
                        '<p><a href="{login_url}">' . esc_html__('Sign in', 'rox-appointment-booking') . '</a></p>' .
                        '<p>' . esc_html__('If you were not expecting this, please contact the site owner.', 'rox-appointment-booking') . '</p>',
                ],
                self::RECIPIENT_AGENT    => [
                    'subject' => __('Your password has been changed', 'rox-appointment-booking'),
                    'body'    => '<h2>' . esc_html__('Password Changed', 'rox-appointment-booking') . '</h2>' .
                        '<p>' . esc_html__('Hello', 'rox-appointment-booking') . ' <strong>{agent_name}</strong>,</p>' .
                        '<p>' . esc_html__('An administrator has set a new password for your account on', 'rox-appointment-booking') . ' <strong>{site_name}</strong>.</p>' .
                        '{new_password_block}' .
                        '<p><a href="{login_url}">' . esc_html__('Sign in', 'rox-appointment-booking') . '</a></p>' .
                        '<p>' . esc_html__('If you were not expecting this, please contact the site owner.', 'rox-appointment-booking') . '</p>',
                ],
            ],
        ];
    }
}
