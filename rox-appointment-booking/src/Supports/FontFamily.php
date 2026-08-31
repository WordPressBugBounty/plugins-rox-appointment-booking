<?php

namespace RoxAppointmentBooking\Supports;

if (!defined('ABSPATH')) exit;

/**
 * Font-family helpers for surface (block / widget / shortcode) attributes.
 *
 * Surfaces store a short key ("roboto"), never a raw CSS value, so nothing an
 * editor picks can reach the panel's inline `style` unchecked. This class owns
 * the whole list: the labels the pickers show, the CSS stack each key resolves
 * to and the Google Fonts family each one needs loading.
 *
 * @package RoxAppointmentBooking
 * @since 1.0.0
 */
class FontFamily
{
    /**
     * Key meaning "inherit the theme's font" rather than naming one.
     */
    public const INHERIT = 'inherit';

    /**
     * Handle prefix for the per-font Google Fonts stylesheets.
     */
    protected const STYLE_HANDLE_PREFIX = 'rox-appointment-booking-font-';

    /**
     * Fonts the panel stylesheet already imports from Google Fonts itself.
     * Picking one of these needs no extra request.
     *
     * @var string[]
     */
    protected const BUNDLED = ['heebo', 'inter'];

    /**
     * Every font a surface may pick, keyed by the value stored in the
     * attribute. `stack` is the CSS the panel applies; `google` is the
     * `family=` parameter needed to load it, or null for a font that is
     * already on the visitor's machine.
     *
     * @return array<string, array{label: string, stack: string, google: ?string}>
     */
    public static function fonts(): array
    {
        return [
            self::INHERIT => [
                'label'  => __('Theme font (inherit)', 'rox-appointment-booking'),
                'stack'  => 'inherit',
                'google' => null,
            ],
            'system' => [
                'label'  => __('System UI', 'rox-appointment-booking'),
                'stack'  => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
                'google' => null,
            ],
            'arial' => [
                'label'  => __('Arial', 'rox-appointment-booking'),
                'stack'  => 'Arial, Helvetica, sans-serif',
                'google' => null,
            ],
            'georgia' => [
                'label'  => __('Georgia', 'rox-appointment-booking'),
                'stack'  => 'Georgia, "Times New Roman", serif',
                'google' => null,
            ],
            'times' => [
                'label'  => __('Times New Roman', 'rox-appointment-booking'),
                'stack'  => '"Times New Roman", Times, serif',
                'google' => null,
            ],
            'verdana' => [
                'label'  => __('Verdana', 'rox-appointment-booking'),
                'stack'  => 'Verdana, Geneva, sans-serif',
                'google' => null,
            ],
            'heebo' => [
                'label'  => __('Heebo', 'rox-appointment-booking'),
                'stack'  => '"Heebo", sans-serif',
                'google' => 'Heebo:wght@100..900',
            ],
            'inter' => [
                'label'  => __('Inter', 'rox-appointment-booking'),
                'stack'  => '"Inter", sans-serif',
                'google' => 'Inter:wght@100..900',
            ],
            'roboto' => [
                'label'  => __('Roboto', 'rox-appointment-booking'),
                'stack'  => '"Roboto", sans-serif',
                'google' => 'Roboto:wght@300;400;500;700',
            ],
            'open-sans' => [
                'label'  => __('Open Sans', 'rox-appointment-booking'),
                'stack'  => '"Open Sans", sans-serif',
                'google' => 'Open+Sans:wght@300..800',
            ],
            'lato' => [
                'label'  => __('Lato', 'rox-appointment-booking'),
                'stack'  => '"Lato", sans-serif',
                'google' => 'Lato:wght@300;400;700',
            ],
            'montserrat' => [
                'label'  => __('Montserrat', 'rox-appointment-booking'),
                'stack'  => '"Montserrat", sans-serif',
                'google' => 'Montserrat:wght@100..900',
            ],
            'poppins' => [
                'label'  => __('Poppins', 'rox-appointment-booking'),
                'stack'  => '"Poppins", sans-serif',
                'google' => 'Poppins:wght@300;400;500;600;700',
            ],
            'nunito' => [
                'label'  => __('Nunito', 'rox-appointment-booking'),
                'stack'  => '"Nunito", sans-serif',
                'google' => 'Nunito:wght@200..1000',
            ],
            'raleway' => [
                'label'  => __('Raleway', 'rox-appointment-booking'),
                'stack'  => '"Raleway", sans-serif',
                'google' => 'Raleway:wght@100..900',
            ],
            'rubik' => [
                'label'  => __('Rubik', 'rox-appointment-booking'),
                'stack'  => '"Rubik", sans-serif',
                'google' => 'Rubik:wght@300..900',
            ],
            'work-sans' => [
                'label'  => __('Work Sans', 'rox-appointment-booking'),
                'stack'  => '"Work Sans", sans-serif',
                'google' => 'Work+Sans:wght@100..900',
            ],
            'dm-sans' => [
                'label'  => __('DM Sans', 'rox-appointment-booking'),
                'stack'  => '"DM Sans", sans-serif',
                'google' => 'DM+Sans:wght@100..1000',
            ],
            'manrope' => [
                'label'  => __('Manrope', 'rox-appointment-booking'),
                'stack'  => '"Manrope", sans-serif',
                'google' => 'Manrope:wght@200..800',
            ],
            'figtree' => [
                'label'  => __('Figtree', 'rox-appointment-booking'),
                'stack'  => '"Figtree", sans-serif',
                'google' => 'Figtree:wght@300..900',
            ],
            'mulish' => [
                'label'  => __('Mulish', 'rox-appointment-booking'),
                'stack'  => '"Mulish", sans-serif',
                'google' => 'Mulish:wght@200..1000',
            ],
            'karla' => [
                'label'  => __('Karla', 'rox-appointment-booking'),
                'stack'  => '"Karla", sans-serif',
                'google' => 'Karla:wght@200..800',
            ],
            'quicksand' => [
                'label'  => __('Quicksand', 'rox-appointment-booking'),
                'stack'  => '"Quicksand", sans-serif',
                'google' => 'Quicksand:wght@300..700',
            ],
            'oswald' => [
                'label'  => __('Oswald', 'rox-appointment-booking'),
                'stack'  => '"Oswald", sans-serif',
                'google' => 'Oswald:wght@200..700',
            ],
            'source-sans-3' => [
                'label'  => __('Source Sans 3', 'rox-appointment-booking'),
                'stack'  => '"Source Sans 3", sans-serif',
                'google' => 'Source+Sans+3:wght@200..900',
            ],
            'playfair-display' => [
                'label'  => __('Playfair Display', 'rox-appointment-booking'),
                'stack'  => '"Playfair Display", serif',
                'google' => 'Playfair+Display:wght@400..900',
            ],
            'merriweather' => [
                'label'  => __('Merriweather', 'rox-appointment-booking'),
                'stack'  => '"Merriweather", serif',
                'google' => 'Merriweather:wght@300;400;700;900',
            ],
        ];
    }

    /**
     * The list every picker renders: the plugin default first, then each font.
     * Shared by the block editor (printed as JSON) and the Elementor control,
     * so the two surfaces cannot drift apart.
     *
     * @return array<int, array{value: string, label: string, stack: string, google: ?string}>
     */
    public static function options(): array
    {
        $options = [[
            'value'  => '',
            'label'  => __('Default (Heebo)', 'rox-appointment-booking'),
            'stack'  => '',
            'google' => null,
        ]];

        foreach (self::fonts() as $value => $font) {
            $options[] = array_merge(['value' => $value], $font);
        }

        return $options;
    }

    /**
     * `key => label` map for a plain select control (Elementor).
     *
     * @return array<string, string>
     */
    public static function selectOptions(): array
    {
        $map = [];

        foreach (self::options() as $option) {
            $map[$option['value']] = $option['label'];
        }

        return $map;
    }

    /**
     * Sanitises a font key coming from a surface attribute. Anything not on the
     * list above becomes '', which leaves the panel on its stylesheet default.
     *
     * @param string $value Raw font key.
     * @return string
     */
    public static function sanitize(string $value): string
    {
        $value = sanitize_key(trim($value));

        return isset(self::fonts()[$value]) ? $value : '';
    }

    /**
     * Resolves a font key to the CSS stack the panel applies. An unknown or
     * empty key resolves to '', which the panel reads as "keep the default".
     *
     * @param string $value Raw font key.
     * @return string
     */
    public static function stack(string $value): string
    {
        $key = self::sanitize($value);

        return $key === '' ? '' : self::fonts()[$key]['stack'];
    }

    /**
     * The Google Fonts stylesheet a key needs, or '' when it needs none — the
     * default, the theme's own font, a locally installed face, or one of the
     * two the panel stylesheet already imports.
     *
     * @param string $value Raw font key.
     * @return string
     */
    public static function googleUrl(string $value): string
    {
        $key = self::sanitize($value);

        if ($key === '' || in_array($key, self::BUNDLED, true)) {
            return '';
        }

        $family = self::fonts()[$key]['google'] ?? null;

        return $family
            ? 'https://fonts.googleapis.com/css2?family=' . $family . '&display=swap'
            : '';
    }

    /**
     * Loads the web font a key needs, if any. Called while a surface renders,
     * which is past wp_head, so the stylesheet lands in the footer —
     * `display=swap` keeps the panel readable until the face arrives.
     *
     * @param string $value Raw font key.
     * @return void
     */
    public static function enqueue(string $value): void
    {
        $url = self::googleUrl($value);

        if ($url === '') {
            return;
        }

        $handle = self::STYLE_HANDLE_PREFIX . self::sanitize($value);

        if (!wp_style_is($handle, 'registered')) {
            wp_register_style($handle, $url, [], null);
        }

        wp_enqueue_style($handle);
    }
}
