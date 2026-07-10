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
     * Redirect users to the booking engine dashboard after login.
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

        if (in_array('administrator', $user->roles)) {
            return $redirect_to;
        }

        return admin_url('admin.php?page=rox-appointment-booking-dashboard#/appointment');
    }


}
