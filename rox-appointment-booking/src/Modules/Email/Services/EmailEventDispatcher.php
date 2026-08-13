<?php

namespace RoxAppointmentBooking\Modules\Email\Services;

defined('ABSPATH') || exit;

/**
 * Class EmailEventDispatcher
 *
 * @package RoxAppointmentBooking\Modules\Email\Services
 * @description Listens for `rox_appointment_booking_email_event` and fans one
 *              event out to every recipient registered for it, so callers never
 *              need to know who a given e-mail goes to:
 *              do_action('rox_appointment_booking_email_event', 'booking_confirmed', $context).
 *              Nothing fires it yet — trigger points arrive in Steps 2 and 5-10.
 */
class EmailEventDispatcher
{
    /**
     * Whether this class should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * Register the event listener.
     */
    public function __construct()
    {
        add_action('rox_appointment_booking_email_event', [$this, 'dispatch'], 10, 3);
    }

    /**
     * Send an event's e-mail to each of its registered recipients.
     *
     * @param string $eventKey Event key.
     * @param array $context Event context.
     * @param array $only Optional whitelist of recipient types; empty means all.
     * @return void
     */
    public function dispatch(string $eventKey, array $context = [], array $only = []): void
    {
        foreach (EmailTemplateRegistry::recipientsFor($eventKey) as $recipientType) {
            if (!empty($only) && !in_array($recipientType, $only, true)) {
                continue;
            }

            EmailService::send($eventKey, $recipientType, $context);
        }
    }
}
