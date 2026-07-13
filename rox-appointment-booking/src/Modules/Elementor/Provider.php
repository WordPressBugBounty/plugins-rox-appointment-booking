<?php

/**
 * Class Provider
 *
 * @package RoxAppointmentBooking
 * @subpackage Modules\Elementor
 * @since 1.0.0
 *
 * Wires the "Rox Appointment Booking Panel" Elementor widget. Like the Gutenberg
 * block, the widget ships NO UI of its own: it reuses the exact frontend bundle
 * the `[rox_appointment_booking]` shortcode mounts
 * (`public/build/frontend/app.js` + `app.css`) and renders the same
 * `rox-appointment-booking-frontend-root` mount node.
 *
 * This module is a no-op when Elementor is absent: all wiring hangs off the
 * Elementor hooks (`elementor/widgets/register`, etc.), which only fire when
 * Elementor (>= 3.5) is active. Targets the modern
 * `Elementor\Widgets_Manager::register()` API.
 */

namespace RoxAppointmentBooking\Modules\Elementor;

use RoxAppointmentBooking\Modules\Elementor\Widgets\BookingPanelWidget;
use RoxAppointmentBooking\Supports\Assets;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

class Provider
{
    /**
     * Custom Elementor category slug for the plugin's widgets.
     */
    public const CATEGORY_SLUG = 'rox-appointment-booking';

    /**
     * Frontend (view) script/style handle. Shared with FrontendApp's shortcode
     * bundle and the Gutenberg block so it loads only once per page.
     */
    protected const VIEW_HANDLE = 'rox-appointment-booking-frontend';

    /**
     * Handle for the small Elementor editor/frontend handler script that mounts
     * the panel on widgets injected after the bundle has run.
     */
    public const HANDLER_HANDLE = 'rox-appointment-booking-elementor-handler';

    /**
     * Constructor.
     *
     * All hooks are Elementor-specific, so nothing runs when Elementor is not
     * active (the hooks never fire).
     */
    public function __construct()
    {
        add_action('elementor/elements/categories_registered', [$this, 'registerCategory']);
        add_action('elementor/widgets/register', [$this, 'registerWidgets']);
        add_action('elementor/frontend/after_register_scripts', [$this, 'registerAssets']);
        add_action('elementor/frontend/after_register_styles', [$this, 'registerAssets']);
    }

    /**
     * Registers the custom "Rox Appointment Booking" widget category.
     *
     * @param \Elementor\Elements_Manager $elements_manager
     * @return void
     */
    public function registerCategory($elements_manager): void
    {
        $elements_manager->add_category(
            self::CATEGORY_SLUG,
            [
                'title' => esc_html__('Rox Appointment Booking', 'rox-appointment-booking'),
                'icon'  => 'eicon-calendar',
            ]
        );
    }

    /**
     * Registers the booking panel widget with Elementor.
     *
     * @param \Elementor\Widgets_Manager $widgets_manager
     * @return void
     */
    public function registerWidgets($widgets_manager): void
    {
        $widgets_manager->register(new BookingPanelWidget());
    }

    /**
     * Registers the shortcode's frontend bundle as the widget's view assets and
     * prints the config the booking panel reads from
     * `window.rox_appointment_booking.config.app`.
     *
     * Idempotent: shares the `rox-appointment-booking-frontend` handle with the
     * shortcode and the Gutenberg block, so it registers only once per page.
     * Mirrors BookingPanelBlock::registerViewAssets().
     *
     * @return void
     */
    public function registerAssets(): void
    {
        $this->registerHandlerScript();

        // Shared with FrontendApp / the block; register only if nobody else has.
        if (!wp_script_is(self::VIEW_HANDLE, 'registered')) {
            $build_path = ROX_APPOINTMENT_BOOKING_PATH . 'public/build/';
            $build_url  = ROX_APPOINTMENT_BOOKING_PUBLIC_URL . 'build/';

            $view_asset_file = $build_path . 'frontend/app.asset.php';
            if (!file_exists($view_asset_file)) {
                return;
            }

            $view_asset = require $view_asset_file;

            $shared = Assets::enqueueSharedChunks(
                $build_url,
                $build_path,
                $view_asset['dependencies'] ?? [],
                $view_asset['version'] ?? ROX_APPOINTMENT_BOOKING_VERSION
            );

            wp_register_script(
                self::VIEW_HANDLE,
                $build_url . 'frontend/app.js',
                [$shared['script']],
                $view_asset['version'] ?? ROX_APPOINTMENT_BOOKING_VERSION,
                true
            );

            wp_register_style(
                self::VIEW_HANDLE,
                $build_url . 'frontend/app.css',
                array_filter([$shared['style']]),
                $view_asset['version'] ?? ROX_APPOINTMENT_BOOKING_VERSION
            );

            wp_add_inline_script(
                self::VIEW_HANDLE,
                'window.rox_appointment_booking = window.rox_appointment_booking || {}; ' .
                    'window.rox_appointment_booking.config = window.rox_appointment_booking.config || {}; ' .
                    'window.rox_appointment_booking.config.app = ' . wp_json_encode($this->frontendConfig()) . ';',
                'before'
            );
        }

        $this->registerEditorSessionReset();
    }

    /**
     * In the Elementor editor preview, clears the panel's persisted
     * sessionStorage BEFORE the frontend bundle initialises so the preview
     * always starts fresh at the first step (Category, or Location for Pro),
     * mirroring the Gutenberg block's static first-step preview. The inline
     * runs only in the editor preview (URL-gated); the published frontend keeps
     * its session persistence untouched.
     *
     * @return void
     */
    protected function registerEditorSessionReset(): void
    {
        static $added = false;

        if ($added || !wp_script_is(self::VIEW_HANDLE, 'registered')) {
            return;
        }

        $added = true;

        wp_add_inline_script(
            self::VIEW_HANDLE,
            "try{if(window.location.search.indexOf('elementor-preview=')>-1){window.sessionStorage.removeItem('rox_appointment_booking_service_state');}}catch(e){}",
            'before'
        );
    }

    /**
     * Registers the small handler script that mounts the panel on Elementor
     * widgets injected after the frontend bundle has run (editor drag-in,
     * control changes). Idempotent.
     *
     * @return void
     */
    protected function registerHandlerScript(): void
    {
        if (wp_script_is(self::HANDLER_HANDLE, 'registered')) {
            return;
        }

        $relative = 'src/Modules/Elementor/assets/widget-handler.js';
        $file     = ROX_APPOINTMENT_BOOKING_PATH . $relative;

        wp_register_script(
            self::HANDLER_HANDLE,
            ROX_APPOINTMENT_BOOKING_URL . $relative,
            ['elementor-frontend', self::VIEW_HANDLE],
            file_exists($file) ? (string) filemtime($file) : ROX_APPOINTMENT_BOOKING_VERSION,
            true
        );
    }

    /**
     * Frontend config the shortcode booking panel expects. Mirrors
     * FrontendApp::frontendAppVars() / BookingPanelBlock::frontendConfig().
     *
     * @return array
     */
    protected function frontendConfig(): array
    {
        return [
            'version'           => ROX_APPOINTMENT_BOOKING_VERSION,
            'appTitle'          => ROX_APPOINTMENT_BOOKING_NAME,
            'defaultLocale'     => 'en_US',
            'timezone'          => get_option('timezone_string') ?: 'UTC',
            'dateFormat'        => get_option('date_format') ?: 'Y-m-d',
            'timeFormat'        => get_option('time_format') ?: 'H:i:s',
            'appRootDomId'      => 'rox-appointment-booking-frontend-root',
            'publicUrl'         => esc_url_raw(ROX_APPOINTMENT_BOOKING_PUBLIC_URL),
            'nonce'             => wp_create_nonce('rox_appointment_booking_frontend_nonce'),
            'apiBaseUrl'        => esc_url_raw(rest_url('rox-appointment-booking/v1/')),
            'restBaseUrl'       => esc_url_raw(rest_url()),
            'siteUrl'           => esc_url_raw(site_url()),
            'is_user_logged_in' => is_user_logged_in(),
            'logout_url'        => html_entity_decode(wp_logout_url()),
        ];
    }
}
