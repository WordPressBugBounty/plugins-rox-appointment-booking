<?php

/**
 * Class CustomerPanelConfig
 *
 * @package RoxAppointmentBooking
 * @subpackage Modules\CustomerPanel\Services
 * @since 1.0.0
 *
 * Shared config builder for the Customer Panel bundle — the payload the React
 * app reads from `window.rox_appointment_booking.config.customerPanel` at mount
 * time. Both surfaces that mount the panel call `build()`: the wp-admin page
 * (`CustomerPanelApp`) and the frontend dashboard shortcode
 * (`CustomerPanelShortcode`), so the config has a single home.
 */

namespace RoxAppointmentBooking\Modules\CustomerPanel\Services;

use RoxAppointmentBooking\Modules\UserManagement\Util\UserInfo;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

class CustomerPanelConfig
{
    /**
     * Not auto-instantiated by the module loader; used statically.
     *
     * @var bool
     */
    public static $loadable = false;

    /**
     * Minimal window config for the Customer Panel bundle. Kept small on purpose;
     * expand as the panel grows (it will read from window...config.customerPanel).
     *
     * @return array
     */
    public static function build(): array
    {
        $userInfo = new UserInfo();
        // The photo set on the customer record in the admin wins over the WP
        // user avatar, so the header matches the Profile view.
        $customer = CustomerPanelService::currentCustomer();
        $thumbnail = $customer && $customer->thumbnail_id
            ? wp_get_attachment_url((int) $customer->thumbnail_id)
            : '';

        return [
            'nonce'       => wp_create_nonce('wp_rest'),
            // The language the customer is reading the panel in, passed back as
            // `lang` on every request so service names in their booking history
            // come back translated. Empty with no multilingual plugin active, so
            // a single-language site sends no extra param.
            'language'    => rox_appointment_booking_multilingual()->isActive()
                ? rox_appointment_booking_current_language()
                : '',
            'apiBaseUrl'  => esc_url_raw(rest_url('rox-appointment-booking/v1/')),
            'restBaseUrl' => esc_url_raw(rest_url()),
            // Nonce-signed WordPress logout URL, pointed back at the frontend
            // dashboard page so ending the session lands on the login form rather
            // than the bare wp-login.php screen. An empty redirect (no such page)
            // leaves wp_logout_url() on its own default.
            // wp_logout_url() HTML-escapes the ampersand (&#038;), which is right
            // for an href but breaks when used as a raw JS redirect target
            // (the _wpnonce param gets mangled and WP shows the "really log out?"
            // confirmation). Decode the entities so window.location.href gets a
            // clean `&`; wp_json_encode handles JS-context escaping.
            'logoutUrl'   => html_entity_decode(wp_logout_url(rox_appointment_booking_dashboard_url()), ENT_QUOTES),
            // Settings > Booking > "Allow Customer To Re-Schedule Their
            // Appointment". Off hides the panel's Reschedule affordances; the
            // reschedule endpoint enforces the same rule server-side.
            'canReschedule' => rox_appointment_booking_customer_can_reschedule(),
            // Settings > Booking > "Allow Customer To Cancel Their Appointment",
            // enforced the same way as the reschedule switch above.
            'canCancel' => rox_appointment_booking_customer_can_cancel(),
            'currentUser' => [
                'name'  => $userInfo->getFullName(),
                'email' => $userInfo->getEmail(),
                'src'   => $thumbnail ?: $userInfo->getAvatarUrl(36),
            ],
        ];
    }

    /**
     * The `<script>` line that hands `build()` to the bundle. Both mount surfaces
     * attach it to their own script handle with `wp_add_inline_script(..., 'before')`.
     *
     * @return string
     */
    public static function inlineScript(): string
    {
        return 'window.rox_appointment_booking = window.rox_appointment_booking || {}; '
            . 'window.rox_appointment_booking.config = window.rox_appointment_booking.config || {}; '
            . 'window.rox_appointment_booking.config.customerPanel = ' . wp_json_encode(self::build()) . ';';
    }
}
