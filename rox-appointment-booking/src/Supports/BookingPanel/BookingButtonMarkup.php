<?php

namespace RoxAppointmentBooking\Supports\BookingPanel;

use RoxAppointmentBooking\Supports\Color;
use RoxAppointmentBooking\Supports\IdList;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Markup builder for the trigger-button surfaces.
 *
 * The booking panel block's popup mode and the Elementor widget must emit
 * byte-for-byte the same button element, because a single view bundle
 * (`public/build/blocks/booking-panel/view.js`) drives both: it finds every
 * `[data-rox-booking-trigger]` node and reads the panel settings straight off
 * its `data-*` attributes. Keeping the markup in one place is what makes that
 * contract hold — a class name or attribute renamed on one surface alone would
 * silently break the other.
 *
 * Colours travel as CSS custom properties rather than as plain declarations.
 * An inline style cannot express `:hover`, so the stylesheet owns the hover rule
 * and reads `--rox-btn-*-hover` from it; that also lets Elementor feed the same
 * variables from its generated CSS instead of fighting the block over
 * specificity.
 *
 * The button deliberately renders NO panel root. The modal and its
 * `.rox-appointment-booking-frontend-root` are created by the view bundle on
 * first click, so a page full of buttons costs nothing until one is pressed.
 *
 * @package RoxAppointmentBooking
 * @subpackage Supports
 * @since 1.0.0
 */
class BookingButtonMarkup
{
    /**
     * CSS selector for the panel the modal builds, keyed to the surface that
     * opened it.
     *
     * The panel lives on `<body>`, outside the surface's own wrapper, so an
     * Elementor `{{WRAPPER}}` rule cannot reach it. Handing Elementor this
     * selector instead lets its generated CSS style that panel directly — which
     * is what keeps Global Colours and per-breakpoint values working, neither of
     * which survives being read back in PHP. `{{ID}}` is replaced with the
     * element id, and the surface writes the same id as `panelOwner`.
     */
    public const PANEL_OWNER_SELECTOR =
        '.rox-booking-modal .rox-appointment-booking-frontend-root[data-rox-owner="{{ID}}"]';

    /**
     * Button style variants.
     */
    public const STYLES = ['filled', 'outline', 'link'];

    /**
     * Button size variants.
     */
    public const SIZES = ['small', 'medium', 'large'];

    /**
     * Wrapper alignments.
     *
     * `full` is accepted for backwards compatibility only: alignment and width
     * used to share this one value, and blocks saved before they were split
     * still carry it. It is normalised to width `full` + alignment `left` in
     * render().
     */
    public const ALIGNMENTS = ['left', 'center', 'right', 'full'];

    /**
     * Button widths. `full` stretches the button to the container width.
     */
    public const WIDTHS = ['auto', 'full'];

    /**
     * Leading icons the button can render.
     */
    /**
     * Cached icon set, so a page with several buttons reads the JSON once.
     *
     * @var array<string, string>|null
     */
    protected static $icons = null;

    /**
     * Border styles offered by the surfaces.
     */
    public const BORDER_STYLES = ['none', 'solid', 'dashed', 'dotted', 'double'];

    /**
     * Where a step's heading sits. Empty is the default and means the
     * stylesheet is left alone, which is where the heading has always sat.
     *
     * @var string[]
     */
    public const HEADING_ALIGNS = ['left', 'center', 'right'];


    /**
     * CSS length units a spacing value may carry.
     */
    protected const UNITS = ['px', 'em', 'rem', '%'];

    /**
     * The devices a surface can style separately, mapped to the argument names
     * each one reads and to the variable suffix the stylesheet looks for.
     *
     * The empty suffix is the base value every device falls back to; the
     * stylesheet's media queries nest their fallbacks so an unset tablet or
     * mobile simply inherits the device above it.
     */
    protected const DEVICES = [
        '' => [
            'padding'      => 'padding',
            'margin'       => 'margin',
            'borderWidth'  => 'borderWidth',
            'borderRadius' => 'borderRadius',
            'width'        => 'buttonWidthSize',
            'iconSize'     => 'iconSize',
            'iconGap'      => 'iconGap',
            'iconOffsetY'  => 'iconOffsetY',
        ],
        '-t' => [
            'padding'      => 'paddingTablet',
            'margin'       => 'marginTablet',
            'borderWidth'  => 'borderWidthTablet',
            'borderRadius' => 'borderRadiusTablet',
            'width'        => 'buttonWidthSizeTablet',
            'iconSize'     => 'iconSizeTablet',
            'iconGap'      => 'iconGapTablet',
            'iconOffsetY'  => 'iconOffsetYTablet',
        ],
        '-m' => [
            'padding'      => 'paddingMobile',
            'margin'       => 'marginMobile',
            'borderWidth'  => 'borderWidthMobile',
            'borderRadius' => 'borderRadiusMobile',
            'width'        => 'buttonWidthSizeMobile',
            'iconSize'     => 'iconSizeMobile',
            'iconGap'      => 'iconGapMobile',
            'iconOffsetY'  => 'iconOffsetYMobile',
        ],
    ];

    /**
     * Per-request counter so every button on a page gets a unique instance id.
     *
     * @var int
     */
    protected static int $instance_count = 0;

    /**
     * Builds the button markup.
     *
     * Every argument is optional; anything missing or invalid falls back to the
     * documented default rather than reaching the page unchecked.
     *
     * Presentation is written as an inline `style` unless the caller sets
     * `inlineStyle` to false. Elementor does: its own controls generate a
     * stylesheet rule, which an inline style would always beat.
     *
     * @param array  $args             Button settings. See $defaults below.
     * @param string $wrapper_attrs    Ready-made attribute string for the outer
     *                                 element (Gutenberg's
     *                                 get_block_wrapper_attributes() output).
     *                                 Already escaped by the caller.
     * @return string
     */
    public static function render(array $args = [], string $wrapper_attrs = ''): string
    {
        self::$instance_count++;

        $defaults = [
            'text'                 => __('Book Appointment', 'rox-appointment-booking'),
            'align'                => 'left',
            'alignTablet'          => '',
            'alignMobile'          => '',
            'width'                => 'auto',
            'size'                 => 'medium',
            'style'                => 'filled',
            'backgroundColor'      => '#3560fb',
            'textColor'            => '#ffffff',
            'borderColor'          => '',
            'backgroundColorHover' => '',
            'textColorHover'       => '',
            'borderColorHover'     => '',
            // The fill behind an `outline` button. That variant spends
            // `backgroundColor` on its accent, so the fill needs a pair of its
            // own; left empty the variant stays transparent as it always has.
            'fillColor'            => '',
            'fillColorHover'       => '',
            'borderWidth'          => 1,
            'borderWidthTablet'    => null,
            'borderWidthMobile'    => null,
            'borderStyle'          => 'solid',
            'borderWidthHover'     => null,
            'borderRadius'         => 6,
            'borderRadiusTablet'   => null,
            'borderRadiusMobile'   => null,
            'padding'              => [],
            'paddingTablet'        => [],
            'paddingMobile'        => [],
            'margin'               => [],
            'marginTablet'         => [],
            'marginMobile'         => [],
            'buttonWidthSize'      => null,
            'buttonWidthSizeTablet' => null,
            'buttonWidthSizeMobile' => null,
            'iconSize'             => null,
            'iconSizeTablet'       => null,
            'iconSizeMobile'       => null,
            'iconGap'              => null,
            'iconGapTablet'        => null,
            'iconGapMobile'        => null,
            'iconOffsetY'          => null,
            'iconOffsetYTablet'    => null,
            'iconOffsetYMobile'    => null,
            'boxShadow'            => [],
            'boxShadowHover'       => [],
            'icon'                 => 'calendar',
            'modalWidth'           => 1100,
            'hideNavigation'       => false,
            'hideInfo'             => false,
            // How many service cards sit per row on the Services step. Clamped
            // to 1-2 on the way out; the panel keeps it one-per-row on narrow
            // screens whatever this says.
            'serviceColumns'       => 2,
            // The confirmation screen's "Go to Dashboard" button. Shown by
            // default; an empty label / URL falls back to "Go to Dashboard" and
            // the plugin's customer dashboard page.
            'showDashboardButton'  => true,
            'dashboardButtonText'  => '',
            'dashboardButtonUrl'   => '',
            // Where a step's heading sits, and the two nudges an editor can
            // give it and the content column under it. The content one is read
            // only once a column is hidden — with both in place the panel is
            // full and there is nothing to nudge into.
            'headingAlign'         => '',
            'headingMargin'        => [],
            'contentMargin'        => [],
            // The panel's own wording, as far as the surface let an editor
            // rewrite it. Normalised into the JSON blob the panel reads;
            // empty keeps every default.
            'panelContent'         => [],
            'agentId'              => 0,
            'locationIds'          => [],
            'categoryIds'          => [],
            'resetOnClose'         => false,
            // Carried through to the panel the modal builds, which lives on
            // <body> rather than inside this wrapper: the panel's own frame, a
            // resolved CSS font stack, and its custom properties (accent + Back
            // / Next). All produced by the surface, so they arrive ready to use.
            //
            // The frame is off unless a surface asks for it: the modal already
            // supplies a card, so a surface with no such control (Elementor)
            // would otherwise draw a second box inside the first.
            //
            // Named apart from `backgroundColor` above, which is the *button's*
            // fill — these two colour different things and both travel here.
            'showPanelBackground'  => false,
            'panelBackgroundColor' => '',
            'fontFamily'           => '',
            // Two ways to reach that panel, one per surface: the block resolves
            // its styling in PHP and sends the declarations, while Elementor
            // sends only an id and styles the panel through PANEL_OWNER_SELECTOR.
            'panelStyle'           => '',
            'panelOwner'           => '',
            'inlineStyle'          => true,
        ];

        $args = array_merge($defaults, $args);

        $align = self::oneOf($args['align'], self::ALIGNMENTS, 'left');
        $width = self::oneOf($args['width'], self::WIDTHS, 'auto');
        $size  = self::oneOf($args['size'], self::SIZES, 'medium');
        $style = self::oneOf($args['style'], self::STYLES, 'filled');
        $icon  = self::oneOf($args['icon'], self::iconKeys(), 'calendar');

        // Blocks saved before width and alignment were separate controls carry
        // the width in the alignment value.
        if ($align === 'full') {
            $width = 'full';
            $align = 'left';
        }

        // Empty text would render a button nobody can see the purpose of, so an
        // editor who clears the field gets the default back rather than a blank.
        $text = self::text($args['text']);
        if ($text === '') {
            $text = $defaults['text'];
        }

        // The panel is a wide, multi-column layout; below ~600px it has no room
        // to lay out, and beyond 2000px the modal stops being a modal.
        // Read through text() first: casting an array to int only warns, but an
        // object without __toString() is a fatal Error, and these values arrive
        // from stored settings that nothing upstream validates.
        $modal_width = max(600, min(2000, (int) self::text($args['modalWidth'])));

        $classes = [
            'rox-booking-button',
            'rox-booking-button--' . $style,
            'rox-booking-button--' . $size,
        ];

        if ($width === 'full') {
            $classes[] = 'rox-booking-button--full';
        }

        $use_inline    = !empty($args['inlineStyle']);
        $inline_style  = $use_inline ? self::buttonStyle($args, $style) : '';
        // A variable rather than `text-align` itself, so the stylesheet stays
        // the one place the rule lives. Elementor sets text-align from its own
        // responsive control, so it asks for no inline style at all.
        $wrapper_style = $use_inline ? self::wrapperStyle($args, $align) : '';

        $wrapper_attrs = trim($wrapper_attrs);
        if ($wrapper_attrs === '') {
            $wrapper_attrs = 'class="rox-booking-button-wrap"';
        }

        $icon_markup = self::icon($icon);

        return sprintf(
            '<div %1$s%2$s><button type="button" class="%3$s"%4$s '
                . 'data-rox-booking-trigger="1" data-instance="%5$s" data-type="booking-form" '
                . 'data-modal-width="%6$s" data-reset-on-close="%7$s" '
                . 'data-hide-navigation="%8$s" data-hide-info="%9$s" '
                . 'data-service-columns="%24$s" '
                . 'data-show-dashboard-button="%25$s" data-dashboard-button-text="%26$s" data-dashboard-button-url="%27$s" '
                . 'data-content-margin="%20$s" data-heading-align="%21$s" '
                . 'data-heading-margin="%22$s" data-panel-content="%23$s" '
                . 'data-locations="%10$s" data-categories="%11$s" data-agent-id="%12$s" '
                . 'data-show-background="%15$s" data-background-color="%16$s" '
                . 'data-font-family="%17$s" data-panel-style="%18$s" data-panel-owner="%19$s" '
                . 'aria-haspopup="dialog" aria-expanded="false">%13$s<span class="rox-booking-button__label">%14$s</span></button></div>',
            $wrapper_attrs,
            $wrapper_style === '' ? '' : ' style="' . esc_attr($wrapper_style) . '"',
            esc_attr(implode(' ', $classes)),
            $inline_style === '' ? '' : ' style="' . esc_attr($inline_style) . '"',
            // Prefixed per surface: the instance id keys the panel's store, and
            // every surface counts from 1, so a bare number would let a button
            // and a panel block on one page share one store.
            esc_attr('btn-' . self::$instance_count),
            esc_attr((string) $modal_width),
            esc_attr(self::boolAttr($args['resetOnClose'])),
            esc_attr(self::boolAttr($args['hideNavigation'])),
            esc_attr(self::boolAttr($args['hideInfo'])),
            esc_attr(IdList::toAttr($args['locationIds'])),
            esc_attr(IdList::toAttr($args['categoryIds'])),
            // 0 means "no lock" — the panel runs its normal multi-step flow.
            // Read through text() so a non-scalar lands on 0 rather than on the
            // 1 that casting an array to int would produce.
            esc_attr((string) max(0, (int) self::text($args['agentId']))),
            $icon_markup,
            esc_html($text),
            esc_attr(self::boolAttr($args['showPanelBackground'])),
            esc_attr(Color::sanitize(self::text($args['panelBackgroundColor']))),
            esc_attr(self::text($args['fontFamily'])),
            esc_attr(self::text($args['panelStyle'])),
            esc_attr(self::text($args['panelOwner'])),
            esc_attr(self::contentSpacing($args['contentMargin'], true)),
            esc_attr(self::oneOf($args['headingAlign'], self::HEADING_ALIGNS, '')),
            esc_attr(self::contentSpacing($args['headingMargin'], true)),
            esc_attr(PanelContent::toAttr($args['panelContent'])),
            esc_attr((string) self::serviceColumns($args['serviceColumns'])),
            esc_attr(self::boolAttr($args['showDashboardButton'])),
            esc_attr(self::text($args['dashboardButtonText'])),
            esc_attr(esc_url_raw(self::text($args['dashboardButtonUrl'])))
        );
    }

    /**
     * Builds the button's inline style declaration.
     *
     * Colours become custom properties the stylesheet reads, so the hover rule
     * can pick them up; spacing and border metrics are plain declarations that
     * override the size/variant classes side by side.
     *
     * @param array  $args  Merged settings.
     * @param string $style Resolved variant.
     * @return string
     */
    protected static function buttonStyle(array $args, string $style): string
    {
        $declarations = [];

        // Only plain hex / rgb() survives; anything else is dropped so the
        // stylesheet's own colour applies.
        //
        // `backgroundColor` is the surface's primary colour, but what it paints
        // depends on the variant: a fill for `filled`, and the accent (label,
        // and through it the border) for `outline` / `link`, which have no fill
        // to speak of. The editor UIs relabel the control to match.
        $primary       = Color::sanitize(self::text($args['backgroundColor']));
        $primary_hover = Color::sanitize(self::text($args['backgroundColorHover']));
        $text          = Color::sanitize(self::text($args['textColor']));
        $text_hover    = Color::sanitize(self::text($args['textColorHover']));
        $border        = Color::sanitize(self::text($args['borderColor']));
        $border_hover  = Color::sanitize(self::text($args['borderColorHover']));

        if ($style === 'filled') {
            $colors = [
                '--rox-btn-bg'       => $primary,
                '--rox-btn-fg'       => $text,
                '--rox-btn-bg-hover' => $primary_hover,
                '--rox-btn-fg-hover' => $text_hover,
            ];
        } else {
            // No fill of its own: the primary colour is the label, and the
            // stylesheet derives the outline's border from it.
            $colors = [
                '--rox-btn-fg'       => $primary,
                '--rox-btn-fg-hover' => $primary_hover,
            ];

            // …unless a surface paints one anyway, which is what the separate
            // fill pair is for.
            $fill       = Color::sanitize(self::text($args['fillColor']));
            $fill_hover = Color::sanitize(self::text($args['fillColorHover']));

            if ($fill !== '') {
                $colors['--rox-btn-bg'] = $fill;
                // The variant tints its fill on hover, which would wipe out a
                // colour an editor picked deliberately. Naming the resting fill
                // as the hover one keeps it in place; an explicit hover fill
                // still wins.
                $colors['--rox-btn-bg-hover'] = $fill_hover !== '' ? $fill_hover : $fill;
            } elseif ($fill_hover !== '') {
                $colors['--rox-btn-bg-hover'] = $fill_hover;
            }
        }

        // `link` draws no box, so a border colour would have nothing to paint.
        if ($style !== 'link') {
            $colors['--rox-btn-border']       = $border;
            $colors['--rox-btn-border-hover'] = $border_hover;
        }

        foreach ($colors as $property => $value) {
            if ($value !== '') {
                $declarations[] = $property . ':' . $value;
            }
        }

        // Any hover colour that actually applies replaces the stylesheet's
        // default brightness shift, which would otherwise tint the chosen
        // colour on top of itself.
        $has_hover = false;
        foreach ($colors as $property => $value) {
            if ($value !== '' && str_ends_with($property, '-hover')) {
                $has_hover = true;
            }
        }

        if ($has_hover) {
            $declarations[] = '--rox-btn-hover-filter:none';
        }

        // Spacing and border metrics travel as variables too, for the same
        // reason the colours do: the stylesheet's tablet and mobile rules read
        // them, and a plain inline declaration would outrank every media query.
        //
        // Written per side, so an editor who sets only the top keeps the size
        // class's padding on the other three.
        foreach (self::DEVICES as $suffix => $keys) {
            foreach (self::spacing($args[$keys['padding']] ?? [], false) as $side => $length) {
                $declarations[] = '--rox-btn-p' . $side[0] . $suffix . ':' . $length;
            }

            foreach (self::spacing($args[$keys['margin']] ?? [], true) as $side => $length) {
                $declarations[] = '--rox-btn-m' . $side[0] . $suffix . ':' . $length;
            }
        }

        // Width, icon size, icon gap and the icon's optical nudge. Each is a
        // plain metric, and an unset device emits nothing so it inherits the
        // one above — the same fall-through the spacing above relies on.
        //
        // The nudge allows a negative value; the other three do not.
        foreach (self::DEVICES as $suffix => $keys) {
            $metrics = [
                '--rox-btn-w'      => [$keys['width'], 2000, false],
                '--rox-btn-icon'   => [$keys['iconSize'], 200, false],
                '--rox-btn-gap'    => [$keys['iconGap'], 200, false],
                '--rox-btn-icon-y' => [$keys['iconOffsetY'], 100, true],
            ];

            foreach ($metrics as $property => [$key, $max, $signed]) {
                $value = self::metric($args[$key] ?? null, $max, false, $signed);

                if ($value !== '') {
                    $declarations[] = $property . $suffix . ':' . $value;
                }
            }
        }

        // One shadow each for resting and hover. Not per device: nobody
        // designs a different shadow for a phone, and Elementor's own shadow
        // control is not responsive either.
        $shadows = [
            '--rox-btn-shadow'       => self::shadow($args['boxShadow']),
            '--rox-btn-shadow-hover' => self::shadow($args['boxShadowHover']),
        ];

        foreach ($shadows as $property => $value) {
            if ($value !== '') {
                $declarations[] = $property . ':' . $value;
            }
        }

        // `link` is a text button — there is no box to draw around it.
        if ($style !== 'link') {
            // Border style is not offered per device; nobody dashes a border on
            // phones alone.
            $declarations[] = 'border-style:'
                . self::oneOf($args['borderStyle'], self::BORDER_STYLES, 'solid');

            foreach (self::DEVICES as $suffix => $keys) {
                // Clamped rather than trusted: a negative width is invalid CSS
                // and an absurd radius would swallow the label. An unset device
                // emits nothing, which is what makes it inherit the one above.
                $width = self::metric($args[$keys['borderWidth']] ?? null, 20, $suffix === '');
                if ($width !== '') {
                    $declarations[] = '--rox-btn-bw' . $suffix . ':' . $width;
                }

                $radius = self::metric($args[$keys['borderRadius']] ?? null, 100, $suffix === '');
                if ($radius !== '') {
                    $declarations[] = '--rox-btn-br' . $suffix . ':' . $radius;
                }
            }

            // Hover width has no per-device twin: it is the odd case of a
            // border that appears or thickens on hover, and that reads the
            // same on every screen.
            $width_hover = self::metric($args['borderWidthHover'] ?? null, 20, false);
            if ($width_hover !== '') {
                $declarations[] = '--rox-btn-bw-hover:' . $width_hover;
            }
        }

        return implode(';', $declarations);
    }

    /**
     * Builds the wrapper's inline style: the alignment, per device.
     *
     * @param array  $args  Merged settings.
     * @param string $align Resolved base alignment.
     * @return string
     */
    protected static function wrapperStyle(array $args, string $align): string
    {
        $declarations = ['--rox-btn-align:' . $align];

        foreach (['-t' => 'alignTablet', '-m' => 'alignMobile'] as $suffix => $key) {
            $value = self::text($args[$key] ?? '');

            // Only left/center/right here: `full` is a width, and an unset
            // device must stay unset so it inherits the one above.
            if ($value !== '' && in_array($value, ['left', 'center', 'right'], true)) {
                $declarations[] = '--rox-btn-align' . $suffix . ':' . $value;
            }
        }

        return implode(';', $declarations);
    }

    /**
     * Clamps a pixel metric for one device.
     *
     * @param mixed $value    Raw value; null/'' means the device is unset.
     * @param int   $max      Upper bound.
     * @param bool  $required Whether an unset value still emits (the base
     *                        device always does; the others inherit instead).
     * @return string CSS length, or '' to emit nothing.
     */
    protected static function metric($value, int $max, bool $required, bool $signed = false): string
    {
        $value = self::text($value);

        if ($value === '') {
            return $required ? '0px' : '';
        }

        // A signed metric may sit either side of zero — an icon nudged up, a
        // shadow cast to the left — so the floor mirrors the ceiling.
        $floor = $signed ? -$max : 0;

        return max($floor, min($max, (int) $value)) . 'px';
    }

    /**
     * The service-list column count, clamped to the 1-2 the panel supports.
     *
     * Accepts a Gutenberg RangeControl number or an Elementor SLIDER's
     * `{size, unit}` map; anything else lands on the 2-up default.
     *
     * @param mixed $value Raw setting.
     * @return int
     */
    public static function serviceColumns($value): int
    {
        // An Elementor SLIDER hands back `{size, unit}`; a Gutenberg RangeControl
        // hands back the number itself. Anything unusable falls to the 2-up
        // default rather than to 0.
        if (is_array($value)) {
            $value = $value['size'] ?? 2;
        }

        $columns = is_numeric($value) ? (int) $value : 2;

        return max(1, min(2, $columns ?: 2));
    }

    /**
     * Renders a four-sided spacing value as `top,right,bottom,left`.
     *
     * All four are always present, with `0` standing in for a side left
     * empty — the element this lands on carries no spacing of its own, so a
     * zero is the same as unset and the reader stays simple.
     *
     * @param mixed $value          Raw `{top,right,bottom,left}` map.
     * @param bool  $allow_negative Whether a negative length is meaningful.
     * @return string
     */
    public static function contentSpacing($value, bool $allow_negative): string
    {
        $sides = self::spacing($value, $allow_negative);

        return implode(',', [
            $sides['top'] ?? '0',
            $sides['right'] ?? '0',
            $sides['bottom'] ?? '0',
            $sides['left'] ?? '0',
        ]);
    }

    /**
     * Normalises a four-sided spacing value into the sides that were actually
     * set. Sides left empty are omitted so the stylesheet keeps its own value
     * for them.
     *
     * @param mixed $value           Raw `{top,right,bottom,left}` map.
     * @param bool  $allow_negative  Whether a negative length is meaningful
     *                               (true for margin, false for padding).
     * @return array<string, string> Side => CSS length.
     */
    protected static function spacing($value, bool $allow_negative): array
    {
        if (!is_array($value)) {
            return [];
        }

        $sides = [];

        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $length = self::length($value[$side] ?? '', $allow_negative);

            if ($length !== '') {
                $sides[$side] = $length;
            }
        }

        return $sides;
    }

    /**
     * Validates a single CSS length. The value is inlined into a `style`
     * attribute, so only a number with a known unit may pass.
     *
     * A unitless number is read as pixels rather than dropped: the spacing
     * controls can hand one back before a unit has been picked, and silently
     * discarding it looks to the editor like the control does nothing.
     *
     * @param string $value          Raw length, e.g. "12px".
     * @param bool   $allow_negative Whether a leading minus is allowed.
     * @return string Sanitised length, or '' when unusable.
     */
    protected static function length($value, bool $allow_negative): string
    {
        $value = self::text($value);

        if ($value === '') {
            return '';
        }

        $sign   = $allow_negative ? '-?' : '';
        $number = '\d+(\.\d+)?';

        // A bare "0" is a valid length and needs no unit.
        if (preg_match('/^' . $sign . $number . '$/', $value)) {
            return (float) $value === 0.0 ? '0' : $value . 'px';
        }

        $pattern = '/^' . $sign . $number . '(' . implode('|', self::UNITS) . ')$/';

        return preg_match($pattern, $value) ? $value : '';
    }

    /**
     * Returns the leading icon SVG, or '' for `none`.
     *
     * Drawn with `currentColor` so it always matches the label, whatever colour
     * the variant resolved to.
     *
     * @param string $icon Icon key.
     * @return string
     */
    protected static function icon(string $icon): string
    {
        $icons = self::icons();

        if ($icon === 'none' || !isset($icons[$icon])) {
            return '';
        }

        return '<span class="rox-booking-button__icon" aria-hidden="true">'
            // The markup comes from the plugin's own icon file, never from
            // stored settings — only the key above is editor-supplied, and it
            // has to match a key in the set to get here.
            . '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" '
            . 'stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">'
            . $icons[$icon]
            . '</svg></span>';
    }

    /**
     * Renders a `{x, y, blur, spread, color, inset}` map as a CSS shadow.
     *
     * @param mixed $value Raw shadow map.
     * @return string Empty when there is no shadow to draw.
     */
    protected static function shadow($value): string
    {
        if (!is_array($value)) {
            return '';
        }

        $color = Color::sanitize(self::text($value['color'] ?? ''));

        // Without a colour there is nothing to draw. Left to itself the
        // browser would paint the current text colour, which is never what an
        // untouched control is asking for.
        if ($color === '') {
            return '';
        }

        $lengths = [];

        // Blur is the one length that cannot be negative.
        foreach (['x', 'y', 'blur', 'spread'] as $key) {
            $lengths[] = self::metric($value[$key] ?? 0, 200, true, $key !== 'blur');
        }

        return (empty($value['inset']) ? '' : 'inset ')
            . implode(' ', $lengths) . ' ' . $color;
    }

    /**
     * The icon set, loaded once per request.
     *
     * @return array<string, string> Icon name => SVG innards.
     */
    public static function icons(): array
    {
        if (self::$icons !== null) {
            return self::$icons;
        }

        // Resolved from this file rather than a plugin constant, so the builder
        // stays usable outside a full WordPress bootstrap.
        $file = dirname(__DIR__) . '/resources/blocks/booking-panel/icons.json';
        $decoded = file_exists($file)
            ? json_decode((string) file_get_contents($file), true)
            : null;

        self::$icons = is_array($decoded) ? array_filter($decoded, 'is_string') : [];

        return self::$icons;
    }

    /**
     * Every value the icon setting accepts: `none` plus each icon in the set.
     *
     * @return string[]
     */
    public static function iconKeys(): array
    {
        return array_merge(['none'], array_keys(self::icons()));
    }

    /**
     * Reads a setting as a trimmed string.
     *
     * Casting straight to string is not safe here: the surfaces hand these
     * values over from stored post meta, and an array reaching `(string)` warns
     * while an object without `__toString()` is a fatal Error. Gutenberg's
     * block.json schema catches most of it, but an `object` attribute's inner
     * values are unvalidated and Elementor validates nothing at all, so a
     * hand-edited or migrated layout could otherwise take a page down.
     *
     * @param mixed $value Raw setting.
     * @return string Trimmed string, or '' when the value is not stringable.
     */
    protected static function text($value): string
    {
        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return trim((string) $value);
        }

        return '';
    }

    /**
     * Narrows a value to a known option, falling back when it is not one.
     *
     * @param mixed    $value    Raw value.
     * @param string[] $allowed  Allowed options.
     * @param string   $fallback Value used when $value is not allowed.
     * @return string
     */
    protected static function oneOf($value, array $allowed, string $fallback): string
    {
        $value = self::text($value);

        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    /**
     * Renders a boolean as the "true"/"false" string the view bundle compares
     * against, accepting Elementor's `yes` switcher value too.
     *
     * @param mixed $value Raw value.
     * @return string
     */
    protected static function boolAttr($value): string
    {
        if (is_string($value)) {
            $value = in_array(strtolower($value), ['yes', 'true', '1'], true);
        }

        return !empty($value) ? 'true' : 'false';
    }
}
