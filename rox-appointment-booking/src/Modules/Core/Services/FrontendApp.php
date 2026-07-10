<?php

/**
 * Class FrontendApp
 * 
 * @package RoxAppointmentBooking
 * @subpackage Modules\Core\Services
 * @since 1.0.0
 *
 * Handles the frontend assets for the Booking Engine plugin.
 * Enqueues scripts and styles for the public-facing side of the site.
 */

namespace RoxAppointmentBooking\Modules\Core\Services;

use RoxAppointmentBooking\Supports\Assets;

if (! defined('ABSPATH')) exit; // Exit if accessed directly


class FrontendApp
{
    /**
     * Whether the service should be loadable.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * Constructor.
     * 
     * Initializes the class by registering WordPress frontend hooks.
     */
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets'], 100);
        add_shortcode('rox_appointment_booking', [$this, 'renderShortcode']);
    }

    /**
     * Returns JavaScript variables for the frontend app.
     *
     * @return array
     */
    public function frontendAppVars(): array
    {
        return [
            'version' => ROX_APPOINTMENT_BOOKING_VERSION,
            'appTitle' => ROX_APPOINTMENT_BOOKING_NAME,
            'defaultLocale' => 'en_US',
            'timezone' => get_option('timezone_string') ?: 'UTC',
            'dateFormat' => get_option('date_format') ?: 'Y-m-d',
            'timeFormat' => get_option('time_format') ?: 'H:i:s',
            'appRootDomId' => 'rox-appointment-booking-frontend-root',
            'publicUrl' => esc_url_raw(ROX_APPOINTMENT_BOOKING_PUBLIC_URL),
            'nonce' => wp_create_nonce('rox_appointment_booking_frontend_nonce'),
            'apiBaseUrl' => esc_url_raw(rest_url('rox-appointment-booking/v1/')),
            'restBaseUrl' => esc_url_raw(rest_url()),
            'siteUrl' => esc_url_raw(site_url()),
            // User authentication status only - actual user data fetched via secure API
            'is_user_logged_in' => is_user_logged_in(),
        ];
    }

    /**
     * Enqueues the required CSS and JavaScript files for frontend.
     *
     * @return void
     */
    public function enqueueAssets(): void
    {
        // Only load on frontend when needed
        if (is_admin()) {
            return;
        }

        // Check if we should load assets (e.g., shortcode is present)
        if (!$this->shouldLoadAssets()) {
            return;
        }

        $public_url = ROX_APPOINTMENT_BOOKING_PUBLIC_URL;
        $plugin_path = ROX_APPOINTMENT_BOOKING_PATH;
        
        // Load dependencies from the auto-generated asset file
        $asset_file = $plugin_path . 'public/build/frontend/app.asset.php';
        $asset = file_exists($asset_file) ? require($asset_file) : [
            'dependencies' => ['react', 'react-dom', 'wp-element'],
            'version' => ROX_APPOINTMENT_BOOKING_VERSION
        ];

        // Register the shared runtime/vendors chunks first so the entry can
        // depend on them (runtime -> vendors -> entry load order).
        $shared = Assets::enqueueSharedChunks(
            $public_url . 'build/',
            $plugin_path . 'public/build/',
            $asset['dependencies'],
            $asset['version'],
        );

        Assets::enqueueStyle(
            'rox-appointment-booking-frontend',
            $public_url . 'build/frontend/app.css',
            array_filter([$shared['style']]),
            $asset['version'],
        );

        Assets::enqueueScript(
            'rox-appointment-booking-frontend',
            $public_url . 'build/frontend/app.js',
            [$shared['script']],
            $asset['version'],
            true,
        );

        wp_add_inline_script(
            'rox-appointment-booking-frontend',
            'window.rox_appointment_booking = window.rox_appointment_booking || {}; ' .
                'window.rox_appointment_booking.config = window.rox_appointment_booking.config || {}; ' .
                'window.rox_appointment_booking.config.app = ' . json_encode($this->frontendAppVars()) . ';',
            'before'
        );
    }

    /**
     * Determines if frontend assets should be loaded.
     *
     * @return bool
     */
    protected function shouldLoadAssets(): bool
    {
        global $post;

        // Always load if we can't check (will be needed by shortcode)
        if (!$post) {
            return false;
        }

        // Check if shortcode exists in content
        if (has_shortcode($post->post_content, 'rox_appointment_booking')) {
            return true;
        }

        // Allow filtering for custom conditions
        return apply_filters('rox_appointment_booking/frontend/should_load_assets', false);
    }

    /**
     * Renders the booking engine shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function renderShortcode($atts = []): string
    {
        static $instance_count = 0;
        $instance_count++;

        $atts = shortcode_atts([
            'type' => 'booking-form',
        ], $atts, 'rox_appointment_booking');

        ob_start();
        ?>
        <div class="rox-appointment-booking-frontend-root" data-instance="<?php echo esc_attr($instance_count); ?>" data-type="<?php echo esc_attr($atts['type']); ?>"></div>
        <?php
        return ob_get_clean();
    }
}
