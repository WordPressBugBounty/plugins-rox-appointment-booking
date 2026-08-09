<?php

namespace RoxAppointmentBooking\Modules\UserManagement\Services;

defined('ABSPATH') || exit;

/**
 * Class WooCommerceCompat
 *
 * @package RoxAppointmentBooking\Modules\UserManagement\Services
 * @description Keeps this plugin's admin pages reachable for booking agents and
 * customers while WooCommerce is active. WooCommerce redirects every user without
 * `edit_posts` / `manage_woocommerce` / `view_admin_dashboard` away from wp-admin
 * to its My Account page, and both booking roles hold only `read` — so the
 * dashboard (registered with the `read` capability precisely so those roles can
 * open it) became unreachable for them.
 *
 * Nothing here depends on WooCommerce being installed: without it the filter is
 * simply never applied.
 */
class WooCommerceCompat
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
        add_filter('woocommerce_prevent_admin_access', [$this, 'allowBookingRoles']);
    }

    /**
     * Lets a booking agent or customer through to this plugin's admin pages only.
     *
     * Both gates have to pass — the request must target one of our pages *and*
     * the user must hold one of the two booking capabilities. A plain WooCommerce
     * customer or a subscriber holds neither, so WooCommerce keeps redirecting
     * them exactly as before; WooCommerce's own admin pages never match the slug.
     *
     * @param bool $prevent Whether WooCommerce would block this request.
     * @return bool
     */
    public function allowBookingRoles($prevent): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing check.
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        if ($page === '' || !str_starts_with($page, ROX_APPOINTMENT_BOOKING_TEXT_DOMAIN)) {
            return (bool) $prevent;
        }

        if (
            current_user_can('rox_appointment_booking_agent_capability')
            || current_user_can('rox_appointment_booking_customer_capability')
        ) {
            return false;
        }

        return (bool) $prevent;
    }
}
