<?php

namespace RoxAppointmentBooking\Modules\CustomerPanel\Services;

use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Shared server-side helpers for the Customer Panel REST endpoints. Identity is
 * always resolved here from the logged-in WordPress user — endpoints must scope
 * every query to currentCustomerId() and never trust a client-supplied
 * customer_id. Mirrors AppointmentService::getCurrentCustomerId() but keeps all
 * customer-panel backend logic inside the CustomerPanel module.
 */
class CustomerPanelService
{
    public static $loadable = true;

    /**
     * The CustomerModel row for the current logged-in user, matched by
     * wp_user_id first, then email. Null if not logged in or no customer row.
     *
     * @return CustomerModel|null
     */
    public static function currentCustomer()
    {
        $user = wp_get_current_user();
        if (! $user || ! $user->exists()) {
            return null;
        }

        return CustomerModel::query()
            ->where('wp_user_id', $user->ID)
            ->orWhere('email', $user->user_email)
            ->first();
    }

    /**
     * The current logged-in customer's ID, or null.
     *
     * @return int|null
     */
    public static function currentCustomerId(): ?int
    {
        $customer = self::currentCustomer();

        return $customer ? (int) $customer->getID() : null;
    }

    /**
     * Whether the current request is an authenticated customer-panel user.
     * Used by every CustomerPanel endpoint's permissionCheck().
     *
     * @return bool
     */
    public static function isCurrentUserCustomer(): bool
    {
        return is_user_logged_in()
            && function_exists('rox_appointment_booking_is_customer')
            && rox_appointment_booking_is_customer();
    }
}
