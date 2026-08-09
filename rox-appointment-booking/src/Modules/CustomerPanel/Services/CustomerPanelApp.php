<?php

namespace RoxAppointmentBooking\Modules\CustomerPanel\Services;

use RoxAppointmentBooking\Supports\Assets;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Enqueues the separate Customer Panel bundle for customer users on the plugin
 * dashboard page. The admin App.php bails out for customers (guard), so only
 * this bundle mounts into #rox-appointment-booking-customer-panel-root for them.
 *
 * Also keeps customers pointed at the frontend dashboard page: out of wp-admin,
 * no admin toolbar, and back to that page on logout.
 */
class CustomerPanelApp
{
    public static $loadable = true;

    /** admin_enqueue_scripts page hook for the plugin dashboard page. */
    private string $page_hook = 'toplevel_page_rox-appointment-booking-dashboard';

    /** Capability required to reach the dashboard page (matches App.php). */
    private string $capability = 'read';

    /** Admin menu slug of the dashboard page the panel lives on. */
    private string $page_slug = 'rox-appointment-booking-dashboard';

    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets'], 100);
        // Ahead of WooCommerce, which hooks its own "get out of wp-admin"
        // redirect on `admin_init` at the default 10 and would otherwise send a
        // booking customer to its My Account page before this ever runs. A
        // priority — rather than relying on which plugin registered first —
        // makes the order deterministic.
        add_action('admin_init', [$this, 'redirectCustomersToPanel'], 5);
        add_filter('show_admin_bar', [$this, 'hideAdminBarForCustomers']);
        add_filter('logout_redirect', [$this, 'logoutRedirect'], 10, 3);
    }

    /**
     * Customers get ONLY the Customer Panel — never the wp-admin UI. Any customer
     * who lands on a wp-admin screen is sent to the frontend dashboard page, so
     * they never see the WordPress admin menu/toolbar. AJAX is left alone so
     * background requests keep working.
     *
     * Without that page (never created, or the site owner deleted it) the old
     * behaviour stands in: bounce them to the panel's own wp-admin page, which
     * still mounts the same bundle. Better a stripped-down admin screen than a
     * customer locked out of their bookings.
     *
     * @return void
     */
    public function redirectCustomersToPanel(): void
    {
        // Matched on the plugin's own customer role, NOT the broader
        // rox_appointment_booking_is_customer() helper this used to ask. That
        // helper means "not an administrator and not a booking agent", which is
        // equally true of editors, authors, contributors, subscribers and
        // WooCommerce shop managers — none of whom this plugin has any business
        // throwing out of wp-admin. Booking customers hold this role (assigned by
        // SaveCustomer and the frontend panel's CustomerService), and a logged-out
        // request has no roles at all, so admin-post.php's `admin_init` is left
        // alone too.
        //
        // The role narrows this to booking customers; rox_appointment_booking_is_customer()
        // then decides who WINS when one login carries more than one of our roles —
        // an agent who also holds the customer role belongs in wp-admin, and the
        // helper already answers that (it is false for administrators and agents).
        // Without this second test such a user bounces here to the dashboard page,
        // is sent straight back by CustomerPanelShortcode::redirectAgentsToAdmin(),
        // and loops until the browser gives up.
        if (
            wp_doing_ajax()
            || !in_array('rox_appointment_booking_customer', rox_appointment_booking_get_current_user_role(), true)
            || !rox_appointment_booking_is_customer()
        ) {
            return;
        }

        $dashboard_url = rox_appointment_booking_dashboard_url();
        if ($dashboard_url) {
            wp_safe_redirect($dashboard_url);
            exit;
        }

        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if ($page === $this->page_slug) {
            return; // already on the panel
        }

        wp_safe_redirect(admin_url('admin.php?page=' . $this->page_slug));
        exit;
    }

    /**
     * The panel is a standalone app on an ordinary page — the WordPress toolbar
     * across the top would only offer a customer links they cannot use, and the
     * wp-admin surface used to hide it with CSS anyway.
     *
     * Matched on the plugin's own customer role, NOT the broader
     * rox_appointment_booking_is_customer() helper. That helper means "not an
     * administrator and not a booking agent", which is also true of editors,
     * authors, contributors, subscribers and WooCommerce shop managers — taking
     * the toolbar away from all of them, everywhere on the site, is not this
     * plugin's call. Booking customers hold this role (SaveCustomer /
     * FrontendBookingPanel CustomerService both assign it).
     *
     * @param bool $show Whether WordPress intends to show the admin bar.
     * @return bool
     */
    public function hideAdminBarForCustomers($show)
    {
        // Paired with rox_appointment_booking_is_customer() for the same reason as
        // redirectCustomersToPanel() above: an agent who also carries the customer
        // role works in wp-admin and keeps the toolbar.
        if (
            in_array('rox_appointment_booking_customer', rox_appointment_booking_get_current_user_role(), true)
            && rox_appointment_booking_is_customer()
        ) {
            return false;
        }

        return $show;
    }

    /**
     * Sends a customer or an agent back to the dashboard page when they log out,
     * where they are met by the login form rather than the bare wp-login.php
     * screen. Both sign in through that page, so both come back to it.
     *
     * Only fills in a destination WordPress does not already have. A logout link
     * that names where to go — the panel's own (which names this page anyway),
     * WooCommerce's My Account link, a theme's — is left alone; overriding it
     * would drag a shopper who happens to also be a booking customer out of the
     * store and onto the booking dashboard. What is left is every logout with no
     * destination at all: the agent's wp-admin avatar menu, the toolbar link, a
     * bookmarked wp-login.php?action=logout. Those are the ones that used to end
     * on the bare wp-login.php screen.
     *
     * Matched on the plugin's own roles rather than the broader
     * rox_appointment_booking_is_customer() check, which also answers true for
     * plain subscribers who have nothing to do with bookings — their logout is
     * not ours to redirect.
     *
     * @param string  $redirect_to           Where WordPress means to send them.
     * @param string  $requested_redirect_to Redirect requested on the logout url.
     * @param \WP_User $user                 The user who just logged out.
     * @return string
     */
    public function logoutRedirect($redirect_to, $requested_redirect_to, $user)
    {
        if ($requested_redirect_to !== '') {
            return $redirect_to;
        }

        $panel_roles = ['rox_appointment_booking_customer', 'rox_appointment_booking_agent'];

        if (!($user instanceof \WP_User) || !array_intersect($panel_roles, (array) $user->roles)) {
            return $redirect_to;
        }

        $dashboard_url = rox_appointment_booking_dashboard_url();

        return $dashboard_url ? $dashboard_url : $redirect_to;
    }

    public function enqueueAssets($hook): void
    {
        if (
            !is_admin()
            || !current_user_can($this->capability)
            || $hook !== $this->page_hook
            || !rox_appointment_booking_is_customer()
        ) {
            return;
        }

        $asset_file = ROX_APPOINTMENT_BOOKING_PUBLIC_PATH . 'build/customer-panel/app.asset.php';
        $asset = file_exists($asset_file) ? require($asset_file) : [
            'dependencies' => ['react', 'react-dom'],
            'version' => ROX_APPOINTMENT_BOOKING_VERSION,
        ];

        // runtime -> vendors -> entry load order (shared chunks are idempotent).
        $shared = Assets::enqueueSharedChunks(
            ROX_APPOINTMENT_BOOKING_PUBLIC_URL . 'build/',
            ROX_APPOINTMENT_BOOKING_PUBLIC_PATH . 'build/',
            $asset['dependencies'],
            $asset['version'],
        );

        Assets::enqueueStyle(
            'rox-appointment-booking-customer-panel',
            ROX_APPOINTMENT_BOOKING_PUBLIC_URL . 'build/customer-panel/app.css',
            array_filter([$shared['style']]),
            $asset['version'],
        );

        // The Customer Panel is a standalone UI — strip the surrounding WordPress
        // admin chrome (menu, admin bar, footer) and full-bleed the content area
        // so it reads like its own app rather than a wp-admin screen.
        wp_add_inline_style(
            'rox-appointment-booking-customer-panel',
            $this->chromeResetCss()
        );

        Assets::enqueueScript(
            'rox-appointment-booking-customer-panel',
            ROX_APPOINTMENT_BOOKING_PUBLIC_URL . 'build/customer-panel/app.js',
            [$shared['script']],
            $asset['version'],
            true,
        );

        wp_set_script_translations(
            'rox-appointment-booking-customer-panel',
            'rox-appointment-booking',
            ROX_APPOINTMENT_BOOKING_PATH . 'languages'
        );

        wp_add_inline_script(
            'rox-appointment-booking-customer-panel',
            CustomerPanelConfig::inlineScript(),
            'before'
        );
    }

    /**
     * CSS that hides the WordPress admin chrome (menu, admin bar, footer) and
     * full-bleeds the content area, so the Customer Panel renders as a standalone
     * app. Scoped to this page only because the enqueue itself is gated to the
     * dashboard page + customer users.
     *
     * @return string
     */
    private function chromeResetCss(): string
    {
        return '
            #adminmenumain, #adminmenuback, #adminmenuwrap, #wpadminbar, #wpfooter { display: none !important; }
            html.wp-toolbar { padding-top: 0 !important; }
            #wpcontent, #wpbody-content { margin-left: 0 !important; padding-left: 0 !important; }
            #wpbody-content { padding-bottom: 0 !important; float: none !important; }
            #wpbody-content > .wrap { margin: 0 !important; }
            html, body.wp-admin { background: #f4f5f7 !important; }
        ';
    }
}
