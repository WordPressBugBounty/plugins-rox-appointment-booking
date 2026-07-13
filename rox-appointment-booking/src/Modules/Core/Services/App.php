<?php

/**
 * Class AppPage
 * 
 * @package RoxAppointmentBooking
 * @subpackage Modules\ElementorWidgetBuilder\Services
 * @since 1.0.0
 *
 * Handles the admin menu pages and assets for the Rox Appointment Booking plugin.
 * Creates and manages the main plugin menu, dashboard, and widget builder pages.
 */

namespace RoxAppointmentBooking\Modules\Core\Services;

use RoxAppointmentBooking\Supports\Assets;
use RoxAppointmentBooking\Modules\UserManagement\Util\UserInfo;
use RoxAppointmentBooking\Modules\Service\Services\ServiceService;

if (! defined('ABSPATH')) exit; // Exit if accessed directly


class App
{
    /**
     * Whether the service should be loadable.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * Admin menu page title.
     *
     * @var string
     */
    private string $page_title = 'Dashboard';
    /**
     * Admin menu label.
     *
     * @var string
     */
    private string $menu_title = 'Rox Appointment';
    /**
     * Capability required to access the menu.
     *
     * @var string
     */
    private string $capability = 'read';
    /**
     * Admin menu slug.
     *
     * @var string
     */
    private string $menu_slug = 'rox-appointment-booking-dashboard';
    /**
     * Dashboard page title.
     *
     * @var string
     */
    private string $dashboard_page_title = 'Dashboard';
    /**
     * Dashboard menu label.
     *
     * @var string
     */
    private string $dashboard_menu_title = 'Dashboard';
    /**
     * Constructor.
     * 
     * Initializes the class by registering WordPress admin menu and asset hooks.
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets'], 100);
        add_action('admin_init', [$this, 'maybeRedirectToOnboard']);
        add_action('in_admin_header', [$this, 'removeOtherPluginNotices'], 1);
    }

    /**
     * Adds JavaScript variables to the admin head.
     *
     * @return array
     */
    public function reactAppVars(): array
    {
        $user = wp_get_current_user();
        $user_roles = $user ? $user->roles : [];
        $userInfo = new UserInfo();

        $vars = [
            'version' => ROX_APPOINTMENT_BOOKING_VERSION,
            'appTitle' => 'Rox Appointment Booking',
            'defaultLocale' => 'en_US',
            'timezone' => get_option('timezone_string') ?: 'UTC',
            'dateFormat' => get_option('date_format') ?: 'Y-m-d',
            'timeFormat' => get_option('time_format') ?: 'H:i:s',
            'appRootDomId' => 'rox-appointment-booking-app-root',
            'publicUrl' => esc_url_raw(ROX_APPOINTMENT_BOOKING_PUBLIC_URL),
            'nonce' => wp_create_nonce('rox_appointment_booking_app_nonce'),
            'apiBaseUrl' => esc_url_raw(rest_url('rox-appointment-booking/v1/')),
            'restBaseUrl' => esc_url_raw(rest_url()),
            'siteUrl' => esc_url_raw(site_url()),
            'adminUrl' => esc_url_raw(admin_url()),
            'userRoles' => $user_roles,
            'isProUser' => rox_appointment_booking_is_pro_user(),
            'isCustomer' => !in_array('administrator', $user_roles) && !in_array('rox_appointment_booking_agent', $user_roles),
            // Whether the Locations module should appear in the UI. Previously
            // computed inside AppConfig::getLocationMenuItem(); now surfaced as a
            // single boolean for the static JS sidebar config (config/sidebar.js).
            'locationModuleEnabled' => $this->isLocationModuleEnabled(),
            // Current-user display data for the topbar avatar, previously built
            // server-side in AppConfig::getAppConfigStructure().
            'currentUser' => [
                'src' => $userInfo->getAvatarUrl(36),
                'name' => $userInfo->getFullName(),
                'role' => $userInfo->getRoleDisplayName(),
            ],
            // Status/method enums (filterable via the PHP status hooks). Surfaced
            // for the static JS table/form configs so they don't have to refetch
            // option lists that the server already computes at boot.
            'enums' => [
                'orderStatuses'       => rox_appointment_booking_order_statuses(),
                'paymentStatuses'     => rox_appointment_booking_payment_statuses(),
                'appointmentStatuses' => rox_appointment_booking_appointment_statuses(),
                'paymentMethods'      => rox_appointment_booking_payment_methods(),
            ],
            // Settings-derived field defaults used by the static JS form configs
            // (e.g. the customer form's phone field), previously read inside the
            // form structure endpoints.
            'defaults' => $this->formFieldDefaults(),
            // Duration dropdown options for the service form (filterable via
            // rox_appointment_booking_duration_options). Generated server-side and
            // surfaced so the static JS config doesn't duplicate the ~280-entry
            // list.
            'durationOptions' => ServiceService::getDurationOptions(),
            'currencySymbol' => rox_appointment_booking__get_currency_symbol(rox_appointment_booking_payment_settings('payment_currency')),
        ];

        /**
         * Filter the admin React app config injected as
         * `window.rox_appointment_booking.config.app`. The Pro plugin uses this to
         * declare feature flags (e.g. `proFeatures.googleLogin`) so the admin UI
         * only surfaces sections a given Pro version can actually back.
         */
        return apply_filters('rox_appointment_booking_app_config_structure', $vars);
    }

    /**
     * Field default values sourced from plugin settings, surfaced for the static
     * JS form configs. Mirrors the values the form structure endpoints used to
     * read from `rox_appointment_booking_general_settings`.
     *
     * @return array
     */
    private function formFieldDefaults(): array
    {
        $general_settings = rox_appointment_booking_general_settings();

        return [
            'phoneCountryCode'  => $general_settings['default_phone_country_code'] ?? '+1',
            'phoneCountryIso'   => $general_settings['default_phone_country_iso'] ?? 'US',
            'appointmentStatus' => $general_settings['default_appointment_status'] ?? 'pending',
        ];
    }

    /**
     * Determine whether the Locations module should be exposed in the UI.
     *
     * Mirrors the logic previously embedded in
     * AppConfig::getLocationMenuItem(): the module is always available on the
     * free plan, and on Pro it depends on the location settings toggle.
     *
     * @return bool
     */
    private function isLocationModuleEnabled(): bool
    {
        $location_settings = get_option('rox_appointment_booking_location_settings', []);

        return !rox_appointment_booking_is_pro_user()
            || (isset($location_settings['location_module_enable'])
                && filter_var($location_settings['location_module_enable'], FILTER_VALIDATE_BOOLEAN));
    }

    /**
     * Enqueues the required CSS and JavaScript files.
     *
     * @param string $hook The current admin page hook.
     * @return void
     */
    public function enqueueAssets($hook): void
    {


        /**
         * Register a virtual (no-src) stylesheet handle so we can attach
         * small global admin CSS via wp_add_inline_style() without loading
         * an extra .css file. This keeps trivial admin-wide tweaks inline
         * instead of creating a separate enqueued asset.
         */
        wp_register_style( 'rox-appointment-booking-admin-global', false );
        wp_enqueue_style( 'rox-appointment-booking-admin-global' );

        wp_add_inline_style(
            'rox-appointment-booking-admin-global',
            '
            #adminmenu .toplevel_page_rox-appointment-booking-dashboard div.wp-menu-image.svg {
               background-size: 16px auto;
            }
            '
        );

        // Only load on app page
        if (
            !is_admin() ||
            !current_user_can($this->capability) ||
            $hook !== 'toplevel_page_rox-appointment-booking-dashboard'
        ) {
            return;
        }

        if (!$this->isOnboarded()) {
            $onboarding_asset_file = ROX_APPOINTMENT_BOOKING_PUBLIC_PATH . 'build/onboarding/app.asset.php';
            $onboarding_asset = file_exists($onboarding_asset_file) ? require($onboarding_asset_file) : [
                'dependencies' => ['react', 'react-dom'],
                'version' => ROX_APPOINTMENT_BOOKING_VERSION,
            ];

            // Register the shared runtime/vendors chunks first so the entry can
            // depend on them (runtime -> vendors -> entry load order).
            $onboarding_shared = Assets::enqueueSharedChunks(
                ROX_APPOINTMENT_BOOKING_PUBLIC_URL . 'build/',
                ROX_APPOINTMENT_BOOKING_PUBLIC_PATH . 'build/',
                $onboarding_asset['dependencies'],
                $onboarding_asset['version'],
            );

            Assets::enqueueStyle(
                'rox-appointment-booking-onboarding',
                ROX_APPOINTMENT_BOOKING_PUBLIC_URL . 'build/onboarding/app.css',
                array_filter([$onboarding_shared['style']]),
                $onboarding_asset['version'],
            );

            Assets::enqueueScript(
                'rox-appointment-booking-onboarding',
                ROX_APPOINTMENT_BOOKING_PUBLIC_URL . 'build/onboarding/app.js',
                [$onboarding_shared['script']],
                $onboarding_asset['version'],
                true,
            );

            wp_add_inline_script(
                'rox-appointment-booking-onboarding',
                'window.RoxAppointmentBooking = window.RoxAppointmentBooking || {}; ' .
                    'window.RoxAppointmentBooking.isProActive = ' . (rox_appointment_booking_is_pro_user() ? 'true' : 'false') .';' .
                    'window.RoxAppointmentBooking.siteUrl = "' . esc_url_raw(site_url()) . '";' .
                    'window.RoxAppointmentBooking.restUrl = "' . esc_url_raw(rest_url()) . '";' .
                    'window.RoxAppointmentBooking.restNonce = "' . esc_attr(wp_create_nonce('wp_rest')) . '";',
                'before'
            );

            return;
        }

        wp_enqueue_media();

        // Load the build's content-hash version (and dependencies) from the
        // auto-generated asset file so a rebuilt bundle busts the browser cache,
        // mirroring the frontend enqueue. Falls back to the static version.
        $asset_file = ROX_APPOINTMENT_BOOKING_PUBLIC_PATH . 'build/admin/app.asset.php';
        $asset = file_exists($asset_file) ? require($asset_file) : [
            'dependencies' => ['react', 'react-dom', 'wp-data', 'wp-hooks'],
            'version' => ROX_APPOINTMENT_BOOKING_VERSION,
        ];

        // Register the shared runtime/vendors chunks first so the entry can
        // depend on them (runtime -> vendors -> entry load order).
        $shared = Assets::enqueueSharedChunks(
            ROX_APPOINTMENT_BOOKING_PUBLIC_URL . 'build/',
            ROX_APPOINTMENT_BOOKING_PUBLIC_PATH . 'build/',
            $asset['dependencies'],
            $asset['version'],
        );

        Assets::enqueueStyle(
            'rox-appointment-booking-admin',
            ROX_APPOINTMENT_BOOKING_PUBLIC_URL . 'build/admin/app.css',
            array_filter([$shared['style']]),
            $asset['version'],
        );

        Assets::enqueueScript(
            'rox-appointment-booking-admin',
            ROX_APPOINTMENT_BOOKING_PUBLIC_URL . 'build/admin/app.js',
            [$shared['script']],
            $asset['version'],
            true,
        );

        wp_add_inline_script(
            'rox-appointment-booking-admin',
            'window.rox_appointment_booking = window.rox_appointment_booking || {}; ' .
                'window.rox_appointment_booking.config = window.rox_appointment_booking.config || {}; ' .
                'window.rox_appointment_booking.config.app = ' . json_encode($this->reactAppVars()) . ';',
            'before'
        );
    }

    /**
     * Registers the plugin's admin menu and submenu pages.
     *
     * Creates the main menu item and adds Dashboard and Widget Builder as submenus.
     *
     * @return void
     */
    public function registerMenu(): void
    {
        add_menu_page(
            $this->page_title,
            $this->menu_title,
            $this->capability,
            $this->menu_slug,
            [$this, 'renderDashboard'],
            'data:image/svg+xml;base64,' . base64_encode(file_get_contents( ROX_APPOINTMENT_BOOKING_PUBLIC_PATH . 'svgs/menu-icon.svg')),
            30
        );

        // Add Dashboard as first submenu
        add_submenu_page(
            $this->menu_slug,
            $this->dashboard_page_title,
            $this->dashboard_menu_title,
            $this->capability,
            $this->menu_slug
        );
    }

    /**
     * Renders the Dashboard page content.
     *
     * @return void
     */
    public function renderDashboard(): void
    {
        if (!$this->isOnboarded()) {
            $view_file = dirname(__DIR__) . '/views/onboard.php';
        } else {
            $view_file = dirname(__DIR__) . '/views/dashboard.php';
        }

        if (file_exists($view_file)) {
            include $view_file;
        }
    }

    /**
     * Removes admin notices from other plugins on ROX Appointment Booking pages.
     *
     * @return void
     */
    public function removeOtherPluginNotices(): void
    {
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if ($page === '' || !str_starts_with($page, ROX_APPOINTMENT_BOOKING_TEXT_DOMAIN)) {
            return;
        }
        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
    }

    /**
     * Redirects users to the onboard page if they haven't completed onboarding.
     * 
     * Checks if the user is in the admin area and if they have completed onboarding. If not, it redirects them to the onboard page.
     * Also handles the redirect after plugin activation for users with the appropriate capabilities.
     * 
     * @return void
     */
    public function maybeRedirectToOnboard(): void
    {
        if (!is_admin() || $this->isOnboarded()) {
            return;
        }

        if (current_user_can('manage_options') && get_transient('rox_appointment_booking_activation_redirect')) {
            delete_transient('rox_appointment_booking_activation_redirect');
            wp_safe_redirect(admin_url('admin.php?page=' . $this->menu_slug));
            exit;
        }

        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if ($page === '' || !str_starts_with($page, ROX_APPOINTMENT_BOOKING_TEXT_DOMAIN)) {
            return;
        }

        if ($page === $this->menu_slug) {
            return;
        }

        wp_safe_redirect(admin_url('admin.php?page=' . $this->menu_slug));
        exit;
    }

    /**
     * Checks if the onboarding process has been completed.
     *
     * @return bool True if onboarded, false otherwise.
     */
    private function isOnboarded(): bool
    {
        return (bool) get_option('rox_appointment_booking_onboarded', 0);
    }
}
