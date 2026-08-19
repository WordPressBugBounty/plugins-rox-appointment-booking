<?php

namespace RoxAppointmentBooking\Supports;

if (!defined('ABSPATH')) exit;

/**
 * Colour helpers for surface (block / widget / shortcode) attributes.
 *
 * @package RoxAppointmentBooking
 * @since 1.0.0
 */
class Color
{
    /**
     * Sanitises a CSS colour coming from a surface attribute, dropping anything
     * that is not a plain hex or rgb()/rgba() value. Callers hand the result to
     * the panel, which inlines it as a `style` value, so nothing else may pass.
     *
     * @param string $value Raw colour.
     * @return string Sanitised colour, or '' to fall back to the stylesheet.
     */
    public static function sanitize(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $hex = sanitize_hex_color($value);
        if (!empty($hex)) {
            return $hex;
        }

        // Colour pickers hand back rgb()/rgba() once alpha is involved. Checked
        // without a regex: the wrapper must match and the inside may only hold
        // number/separator characters.
        $is_rgb = str_starts_with($value, 'rgb(') || str_starts_with($value, 'rgba(');
        if ($is_rgb && str_ends_with($value, ')')) {
            $inner = substr($value, strpos($value, '(') + 1, -1);
            if ($inner !== '' && strspn($inner, '0123456789., %') === strlen($inner)) {
                return $value;
            }
        }

        return '';
    }
}
