<?php

/**
 * Class LoginFormBlock
 *
 * @package RoxAppointmentBooking
 * @subpackage Modules\Blocks\Services
 * @since 1.0.0
 *
 * Registers the "Rox Appointment Login Form" Gutenberg block. The block is a
 * thin wrapper: it reuses the standalone login form's frontend bundle
 * (`blocks/login-form/view.js` / `view.css`, built in Phase 1) unchanged and
 * only adds an editor bundle for the static preview. The mount config comes
 * from `LoginForm\Services\LoginFormConfig`, the single home for that logic,
 * shared with the shortcode and the Elementor widget.
 */

namespace RoxAppointmentBooking\Modules\Blocks\Services;

use RoxAppointmentBooking\Supports\Assets;
use RoxAppointmentBooking\Modules\LoginForm\Services\LoginFormConfig;
use RoxAppointmentBooking\Modules\LoginForm\Services\LoginFormShortcode;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

class LoginFormBlock
{
    /**
     * Whether the service should be loadable.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * Editor script handle (matches block.json `editorScript`).
     */
    protected const EDITOR_HANDLE = 'rox-appointment-booking-login-form-editor';

    /**
     * Editor style handle (matches block.json `editorStyle`).
     */
    protected const EDITOR_STYLE_HANDLE = 'rox-appointment-booking-login-form-editor-style';

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
        add_action('init', [$this, 'registerBlock']);
    }

    /**
     * Registers the editor + view assets and the dynamic block type.
     *
     * @return void
     */
    public function registerBlock(): void
    {
        $block_dir  = ROX_APPOINTMENT_BOOKING_PATH . 'src/resources/blocks/login-form';
        $build_path = ROX_APPOINTMENT_BOOKING_PATH . 'public/build/';
        $build_url  = ROX_APPOINTMENT_BOOKING_PUBLIC_URL . 'build/';

        $this->registerEditorAssets($build_url, $build_path);
        $this->registerViewAssets($build_url, $build_path);

        register_block_type($block_dir, [
            'render_callback' => [$this, 'renderBlock'],
        ]);
    }

    /**
     * Registers the editor bundle. It is a webpack entry chunk, so it needs the
     * shared runtime chunk loaded first — otherwise `registerBlockType()` never
     * fires and the block is missing from the inserter.
     *
     * @param string $build_url  Build dir URL (trailing slash).
     * @param string $build_path Build dir path (trailing slash).
     * @return void
     */
    protected function registerEditorAssets(string $build_url, string $build_path): void
    {
        $editor_asset_file = $build_path . 'blocks/login-form/index.asset.php';
        if (!file_exists($editor_asset_file)) {
            return;
        }

        $asset = require $editor_asset_file;

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

        wp_register_script(
            self::EDITOR_HANDLE,
            $build_url . 'blocks/login-form/index.js',
            array_merge([self::RUNTIME_HANDLE], $asset['dependencies'] ?? []),
            $asset['version'] ?? ROX_APPOINTMENT_BOOKING_VERSION,
            true
        );

        wp_set_script_translations(
            self::EDITOR_HANDLE,
            'rox-appointment-booking',
            ROX_APPOINTMENT_BOOKING_PATH . 'languages'
        );

        wp_register_style(
            self::EDITOR_STYLE_HANDLE,
            $build_url . 'blocks/login-form/index.css',
            [],
            $asset['version'] ?? ROX_APPOINTMENT_BOOKING_VERSION
        );
    }

    /**
     * Registers the Phase-1 view bundle (runtime -> vendors -> view) under the
     * handles `block.json` names.
     *
     * The shortcode registers the same handles on `wp_enqueue_scripts`; whichever
     * runs first wins and the other call is a no-op, because `wp_register_script()`
     * ignores an already-registered handle. Registering here too is what lets
     * `register_block_type()` resolve `viewScript` at `init`.
     *
     * @param string $build_url  Build dir URL (trailing slash).
     * @param string $build_path Build dir path (trailing slash).
     * @return void
     */
    protected function registerViewAssets(string $build_url, string $build_path): void
    {
        $view_asset_file = $build_path . 'blocks/login-form/view.asset.php';
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
            LoginFormShortcode::VIEW_HANDLE,
            $build_url . 'blocks/login-form/view.js',
            [$shared['script']],
            $view_asset['version'] ?? ROX_APPOINTMENT_BOOKING_VERSION,
            true
        );

        wp_set_script_translations(
            LoginFormShortcode::VIEW_HANDLE,
            'rox-appointment-booking',
            ROX_APPOINTMENT_BOOKING_PATH . 'languages'
        );

        wp_register_style(
            LoginFormShortcode::VIEW_STYLE_HANDLE,
            $build_url . 'blocks/login-form/view.css',
            array_filter([$shared['style']]),
            $view_asset['version'] ?? ROX_APPOINTMENT_BOOKING_VERSION
        );
    }

    /**
     * Server-renders the root div the view bundle mounts on.
     *
     * @param array $attributes Block attributes.
     * @return string
     */
    public function renderBlock($attributes = []): string
    {
        self::$instance_count++;

        $config = LoginFormConfig::build(
            isset($attributes['redirectUrl']) ? (string) $attributes['redirectUrl'] : '',
            isset($attributes['loginLabel']) ? (string) $attributes['loginLabel'] : '',
            !isset($attributes['showGoogle']) || !empty($attributes['showGoogle'])
        );

        $css_vars   = $this->buildCssVars(is_array($attributes) ? $attributes : []);
        $style_attr = $css_vars !== '' ? ' style="' . esc_attr($css_vars) . '"' : '';

        // Alignment lives on the block wrapper as flex justification, so the
        // container's own margin control stays free for real spacing.
        $justify = [
            'left'   => 'flex-start',
            'center' => 'center',
            'right'  => 'flex-end',
        ][$attributes['formAlign'] ?? 'left'] ?? 'flex-start';

        $wrapper_attributes = get_block_wrapper_attributes([
            'style' => 'display:flex;justify-content:' . $justify . ';',
        ]);

        return sprintf(
            '<div %1$s><div class="rox-appointment-booking-login-form-root" data-instance="%2$s" data-config="%3$s"%4$s></div></div>',
            $wrapper_attributes,
            esc_attr((string) self::$instance_count),
            esc_attr(wp_json_encode($config)),
            $style_attr
        );
    }

    /**
     * Builds the inline `--rlf-*` CSS custom properties from the block
     * attributes, emitting only the ones that are set so the SCSS fallbacks keep
     * the default booking-panel look.
     *
     * Mirrors `buildCssVars()` in `edit.jsx`, which applies the same set inline
     * for the editor preview.
     *
     * @param array $attributes Block attributes.
     * @return string
     */
    protected function buildCssVars(array $attributes): string
    {
        $vars = [];

        // Colors + plain lengths/keywords: emitted verbatim after sanitising.
        $value_map = [
            '--rlf-bg'                 => 'bgColor',
            '--rlf-border-color'       => 'borderColor',
            '--rlf-label'              => 'labelColor',
            '--rlf-input-color'        => 'inputColor',
            '--rlf-input-bg'           => 'inputBg',
            '--rlf-input-border'       => 'inputBorder',
            '--rlf-input-focus-border' => 'inputFocusBorder',
            '--rlf-btn-bg'             => 'btnBg',
            '--rlf-btn-color'          => 'btnColor',
            '--rlf-btn-hover-bg'       => 'btnHoverBg',
            '--rlf-btn-hover-color'    => 'btnHoverColor',
            '--rlf-link'               => 'linkColor',
            '--rlf-link-hover'         => 'linkHoverColor',
            '--rlf-error-color'        => 'errorColor',
            '--rlf-error-bg'           => 'errorBg',
            '--rlf-success-color'      => 'successColor',
            '--rlf-success-bg'         => 'successBg',
            '--rlf-width'              => 'formWidth',
            '--rlf-border-width'       => 'borderWidth',
            '--rlf-border-style'       => 'borderStyle',
            '--rlf-radius'             => 'borderRadius',
            '--rlf-input-radius'       => 'inputRadius',
            '--rlf-btn-radius'         => 'btnRadius',
        ];

        foreach ($value_map as $var => $key) {
            if (!empty($attributes[$key])) {
                $value = $this->sanitizeCssValue($attributes[$key]);
                if ($value !== '') {
                    $vars[] = $var . ':' . $value;
                }
            }
        }

        $box_map = [
            '--rlf-margin'      => 'formMargin',
            '--rlf-padding'     => 'formPadding',
            '--rlf-btn-margin'  => 'btnMargin',
            '--rlf-btn-padding' => 'btnPadding',
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
