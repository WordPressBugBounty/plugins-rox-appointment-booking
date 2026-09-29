<?php

namespace RoxAppointmentBooking\Supports;

if (!defined('ABSPATH')) exit;

/**
 * The booking panel's design variants.
 *
 * Every surface that can show the panel — the block, the Elementor widget, the
 * shortcode and the popup trigger — offers the same choice, and all of them
 * hand the answer to the frontend bundle as `data-style-variant` on the mount
 * node. That bundle keeps a component per variant, so this class is the one
 * place the list of names lives on the PHP side.
 *
 * Adding a third design means adding its name here and its component in
 * `src/resources/frontend/styles/`; nothing in between needs to know.
 *
 * @package RoxAppointmentBooking
 * @subpackage Supports
 * @since 1.0.0
 */
class PanelStyle
{
    /**
     * The variants a surface may ask for.
     *
     * @var string[]
     */
    public const STYLES = ['style-1', 'style-2'];

    /**
     * What a surface renders when nobody has picked.
     *
     * Change this and every surface follows — the block's saved default in
     * block.json is the one copy that has to be kept in step by hand.
     */
    public const DEFAULT_STYLE = 'style-1';

    /**
     * Whether a variant draws the grey frame when nobody has chosen.
     *
     * The frame is one control shared by every design, but its default is not:
     * Style 1 has always framed the panel, and Style 2's design sits flat on
     * the page. Mirrors PANEL_STYLE_BACKGROUND in lib/panelStyle.js.
     *
     * @var array<string, bool>
     */
    public const BACKGROUND_DEFAULTS = [
        'style-1' => true,
        'style-2' => false,
    ];

    /**
     * Normalises a stored value to a known variant.
     *
     * Surface settings are whatever was saved in a post, so an unknown name
     * falls back to the default rather than reaching the frontend, where it
     * would resolve to no component at all.
     *
     * @param mixed  $value    Raw setting.
     * @param string $fallback Variant to use when the value is not one of ours.
     * @return string
     */
    public static function sanitize($value, string $fallback = self::DEFAULT_STYLE): string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return in_array($value, self::STYLES, true) ? $value : $fallback;
    }

    /**
     * Shared controls a variant's design has nowhere to apply.
     *
     * A variant lists only what it cannot use, so a control reaches every
     * design until one opts out — and a surface hides the ones it opts out of
     * rather than offering a control that does nothing. Mirrors the `supports`
     * object on each editor style module under
     * `src/resources/blocks/booking-panel/app/`.
     *
     * @var array<string, array<string, bool>>
     */
    public const SUPPORTS = [
        'style-2' => [
            // Style 2 is a step rail and one content column: no right-hand
            // info panel, and a first step that draws cards rather than an
            // illustrated sidebar.
            'hideInfo'                       => false,
            'firstStepSidebar'               => false,
            // Style 1 puts the chosen category's name in front of the Services
            // heading, so its field rewrites only the word after it. Style 2's
            // Services step carries no category name.
            'servicesHeadingFollowsCategory' => false,
        ],
    ];

    /**
     * Whether a variant has somewhere to apply one of the shared controls.
     *
     * @param string $style_variant Variant name.
     * @param string $key           Control name.
     * @return bool
     */
    public static function supports(string $style_variant, string $key): bool
    {
        return (self::SUPPORTS[self::sanitize($style_variant)][$key] ?? true) !== false;
    }

    /**
     * The variants that can apply a control, as an Elementor `condition` value.
     *
     * Elementor reads an array as "is one of", so a control given this shows
     * only while a design that can use it is picked.
     *
     * @param string $key Control name.
     * @return string[]
     */
    public static function stylesSupporting(string $key): array
    {
        return array_values(array_filter(
            self::STYLES,
            static fn(string $style): bool => self::supports($style, $key)
        ));
    }

    /**
     * Whether the grey frame is drawn, for a setting that may not have been
     * chosen.
     *
     * The block attribute is tri-state: null (or absent) is "nobody chose",
     * which resolves to the variant's own default; an explicit true / false is
     * the editor's answer and is honoured whichever design is showing.
     *
     * @param mixed  $value         Raw setting.
     * @param string $style_variant Variant name.
     * @return bool
     */
    public static function showsBackground($value, string $style_variant): bool
    {
        if ($value === null) {
            return self::BACKGROUND_DEFAULTS[self::sanitize($style_variant)];
        }

        return (bool) $value;
    }

    /**
     * The variants as a value => label map, for a surface that renders a select.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'style-1' => esc_html__('Style 1', 'rox-appointment-booking'),
            'style-2' => esc_html__('Style 2', 'rox-appointment-booking'),
        ];
    }
}
