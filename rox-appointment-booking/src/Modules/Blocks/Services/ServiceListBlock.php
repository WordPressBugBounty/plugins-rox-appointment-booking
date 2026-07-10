<?php

/**
 * Class ServiceListBlock
 *
 * @package RoxAppointmentBooking
 * @subpackage Modules\Blocks\Services
 * @since 1.0.0
 *
 * Registers the self-contained "Service List" Gutenberg block. The block has
 * its own editor bundle (static first-step preview) and its own frontend bundle
 * (`view.js`) that mounts a bespoke service-first booking flow on a dedicated
 * root class `rox-appointment-booking-service-list-root`.
 *
 * The shortcode, `FrontendApp` and `BookingService` are NOT touched. The block
 * never reuses the shortcode mount class, so the two never collide.
 */

namespace RoxAppointmentBooking\Modules\Blocks\Services;

use RoxAppointmentBooking\Supports\Assets;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

class ServiceListBlock
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
    protected const BLOCK_NAME = 'rox-appointment-booking/service-list';

    /**
     * Editor script handle (matches block.json `editorScript`).
     */
    protected const EDITOR_HANDLE = 'rox-appointment-booking-service-list-editor';

    /**
     * Editor style handle (matches block.json `editorStyle`).
     */
    protected const EDITOR_STYLE_HANDLE = 'rox-appointment-booking-service-list-editor-style';

    /**
     * Frontend (view) script handle (matches block.json `viewScript`).
     */
    protected const VIEW_HANDLE = 'rox-appointment-booking-service-list-view';

    /**
     * Frontend (view) style handle (matches block.json `viewStyle`).
     */
    protected const VIEW_STYLE_HANDLE = 'rox-appointment-booking-service-list-view-style';

    /**
     * Shared webpack runtime chunk handle.
     */
    protected const RUNTIME_HANDLE = 'rox-appointment-booking-runtime';

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
        // Temporarily disabled for release: the Service List block is not yet
        // complete, so its registration is commented out to hide it from
        // clients (block inserter). Uncomment to re-enable.
        // add_action('init', [$this, 'registerBlock']);
    }

    /**
     * Registers the editor + view assets and the dynamic block type.
     *
     * @return void
     */
    public function registerBlock(): void
    {
        $block_dir  = ROX_APPOINTMENT_BOOKING_PATH . 'src/resources/blocks/service-list';
        $build_path = ROX_APPOINTMENT_BOOKING_PATH . 'public/build/';
        $build_url  = ROX_APPOINTMENT_BOOKING_PUBLIC_URL . 'build/';

        $runtime_handle = $this->registerRuntime($build_url, $build_path);

        // Editor bundle: a webpack entry chunk, so it needs the shared runtime
        // chunk loaded first (otherwise registerBlockType() never fires and the
        // block is missing from the inserter). Its other deps are wp-* externals.
        $editor_asset_file = $build_path . 'blocks/service-list/index.asset.php';
        if (file_exists($editor_asset_file)) {
            $asset = require $editor_asset_file;

            wp_register_script(
                self::EDITOR_HANDLE,
                $build_url . 'blocks/service-list/index.js',
                array_merge([$runtime_handle], $asset['dependencies'] ?? []),
                $asset['version'] ?? ROX_APPOINTMENT_BOOKING_VERSION,
                true
            );

            wp_register_style(
                self::EDITOR_STYLE_HANDLE,
                $build_url . 'blocks/service-list/index.css',
                [],
                $asset['version'] ?? ROX_APPOINTMENT_BOOKING_VERSION
            );
        }

        // Frontend (view) bundle: reuses antd + other vendors, so it needs the
        // shared runtime + vendors chunks in order (runtime -> vendors -> view).
        $view_asset_file = $build_path . 'blocks/service-list/view.asset.php';
        if (file_exists($view_asset_file)) {
            $view_asset = require $view_asset_file;

            $shared = Assets::enqueueSharedChunks(
                $build_url,
                $build_path,
                $view_asset['dependencies'] ?? [],
                $view_asset['version'] ?? ROX_APPOINTMENT_BOOKING_VERSION
            );

            wp_register_script(
                self::VIEW_HANDLE,
                $build_url . 'blocks/service-list/view.js',
                [$shared['script']],
                $view_asset['version'] ?? ROX_APPOINTMENT_BOOKING_VERSION,
                true
            );

            wp_register_style(
                self::VIEW_STYLE_HANDLE,
                $build_url . 'blocks/service-list/view.css',
                array_filter([$shared['style']]),
                $view_asset['version'] ?? ROX_APPOINTMENT_BOOKING_VERSION
            );

            // The reused schedule/payment components + the block's api.js read
            // `window.rox_appointment_booking.config.app`. Print it before the
            // view script (the shortcode's FrontendApp is not loaded here).
            wp_add_inline_script(
                self::VIEW_HANDLE,
                'window.rox_appointment_booking = window.rox_appointment_booking || {}; ' .
                    'window.rox_appointment_booking.config = window.rox_appointment_booking.config || {}; ' .
                    'window.rox_appointment_booking.config.app = ' . wp_json_encode($this->frontendConfig()) . ';',
                'before'
            );
        }

        register_block_type($block_dir, [
            'render_callback' => [$this, 'renderBlock'],
        ]);
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
        if (!wp_script_is(self::RUNTIME_HANDLE, 'registered')) {
            $runtime_asset_file = $build_path . 'runtime.asset.php';
            $runtime_asset = file_exists($runtime_asset_file)
                ? require $runtime_asset_file
                : ['version' => ROX_APPOINTMENT_BOOKING_VERSION];

            wp_register_script(
                self::RUNTIME_HANDLE,
                $build_url . 'runtime.js',
                [],
                $runtime_asset['version'] ?? ROX_APPOINTMENT_BOOKING_VERSION,
                true
            );
        }

        return self::RUNTIME_HANDLE;
    }

    /**
     * Minimal frontend config the reused components + block api expect.
     *
     * @return array
     */
    protected function frontendConfig(): array
    {
        return [
            'apiBaseUrl'        => esc_url_raw(rest_url('rox-appointment-booking/v1/')),
            'restBaseUrl'       => esc_url_raw(rest_url()),
            'siteUrl'           => esc_url_raw(site_url()),
            'nonce'             => wp_create_nonce('rox_appointment_booking_frontend_nonce'),
            'is_user_logged_in' => is_user_logged_in(),
            'timezone'          => get_option('timezone_string') ?: 'UTC',
            'dateFormat'        => get_option('date_format') ?: 'Y-m-d',
            'timeFormat'        => get_option('time_format') ?: 'H:i:s',
        ];
    }

    /**
     * Server-renders the root div the block's view bundle mounts on.
     *
     * @param array $attributes Block attributes.
     * @return string
     */
    public function renderBlock($attributes = []): string
    {
        self::$instance_count++;

        $config = [
            'showStepSidebar' => !empty($attributes['showStepSidebar']),
            'showInfoSidebar' => !empty($attributes['showInfoSidebar']),
            'showServiceImage' => !isset($attributes['showServiceImage']) || !empty($attributes['showServiceImage']),
            'serviceLayout'   => (isset($attributes['serviceLayout']) && $attributes['serviceLayout'] === 'list') ? 'list' : 'grid',
            'nextLabel'       => isset($attributes['nextLabel']) ? sanitize_text_field($attributes['nextLabel']) : '',
            'backLabel'       => isset($attributes['backLabel']) ? sanitize_text_field($attributes['backLabel']) : '',
        ];

        $css_vars = $this->buildButtonCssVars($attributes);

        $wrapper_attributes = get_block_wrapper_attributes();

        $style_attr = $css_vars !== '' ? ' style="' . esc_attr($css_vars) . '"' : '';

        return sprintf(
            '<div %1$s><div class="rox-appointment-booking-service-list-root" data-instance="%2$s" data-config="%3$s"%4$s></div></div>',
            $wrapper_attributes,
            esc_attr((string) self::$instance_count),
            esc_attr(wp_json_encode($config)),
            $style_attr
        );
    }

    /**
     * Builds the inline `--rab-*` CSS custom properties for the buttons,
     * emitting only the ones that are set so SCSS fallbacks keep the default look.
     *
     * @param array $attributes Block attributes.
     * @return string
     */
    protected function buildButtonCssVars(array $attributes): string
    {
        $vars = [];

        $color_map = [
            '--rab-next-bg'          => 'nextBg',
            '--rab-next-color'       => 'nextColor',
            '--rab-next-bg-hover'    => 'nextHoverBg',
            '--rab-next-color-hover' => 'nextHoverColor',
            '--rab-back-color'       => 'backColor',
            '--rab-back-color-hover' => 'backHoverColor',
        ];

        foreach ($color_map as $var => $key) {
            if (!empty($attributes[$key])) {
                $value = $this->sanitizeCssValue($attributes[$key]);
                if ($value !== '') {
                    $vars[] = $var . ':' . $value;
                }
            }
        }

        $box_map = [
            '--rab-next-margin'  => 'nextMargin',
            '--rab-next-padding' => 'nextPadding',
            '--rab-back-margin'  => 'backMargin',
            '--rab-back-padding' => 'backPadding',
        ];

        foreach ($box_map as $var => $key) {
            if (!empty($attributes[$key]) && is_array($attributes[$key])) {
                $shorthand = $this->boxToShorthand($attributes[$key]);
                if ($shorthand !== '') {
                    $vars[] = $var . ':' . $shorthand;
                }
            }
        }

        return implode(';', $vars);
    }

    /**
     * Converts a BoxControl value ({top,right,bottom,left}) to a CSS shorthand.
     * Missing sides fall back to 0.
     *
     * @param array $box Box values.
     * @return string
     */
    protected function boxToShorthand(array $box): string
    {
        $sides = ['top', 'right', 'bottom', 'left'];
        $values = [];
        $has_value = false;

        foreach ($sides as $side) {
            $raw = isset($box[$side]) ? $this->sanitizeCssValue($box[$side]) : '';
            if ($raw !== '') {
                $has_value = true;
                $values[] = $raw;
            } else {
                $values[] = '0';
            }
        }

        return $has_value ? implode(' ', $values) : '';
    }

    /**
     * Sanitizes a value used inside an inline style (color or length). Keeps
     * characters valid for hex / rgb(a) / hsl(a) / named colors and lengths,
     * dropping anything that could break out of the attribute.
     *
     * @param mixed $value Raw value.
     * @return string
     */
    protected function sanitizeCssValue($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return trim(preg_replace('/[^a-zA-Z0-9 ,.#()%\/-]/', '', $value));
    }
}
