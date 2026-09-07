<?php

namespace RoxAppointmentBooking\Supports\BookingPanel;

use RoxAppointmentBooking\Supports\Color;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Style builder for the booking panel's Back / Next navigation buttons.
 *
 * The buttons are rendered by React deep inside the panel, so no inline style
 * written by a surface can reach them, and an inline style cannot express
 * `:hover` in the first place. Everything therefore travels as CSS custom
 * properties set on the panel's mount node: the panel stylesheet owns every
 * rule and reads the variables with the current design as its fallback, so a
 * control the editor never touched changes nothing.
 *
 * The Gutenberg block builds its declarations here. The Elementor widget writes
 * the same variable names through its own `selectors`, because only Elementor's
 * generated CSS can carry its responsive per-breakpoint values; it still reads
 * FONT_WEIGHTS from here so the two surfaces offer one list.
 *
 * @package RoxAppointmentBooking
 * @subpackage Supports
 * @since 1.0.0
 */
class NavButtons
{
    /**
     * Border styles the surfaces offer.
     */
    public const BORDER_STYLES = ['none', 'solid', 'dashed', 'dotted', 'double'];

    /**
     * Font weights the surfaces offer.
     */
    public const FONT_WEIGHTS = ['300', '400', '500', '600', '700', '800'];

    /**
     * CSS length units a spacing value may carry.
     */
    protected const UNITS = ['px', 'em', 'rem', '%'];

    /**
     * The two buttons, mapped to the argument-name prefix each one reads.
     * The key doubles as the variable-name segment (`--rox-nav-back-bg`).
     */
    protected const BUTTONS = [
        'back' => 'navBack',
        'next' => 'navNext',
    ];

    /**
     * Builds the custom-property declarations for both buttons.
     *
     * The navigation row's own height is not worked out here: the stylesheet
     * derives it from these same variables, which is the only place that can
     * still be right once Elementor's responsive controls are in play.
     *
     * @param array $args Surface settings; see BUTTONS for the key prefixes.
     * @return string Semicolon-separated declarations, or '' when nothing is set.
     */
    public static function cssVars(array $args): string
    {
        $declarations = [];

        foreach (self::BUTTONS as $key => $prefix) {
            foreach (self::buttonVars($key, $prefix, $args) as $property => $value) {
                $declarations[] = $property . ':' . $value;
            }
        }

        return implode(';', $declarations);
    }

    /**
     * Builds one button's variables. Anything unset or invalid is omitted
     * rather than emitted empty, which is what leaves the stylesheet's own
     * value in force.
     *
     * @param string $key    Variable segment (`back` / `next`).
     * @param string $prefix Argument-name prefix.
     * @param array  $args   Surface settings.
     * @return array<string, string> Property => value.
     */
    protected static function buttonVars(string $key, string $prefix, array $args): array
    {
        $base = '--rox-nav-' . $key . '-';
        $out  = [];

        $colors = [
            'bg'           => 'BgColor',
            'fg'           => 'TextColor',
            'border'       => 'BorderColor',
            'bg-hover'     => 'BgColorHover',
            'fg-hover'     => 'TextColorHover',
            'border-hover' => 'BorderColorHover',
        ];

        foreach ($colors as $suffix => $attribute) {
            // Only plain hex / rgb() survives; the value is inlined into a
            // `style` attribute.
            $color = Color::sanitize(self::text($args[$prefix . $attribute] ?? ''));

            if ($color !== '') {
                $out[$base . $suffix] = $color;
            }
        }

        // Written per side, so an editor who sets only the top keeps the
        // stylesheet's padding on the other three.
        foreach (self::spacing($args[$prefix . 'Padding'] ?? [], false) as $side => $length) {
            $out[$base . 'p' . $side[0]] = $length;
        }

        foreach (self::spacing($args[$prefix . 'Margin'] ?? [], true) as $side => $length) {
            $out[$base . 'm' . $side[0]] = $length;
        }

        $border_style = self::text($args[$prefix . 'BorderStyle'] ?? '');
        if (in_array($border_style, self::BORDER_STYLES, true)) {
            $out[$base . 'bs'] = $border_style;
        }

        // Clamped rather than trusted: a negative width is invalid CSS and an
        // absurd radius or size would swallow the label.
        $border_width = self::metric($args[$prefix . 'BorderWidth'] ?? null, 20);
        if ($border_width !== '') {
            $out[$base . 'bw'] = $border_width;
        }

        $radius = self::metric($args[$prefix . 'BorderRadius'] ?? null, 100);
        if ($radius !== '') {
            $out[$base . 'br'] = $radius;
        }

        $font_size = self::metric($args[$prefix . 'FontSize'] ?? null, 60);
        if ($font_size !== '') {
            $out[$base . 'fs'] = $font_size;
        }

        $font_weight = self::text($args[$prefix . 'FontWeight'] ?? '');
        if (in_array($font_weight, self::FONT_WEIGHTS, true)) {
            $out[$base . 'fw'] = $font_weight;
        }

        return $out;
    }

    /**
     * Renders a clamped pixel metric, or '' when the setting is unset.
     *
     * @param mixed $value Raw value.
     * @param int   $max   Upper bound.
     * @return string
     */
    protected static function metric($value, int $max): string
    {
        $value = self::text($value);

        if ($value === '') {
            return '';
        }

        return max(0, min($max, (int) $value)) . 'px';
    }

    /**
     * Normalises a four-sided spacing value into the sides that were actually
     * set. Sides left empty are omitted so the stylesheet keeps its own value.
     *
     * @param mixed $value          Raw `{top,right,bottom,left}` map.
     * @param bool  $allow_negative Whether a negative length is meaningful.
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
     * controls can hand one back before a unit has been picked.
     *
     * @param mixed $value          Raw length, e.g. "12px".
     * @param bool  $allow_negative Whether a leading minus is allowed.
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
     * Reads a setting as a trimmed string.
     *
     * Casting straight to string is not safe here: an `object` attribute's
     * inner values are unvalidated by block.json and Elementor validates
     * nothing at all, so a hand-edited layout could otherwise take a page down.
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
}
