<?php

namespace RoxAppointmentBooking\Modules\CustomerPanel\Services;

use RoxAppointmentBooking\Supports\Assets;
use RoxAppointmentBooking\Modules\UserManagement\Util\UserInfo;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Enqueues the separate Customer Panel bundle for customer users on the plugin
 * dashboard page. The admin App.php bails out for customers (guard), so only
 * this bundle mounts into #rox-appointment-booking-customer-panel-root for them.
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
        add_action('admin_init', [$this, 'redirectCustomersToPanel']);
    }

    /**
     * Customers get ONLY the Customer Panel — never the wp-admin UI. Any customer
     * who lands on any wp-admin screen other than the panel's own dashboard page
     * is redirected to it, so they never see the WordPress admin menu/toolbar.
     * AJAX is left alone so background requests keep working.
     *
     * @return void
     */
    public function redirectCustomersToPanel(): void
    {
        if (wp_doing_ajax() || !rox_appointment_booking_is_customer()) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if ($page === $this->page_slug) {
            return; // already on the panel
        }

        wp_safe_redirect(admin_url('admin.php?page=' . $this->page_slug));
        exit;
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
            'window.rox_appointment_booking = window.rox_appointment_booking || {}; '
                . 'window.rox_appointment_booking.config = window.rox_appointment_booking.config || {}; '
                . 'window.rox_appointment_booking.config.customerPanel = ' . wp_json_encode($this->panelVars()) . ';',
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

    /**
     * Minimal window config for the Customer Panel bundle. Kept small on purpose;
     * expand as the panel grows (it will read from window...config.customerPanel).
     *
     * @return array
     */
    private function panelVars(): array
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
            'apiBaseUrl'  => esc_url_raw(rest_url('rox-appointment-booking/v1/')),
            'restBaseUrl' => esc_url_raw(rest_url()),
            // Nonce-signed WordPress logout URL; redirects to the login screen
            // once the session ends. Used by the header avatar dropdown.
            // wp_logout_url() HTML-escapes the ampersand (&#038;), which is right
            // for an href but breaks when used as a raw JS redirect target
            // (the _wpnonce param gets mangled and WP shows the "really log out?"
            // confirmation). Decode the entities so window.location.href gets a
            // clean `&`; wp_json_encode handles JS-context escaping.
            'logoutUrl'   => html_entity_decode(wp_logout_url(), ENT_QUOTES),
            'currentUser' => [
                'name'  => $userInfo->getFullName(),
                'email' => $userInfo->getEmail(),
                'src'   => $thumbnail ?: $userInfo->getAvatarUrl(36),
            ],
        ];
    }
}
