<?php

namespace RoxAppointmentBooking\Modules\Email\Services;

defined('ABSPATH') || exit;

/**
 * Class EmailLayout
 *
 * @package RoxAppointmentBooking\Modules\Email\Services
 * @description The shared HTML shell wrapped around every rendered e-mail body.
 *              Deliberately a passthrough: the existing confirmation e-mail ships
 *              no shell and must keep rendering byte-identically through the
 *              migration. The real shell lands in Step 4 of the plan.
 */
class EmailLayout
{
    /**
     * Wrap a rendered body in the shared layout.
     *
     * @param string $body Rendered e-mail body.
     * @param array $placeholders Resolved placeholder map.
     * @return string
     */
    public static function wrap(string $body, array $placeholders = []): string
    {
        /**
         * Filter the final e-mail body after the layout has been applied.
         *
         * @param string $body         Wrapped body.
         * @param array  $placeholders Resolved placeholder map.
         */
        return apply_filters('rox_appointment_booking_email_layout', $body, $placeholders);
    }
}
