<?php

/**
 * Class BookingPanelBlock
 *
 * @package RoxAppointmentBooking
 * @subpackage Modules\Blocks\Services
 * @since 1.0.0
 *
 * Registers the "Rox Appointment Booking Panel" Gutenberg block, which shows the
 * booking panel one of two ways:
 *
 * - `general` (the default): the panel is laid out on the page itself. The block
 *   ships no UI of its own here — it reuses the exact frontend bundle the
 *   `[rox_appointment_booking]` shortcode mounts (`build/frontend/app.js` +
 *   `app.css`) and renders the same `rox-appointment-booking-frontend-root`
 *   mount node, so the block shows the same panel as the shortcode.
 * - `popup`: only a trigger button is rendered, through
 *   {@see BookingButtonMarkup} so it stays byte-identical to the Elementor
 *   widget's. The modal and the panel inside it are built client-side on the
 *   first click by the view bundle {@see BookingButtonAssets} registers.
 *
 * The frontend view handle (`rox-appointment-booking-frontend`) is shared with
 * `FrontendApp`, so when both the shortcode and the block appear on a page the
 * bundle is enqueued only once.
 */

namespace RoxAppointmentBooking\Modules\Blocks\Services;

use RoxAppointmentBooking\Supports\Assets;
use RoxAppointmentBooking\Supports\BookingButtonAssets;
use RoxAppointmentBooking\Supports\BookingButtonMarkup;
use RoxAppointmentBooking\Supports\Color;
use RoxAppointmentBooking\Supports\FontFamily;
use RoxAppointmentBooking\Supports\IdList;
use RoxAppointmentBooking\Supports\NavButtons;

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

        wp_set_script_translations(
            self::EDITOR_HANDLE,
            'rox-appointment-booking',
            ROX_APPOINTMENT_BOOKING_PATH . 'languages'
        );

        // The font picker's list, so PHP stays the only place it is defined and
        // the editor preview can resolve a key to its stack and web-font URL.
        wp_add_inline_script(
            self::EDITOR_HANDLE,
            'window.rox_appointment_booking = window.rox_appointment_booking || {}; ' .
                'window.rox_appointment_booking.fontFamilies = ' . wp_json_encode(FontFamily::options()) . ';',
            'before'
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
     * The popup's own bundle is registered alongside it but not declared in
     * block.json: a block sitting in general mode has no trigger to drive, so
     * renderBlock() enqueues it only for the blocks that need it.
     *
     * @param string $build_url  Build dir URL (trailing slash).
     * @param string $build_path Build dir path (trailing slash).
     * @return void
     */
    protected function registerViewAssets(string $build_url, string $build_path): void
    {
        // Shared with FrontendApp; if already registered there is nothing to do.
        if (wp_script_is(self::VIEW_HANDLE, 'registered')) {
            BookingButtonAssets::register();
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

        wp_set_script_translations(
            self::VIEW_HANDLE,
            'rox-appointment-booking',
            ROX_APPOINTMENT_BOOKING_PATH . 'languages'
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

        BookingButtonAssets::register();
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
            'defaultLocale'     => determine_locale(),
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
            'dashboardUrl'      => rox_appointment_booking_dashboard_url(),
        ];
    }

    /**
     * Server-renders the block for the mode it is set to.
     *
     * @param array $attributes Block attributes.
     * @return string
     */
    public function renderBlock($attributes = []): string
    {
        $attributes = is_array($attributes) ? $attributes : [];

        // Anything but an explicit "popup" is the inline panel, so a block saved
        // before the mode existed keeps rendering exactly as it did.
        return ($attributes['displayMode'] ?? '') === 'popup'
            ? $this->renderPopup($attributes)
            : $this->renderInline($attributes);
    }

    /**
     * Renders the inline panel: the same root node the shortcode uses, which the
     * shared frontend bundle mounts the booking panel on.
     *
     * @param array $attributes Block attributes.
     * @return string
     */
    protected function renderInline(array $attributes): string
    {
        self::$instance_count++;

        $wrapper_attributes = get_block_wrapper_attributes();

        // The Back / Next buttons are rendered by React deep inside the panel,
        // so their styling travels as custom properties on the mount node and
        // is read from there by the panel stylesheet.
        //
        // Written here rather than handed to get_block_wrapper_attributes():
        // that runs the style through safecss_filter_attr(), which drops any
        // declaration containing parentheses unless the function is var/calc/
        // min/max — so an rgba() colour from the picker would never survive.
        $nav_style = $this->panelStyle($attributes);

        $hide_navigation = !empty($attributes['hideNavigation']) ? 'true' : 'false';
        $hide_info       = !empty($attributes['hideInfo']) ? 'true' : 'false';

        // The grey frame around the panel; the colour is dropped unless it
        // sanitises to a plain hex / rgb() value.
        $show_background  = $this->showBackground($attributes) ? 'true' : 'false';
        $background_color = Color::sanitize($this->str($attributes['backgroundColor'] ?? ''));

        // The panel is handed a resolved CSS stack, never the stored key, so an
        // unknown key falls back to the stylesheet's own font. Google-hosted
        // faces are fetched here because only now is the choice known.
        $font_family = $this->fontStack($attributes);

        // Optional "only offer these" picks. An empty list is the default and
        // means the panel offers every location / category, exactly as before.
        $location_ids = IdList::toAttr($attributes['locationIds'] ?? []);
        $category_ids = IdList::toAttr($attributes['categoryIds'] ?? []);

        return sprintf(
            '<div %1$s><div class="rox-appointment-booking-frontend-root"%10$s data-instance="%2$s" data-type="booking-form" data-hide-navigation="%3$s" data-hide-info="%4$s" data-content-margin="%11$s" data-heading-align="%12$s" data-heading-margin="%13$s" data-show-background="%5$s" data-background-color="%6$s" data-font-family="%7$s" data-locations="%8$s" data-categories="%9$s"></div></div>',
            $wrapper_attributes,
            // Prefixed per surface: the instance id keys the panel's store, and
            // every surface counts from 1, so a bare number would let a panel
            // block and a popup trigger on one page share one store.
            esc_attr('panel-' . self::$instance_count),
            esc_attr($hide_navigation),
            esc_attr($hide_info),
            esc_attr($show_background),
            esc_attr($background_color),
            esc_attr($font_family),
            esc_attr($location_ids),
            esc_attr($category_ids),
            $nav_style === '' ? '' : ' style="' . esc_attr($nav_style) . '"',
            esc_attr($this->contentSpacing($attributes, 'contentMargin', true)),
            esc_attr($this->headingAlign($attributes)),
            esc_attr($this->contentSpacing($attributes, 'headingMargin', true))
        );
    }

    /**
     * Where a step's heading sits, validated against the whitelist.
     *
     * Empty is the default and the answer for anything unrecognised: the
     * value reaches the stylesheet as a class name, and block attributes are
     * whatever was saved in the post.
     *
     * @param array $attributes Block attributes.
     * @return string
     */
    protected function headingAlign(array $attributes): string
    {
        $value = $this->str($attributes['headingAlign'] ?? '');

        return in_array($value, BookingButtonMarkup::HEADING_ALIGNS, true) ? $value : '';
    }

    /**
     * The content column's padding or margin, as the `top,right,bottom,left`
     * string the mount node carries.
     *
     * Delegated to the markup builder so the inline panel and the popup
     * normalise a length the same way — the popup's copy travels through
     * BookingButtonMarkup already.
     *
     * @param array  $attributes     Block attributes.
     * @param string $key            Attribute name.
     * @param bool   $allow_negative Whether a negative length is meaningful.
     * @return string
     */
    protected function contentSpacing(array $attributes, string $key, bool $allow_negative): string
    {
        return BookingButtonMarkup::contentSpacing($attributes[$key] ?? [], $allow_negative);
    }

    /**
     * Renders the popup trigger. The modal and the panel mount node are created
     * client-side on first click, so nothing panel-shaped is emitted here — only
     * the settings that panel will need, carried on the button.
     *
     * @param array $attributes Block attributes.
     * @return string
     */
    protected function renderPopup(array $attributes): string
    {
        // Not declared in block.json: only the blocks actually in popup mode
        // need the trigger bundle. The panel bundle rides along as its
        // dependency, which is what makes the modal able to mount a panel.
        if (BookingButtonAssets::register()) {
            wp_enqueue_script(BookingButtonAssets::VIEW_HANDLE);
            wp_enqueue_style(BookingButtonAssets::VIEW_HANDLE);
        }

        $wrapper_attributes = get_block_wrapper_attributes([
            'class' => 'rox-booking-button-wrap',
        ]);

        return BookingButtonMarkup::render(
            [
                'text'                 => $attributes['buttonText'] ?? '',
                'align'                => $attributes['buttonAlign'] ?? 'left',
                'alignTablet'          => $attributes['buttonAlignTablet'] ?? '',
                'alignMobile'          => $attributes['buttonAlignMobile'] ?? '',
                'width'                => $attributes['buttonWidth'] ?? 'auto',
                'size'                 => $attributes['buttonSize'] ?? 'medium',
                'style'                => $attributes['buttonStyle'] ?? 'filled',
                'icon'                 => $attributes['buttonIcon'] ?? 'calendar',
                'backgroundColor'      => $attributes['buttonBackgroundColor'] ?? '',
                'textColor'            => $attributes['buttonTextColor'] ?? '',
                'borderColor'          => $attributes['buttonBorderColor'] ?? '',
                'backgroundColorHover' => $attributes['buttonBackgroundColorHover'] ?? '',
                'textColorHover'       => $attributes['buttonTextColorHover'] ?? '',
                'borderColorHover'     => $attributes['buttonBorderColorHover'] ?? '',
                'borderWidth'          => $attributes['buttonBorderWidth'] ?? 1,
                'borderWidthTablet'    => $attributes['buttonBorderWidthTablet'] ?? null,
                'borderWidthMobile'    => $attributes['buttonBorderWidthMobile'] ?? null,
                'borderWidthHover'     => $attributes['buttonBorderWidthHover'] ?? null,
                'borderStyle'          => $attributes['buttonBorderStyle'] ?? 'solid',
                'borderRadius'         => $attributes['buttonBorderRadius'] ?? 6,
                'borderRadiusTablet'   => $attributes['buttonBorderRadiusTablet'] ?? null,
                'borderRadiusMobile'   => $attributes['buttonBorderRadiusMobile'] ?? null,
                'padding'              => $attributes['buttonPadding'] ?? [],
                'paddingTablet'        => $attributes['buttonPaddingTablet'] ?? [],
                'paddingMobile'        => $attributes['buttonPaddingMobile'] ?? [],
                'margin'               => $attributes['buttonMargin'] ?? [],
                'marginTablet'         => $attributes['buttonMarginTablet'] ?? [],
                'marginMobile'         => $attributes['buttonMarginMobile'] ?? [],
                'buttonWidthSize'      => $attributes['buttonWidthSize'] ?? null,
                'buttonWidthSizeTablet' => $attributes['buttonWidthSizeTablet'] ?? null,
                'buttonWidthSizeMobile' => $attributes['buttonWidthSizeMobile'] ?? null,
                'iconSize'             => $attributes['buttonIconSize'] ?? null,
                'iconSizeTablet'       => $attributes['buttonIconSizeTablet'] ?? null,
                'iconSizeMobile'       => $attributes['buttonIconSizeMobile'] ?? null,
                'iconGap'              => $attributes['buttonIconGap'] ?? null,
                'iconGapTablet'        => $attributes['buttonIconGapTablet'] ?? null,
                'iconGapMobile'        => $attributes['buttonIconGapMobile'] ?? null,
                'iconOffsetY'          => $attributes['buttonIconOffsetY'] ?? null,
                'iconOffsetYTablet'    => $attributes['buttonIconOffsetYTablet'] ?? null,
                'iconOffsetYMobile'    => $attributes['buttonIconOffsetYMobile'] ?? null,
                'boxShadow'            => $attributes['buttonBoxShadow'] ?? [],
                'boxShadowHover'       => $attributes['buttonBoxShadowHover'] ?? [],
                'modalWidth'           => $attributes['modalWidth'] ?? 1100,
                'hideNavigation'       => !empty($attributes['hideNavigation']),
                'hideInfo'             => !empty($attributes['hideInfo']),
                'contentMargin'        => $attributes['contentMargin'] ?? [],
                'headingAlign'         => $this->headingAlign($attributes),
                'headingMargin'        => $attributes['headingMargin'] ?? [],
                // No agent lock from this block: a panel tied to one agent is
                // what the Single Agent Booking Panel block is for, so the
                // markup builder's "no lock" default stands.
                'locationIds'          => $attributes['locationIds'] ?? [],
                'categoryIds'          => $attributes['categoryIds'] ?? [],
                'resetOnClose'         => !empty($attributes['resetOnClose']),
                // The panel appearance controls are shared by both modes, so
                // they ride along to the mount node the modal builds.
                'showPanelBackground'  => $this->showBackground($attributes),
                'panelBackgroundColor' => $this->str($attributes['backgroundColor'] ?? ''),
                'fontFamily'           => $this->fontStack($attributes),
                'panelStyle'           => $this->panelStyle($attributes),
            ],
            $wrapper_attributes
        );
    }

    /**
     * The panel's own custom properties: the Back / Next styling plus the accent
     * every other panel colour is derived from.
     *
     * The accent rides on the same declaration string as the nav variables: the
     * stylesheet derives every lighter shade and translucent wash from this one
     * property, and the two nav buttons still win where they set their own
     * colour. Empty leaves the stylesheet's own blue in force.
     *
     * @param array $attributes Block attributes.
     * @return string Semicolon-separated declarations, or '' when nothing is set.
     */
    protected function panelStyle(array $attributes): string
    {
        $style        = NavButtons::cssVars($attributes);
        $accent_color = Color::sanitize($this->str($attributes['accentColor'] ?? ''));

        if ($accent_color !== '') {
            $style = rtrim('--rox-accent:' . $accent_color . ';' . $style, ';');
        }

        return $style;
    }

    /**
     * Whether the panel draws its own grey frame.
     *
     * Defaults to on, so only an explicit false switches it off.
     *
     * @param array $attributes Block attributes.
     * @return bool
     */
    protected function showBackground(array $attributes): bool
    {
        return !array_key_exists('showBackground', $attributes)
            || !empty($attributes['showBackground']);
    }

    /**
     * Resolves the chosen font to a CSS stack and queues its web font.
     *
     * @param array $attributes Block attributes.
     * @return string CSS font stack, or '' to keep the stylesheet's own font.
     */
    protected function fontStack(array $attributes): string
    {
        $font_key = $this->str($attributes['fontFamily'] ?? '');

        FontFamily::enqueue($font_key);

        return FontFamily::stack($font_key);
    }

    /**
     * Reads an attribute as a string.
     *
     * Casting straight to string is not safe: block.json's schema does not
     * validate an `object` attribute's inner values, so a hand-edited or
     * migrated post could hand an array to `(string)` — a warning at best.
     *
     * @param mixed $value Raw attribute.
     * @return string Trimmed string, or '' when the value is not stringable.
     */
    protected function str($value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
