<?php

namespace RoxAppointmentBooking\Modules\UserManagement\Services;

defined('ABSPATH') || exit;

/**
 * Class LoginManagement
 * 
 * @package RoxAppointmentBooking\Modules\UserManagement\Services
 * @description Handles login redirection for users with rox-appointment-booking roles.
 */
class LoginManagement
{

    /**
     * Whether this class should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        add_filter('login_redirect', [$this, 'redirectAfterLogin'], 10, 3);
    }

    /**
     * Send a booking agent or customer to their dashboard after login.
     *
     * Only those two roles are taken over. This used to claim every login that
     * was not an administrator, which is equally true of editors, authors,
     * contributors, subscribers and WooCommerce shop managers — none of whom
     * this plugin has any business pulling onto the booking dashboard.
     *
     * The role test lives in rox_appointment_booking_panel_redirect_url() so
     * this filter and the standalone login form agree on who is redirected
     * where.
     *
     * @param string $redirect_to The redirect destination URL.
     * @param string $requested_redirect_to The requested redirect destination URL.
     * @param \WP_User|\WP_Error $user WP_User object if login was successful, WP_Error object otherwise.
     * @return string The redirect URL.
     */
    public function redirectAfterLogin(string $redirect_to, string $requested_redirect_to, $user): string
    {
        if (is_wp_error($user) || !($user instanceof \WP_User)) {
            return $redirect_to;
        }

        $panel_url = rox_appointment_booking_panel_redirect_url($user);

        return $panel_url !== '' ? $panel_url : $redirect_to;
    }


}
