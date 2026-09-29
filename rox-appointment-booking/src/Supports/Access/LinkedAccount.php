<?php

namespace RoxAppointmentBooking\Supports\Access;

if (!defined('ABSPATH')) exit;

/**
 * Guards changes to the WordPress account linked to a customer or agent row.
 *
 * @package RoxAppointmentBooking
 * @subpackage Supports\Access
 * @since 1.3.1
 */
class LinkedAccount
{
    /**
     * Roles a plain customer account may hold.
     */
    public const CUSTOMER_ROLES = ['rox_appointment_booking_customer', 'subscriber'];

    /**
     * Roles a plain agent account may hold.
     */
    public const AGENT_ROLES = ['rox_appointment_booking_agent', 'rox_appointment_booking_customer', 'subscriber'];

    /**
     * Whether the current user may link, rename or re-email a WordPress account.
     *
     * Non-admins may only touch accounts holding nothing but the given plain roles,
     * so a manager can never take over an administrator's account by e-mail.
     *
     * @param int      $userId
     * @param string[] $plainRoles
     * @return bool
     */
    public static function canChange(int $userId, array $plainRoles): bool
    {
        if (current_user_can('edit_user', $userId)) {
            return true;
        }

        $user = get_userdata($userId);
        if (!$user) {
            return false;
        }

        // A super admin may hold no role on this site at all.
        if (is_super_admin($userId) || user_can($user, 'manage_options') || user_can($user, 'edit_users')) {
            return false;
        }

        return !array_diff((array) $user->roles, $plainRoles);
    }
}
