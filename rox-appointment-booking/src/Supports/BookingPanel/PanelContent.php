<?php

namespace RoxAppointmentBooking\Supports\BookingPanel;

if (!defined('ABSPATH')) exit;

/**
 * Panel copy overrides for surface (block / widget) attributes.
 *
 * The booking panel's wording lives in its React components. The surfaces that
 * embed the panel let an editor rewrite any of it, and every surface stores
 * that choice its own way — the block as an object attribute, Elementor as flat
 * control values — so all of them normalise through here before the value
 * reaches the panel as one JSON `data-panel-content` attribute.
 *
 * Mirrors the JS `resources/lib/panelContent.js`.
 *
 * @package RoxAppointmentBooking
 * @since 1.0.0
 */
class PanelContent
{
    /**
     * Every override an editor can set, in the order the panel shows them.
     *
     * An explicit list rather than "whatever the attribute holds": the value is
     * whatever was saved in the post, so only keys named here are ever read.
     */
    public const KEYS = [
        // Step 1's sidebar: the illustration, its title and subtitle, and the
        // help box under them.
        'sidebarImage',
        'sidebarImageWidth',
        'sidebarTitle',
        'sidebarSubtitle',
        'helpTitle',
        'helpButtonText',
        'helpButtonUrl',
        'helpNote',
        // The heading above each step's cards.
        'locationHeading',
        'categoryHeading',
        'servicesHeading',
        'agentsHeading',
        'dateTimeHeading',
        'informationHeading',
        // The left step list, from the second step onward.
        'stepLocationLabel',
        'stepCategoryLabel',
        'stepServicesLabel',
        'stepAgentsLabel',
        'stepDateTimeLabel',
        'stepInformationLabel',
        'stepPaymentLabel',
        'stepCompleteLabel',
    ];

    /**
     * Keys holding a URL rather than a label, sanitised as one.
     */
    protected const URL_KEYS = ['sidebarImage', 'helpButtonUrl'];

    /**
     * Keys holding a pixel length rather than a label, with the bounds it is
     * clamped to and the width the panel ships with.
     *
     * Public because a surface builds its own control from these numbers — the
     * slider an editor drags and the clamp below then agree by construction.
     *
     * Mirrors the JS `SIDEBAR_IMAGE_WIDTH`.
     */
    public const INT_RANGES = [
        'sidebarImageWidth' => ['min' => 40, 'max' => 200, 'default' => 118],
    ];

    /**
     * Normalises the overrides coming from a surface attribute.
     *
     * Anything unrecognised, non-scalar or blank is dropped, so a missing key
     * and a key cleared back to empty both mean the same thing: keep the
     * panel's own wording.
     *
     * @param mixed $value Raw overrides.
     * @return array Overrides, keyed by KEYS.
     */
    public static function parse($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $content = [];

        foreach (self::KEYS as $key) {
            if (!isset($value[$key]) || !is_scalar($value[$key])) {
                continue;
            }

            // A length is clamped to its range rather than dropped, so a value
            // from a hand-edited post cannot size the illustration off the
            // sidebar. Anything that is not a positive number is "unset".
            if (isset(self::INT_RANGES[$key])) {
                $range = self::INT_RANGES[$key];
                $width = (int) $value[$key];

                if ($width > 0) {
                    $content[$key] = max($range['min'], min($range['max'], $width));
                }

                continue;
            }

            $text = in_array($key, self::URL_KEYS, true)
                ? esc_url_raw(trim((string) $value[$key]))
                : sanitize_text_field((string) $value[$key]);

            if ($text !== '') {
                $content[$key] = $text;
            }
        }

        return $content;
    }

    /**
     * Renders the overrides as the JSON string the booking panel reads from its
     * `data-panel-content` attribute. Nothing set means an empty attribute,
     * which tells the panel to keep every default.
     *
     * @param mixed $value Raw overrides.
     * @return string
     */
    public static function toAttr($value): string
    {
        $content = self::parse($value);

        return $content === [] ? '' : (string) wp_json_encode($content);
    }
}
