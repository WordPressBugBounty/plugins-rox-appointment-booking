<?php

/**
 * Class BookingPanelBlock
 *
 * @package RoxAppointmentBooking
 * @subpackage Modules\Blocks\Services
 * @since 1.0.0
 *
 * Registers the "Rox Appointment Booking Panel" Gutenberg block. The block does
 * NOT ship its own UI: it reuses the exact frontend bundle the
 * `[rox_appointment_booking]` shortcode mounts (`build/frontend/app.js` +
 * `app.css`) and renders the same `rox-appointment-booking-frontend-root` mount
 * node, so the block shows the same booking panel as the shortcode.
 *
 * The frontend view handle (`rox-appointment-booking-frontend`) is shared with
 * `FrontendApp`, so when both the shortcode and the block appear on a page the
 * bundle is enqueued only once.
 */

namespace RoxAppointmentBooking\Modules\Blocks\Services;

use RoxAppointmentBooking\Supports\Assets;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

class BookingPanelBlock
{
    /**
     * Whether the service should be loadable.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * Block name (matches block.json).
     */
    protected const BLOCK_NAME = 'rox-appointment-booking/booking-panel';

    /**
     * Editor script handle (matches block.json `editorScript`).
     */
    protected const EDITOR_HANDLE = 'rox-appointment-booking-booking-panel-editor';

    /**
     * Editor style handle (matches block.json `editorStyle`).
     */
    protected const EDITOR_STYLE_HANDLE = 'rox-appointment-booking-booking-panel-editor-style';

    /**
     * Frontend (view) script handle. Shared with FrontendApp's shortcode bundle
     * (matches block.json `viewScript`) so it loads only once per page.
     */
    protected const VIEW_HANDLE = 'rox-appointment-booking-frontend';

    /**
     * Per-request counter so every block on a page gets a unique instance id.
     *
     * @var int
     */
    protected static int $instance_count = 0;

    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('init', [$this, 'registerBlock']);
    }

    /**
     * Registers the editor assets, the shared frontend (view) bundle and the
     * dynamic block type.
     *
     * @return void
     */
    public function registerBlock(): void
    {
        $block_dir  = ROX_APPOINTMENT_BOOKING_PATH . 'src/resources/blocks/booking-panel';
        $build_path = ROX_APPOINTMENT_BOOKING_PATH . 'public/build/';
        $build_url  = ROX_APPOINTMENT_BOOKING_PUBLIC_URL . 'build/';

        $this->registerEditorAssets($build_url, $build_path);
        $this->registerViewAssets($build_url, $build_path);

        register_block_type($block_dir, [
            'render_callback' => [$this, 'renderBlock'],
        ]);
    }

    /**
     * Registers the editor bundle (placeholder preview in the block editor).
     *
     * @param string $build_url  Build dir URL (trailing slash).
     * @param string $build_path Build dir path (trailing slash).
     * @return void
     */
    protected function registerEditorAssets(string $build_url, string $build_path): void
    {
        $runtime_handle = $this->registerRuntime($build_url, $build_path);

        $editor_asset_file = $build_path . 'blocks/booking-panel/index.asset.php';
        if (!file_exists($editor_asset_file)) {
            return;
        }

        $asset = require $editor_asset_file;

        wp_register_script(
            self::EDITOR_HANDLE,
            $build_url . 'blocks/booking-panel/index.js',
            array_merge([$runtime_handle], $asset['dependencies'] ?? []),
            $asset['version'] ?? ROX_APPOINTMENT_BOOKING_VERSION,
            true
        );

        wp_register_style(
            self::EDITOR_STYLE_HANDLE,
            $build_url . 'blocks/booking-panel/index.css',
            [],
            $asset['version'] ?? ROX_APPOINTMENT_BOOKING_VERSION
        );
    }

    /**
     * Registers the shortcode's frontend bundle as the block's view assets and
     * prints the config the booking panel reads from
     * `window.rox_appointment_booking.config.app`.
     *
     * @param string $build_url  Build dir URL (trailing slash).
     * @param string $build_path Build dir path (trailing slash).
     * @return void
     */
    protected function registerViewAssets(string $build_url, string $build_path): void
    {
        // Shared with FrontendApp; if already registered there is nothing to do.
        if (wp_script_is(self::VIEW_HANDLE, 'registered')) {
            return;
        }

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

    /**
     * Registers the shared webpack runtime chunk (idempotent).
     *
     * @param string $build_url  Build dir URL (trailing slash).
     * @param string $build_path Build dir path (trailing slash).
     * @return string Runtime handle.
     */
    protected function registerRuntime(string $build_url, string $build_path): string
    {
        $runtime_handle = 'rox-appointment-booking-runtime';

        if (!wp_script_is($runtime_handle, 'registered')) {
            $runtime_asset_file = $build_path . 'runtime.asset.php';
            $runtime_asset = file_exists($runtime_asset_file)
                ? require $runtime_asset_file
                : ['version' => ROX_APPOINTMENT_BOOKING_VERSION];

            wp_register_script(
                $runtime_handle,
                $build_url . 'runtime.js',
                [],
                $runtime_asset['version'] ?? ROX_APPOINTMENT_BOOKING_VERSION,
                true
            );
        }

        return $runtime_handle;
    }

    /**
     * Frontend config the shortcode booking panel expects. Mirrors
     * FrontendApp::frontendAppVars().
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

    /**
     * Server-renders the same root node the shortcode uses, which the shared
     * frontend bundle mounts the booking panel on.
     *
     * @param array $attributes Block attributes.
     * @return string
     */
    public function renderBlock($attributes = []): string
    {
        self::$instance_count++;

        $wrapper_attributes = get_block_wrapper_attributes();

        $hide_navigation = !empty($attributes['hideNavigation']) ? 'true' : 'false';
        $hide_info       = !empty($attributes['hideInfo']) ? 'true' : 'false';

        return sprintf(
            '<div %1$s><div class="rox-appointment-booking-frontend-root" data-instance="%2$s" data-type="booking-form" data-hide-navigation="%3$s" data-hide-info="%4$s"></div></div>',
            $wrapper_attributes,
            esc_attr((string) self::$instance_count),
            esc_attr($hide_navigation),
            esc_attr($hide_info)
        );
    }
}
