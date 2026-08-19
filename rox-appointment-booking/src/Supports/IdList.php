<?php

namespace RoxAppointmentBooking\Supports;

if (!defined('ABSPATH')) exit;

/**
 * ID-list helpers for surface (block / widget / shortcode) attributes.
 *
 * The booking panel surfaces let an editor restrict which locations and which
 * categories the visitor may pick from. Every surface stores that choice
 * differently — the block as a JSON array of numbers, Elementor as an array of
 * option keys (strings), the rendered markup as a comma-separated `data-*`
 * string — so all of them normalise through here before the value is trusted.
 *
 * @package RoxAppointmentBooking
 * @since 1.0.0
 */
class IdList
{
    /**
     * Normalises an id list coming from a surface attribute or a REST param
     * into a list of unique positive integers. Accepts an array or a
     * comma-separated string; anything that is not a positive integer is
     * dropped rather than coerced to 0.
     *
     * @param mixed $value Raw id list.
     * @return int[] Unique positive ids, in the order given.
     */
    public static function parse($value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        $ids = [];

        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            // Cast rather than absint(): a negative id is nonsense here and must
            // be dropped, not silently flipped into a real row's id.
            $id = (int) $item;

            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Renders an id list as the comma-separated string the booking panel reads
     * from a `data-*` attribute. An empty list means "no restriction", which is
     * exactly what an empty attribute value tells the panel.
     *
     * @param mixed $value Raw id list.
     * @return string
     */
    public static function toAttr($value): string
    {
        return implode(',', self::parse($value));
    }
}
