<?php

/**
 * Locale utility functions for Booking Engine
 *
 * The React bundles ship third-party date UIs (antd's pickers, FullCalendar,
 * Day.js) that default to English because their locale data lives inside the
 * shared `vendors` chunk. Rather than bundling every library's locale files,
 * the calendars are driven by WordPress's own translations: this file hands the
 * active WP_Locale month/weekday/meridiem tables to JavaScript, where
 * `resources/lib/locale.js` registers them as a Day.js locale and feeds them to
 * antd and FullCalendar.
 *
 * @package RoxAppointmentBooking
 * @subpackage Functions
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Convert a WordPress locale to a BCP 47 language tag.
 *
 * WordPress uses underscores and optional variant suffixes (`de_DE_formal`,
 * `es_419`, `ckb`); `Intl` and FullCalendar need hyphens and no variant, so
 * `de_DE_formal` becomes `de-DE` and a bare `ckb` is passed straight through.
 *
 * @param string $locale WordPress locale, e.g. 'de_DE_formal'.
 * @return string BCP 47 tag, e.g. 'de-DE'.
 * @since 1.0.0
 */
if (!function_exists('rox_appointment_booking_locale_to_bcp47')) {
    function rox_appointment_booking_locale_to_bcp47(string $locale): string
    {
        $parts = preg_split('/[_-]/', $locale) ?: [];
        $parts = array_values(array_filter($parts, static function ($part) {
            // Drop WordPress's formality variants, which are not valid subtags.
            return $part !== '' && !in_array(strtolower($part), ['formal', 'informal'], true);
        }));

        if (empty($parts)) {
            return 'en';
        }

        $tag = strtolower($parts[0]);

        // Only a 2-letter region or a 3-digit UN M.49 code is a valid region
        // subtag; anything else (a script, a stray variant) is dropped so the
        // tag can never make Intl throw a RangeError in the browser.
        if (isset($parts[1])) {
            if (preg_match('/^[A-Za-z]{2}$/', $parts[1])) {
                $tag .= '-' . strtoupper($parts[1]);
            } elseif (preg_match('/^[0-9]{3}$/', $parts[1])) {
                $tag .= '-' . $parts[1];
            }
        }

        return $tag;
    }
}

/**
 * Build the locale payload handed to the React bundles.
 *
 * Everything here comes from WordPress itself, so a site running a language
 * pack gets translated month and weekday names in every calendar without the
 * plugin shipping per-library locale files.
 *
 * @return array{
 *     locale:string, language:string, bcp47:string, direction:string,
 *     startOfWeek:int, months:string[], monthsShort:string[], weekdays:string[],
 *     weekdaysShort:string[], weekdaysMin:string[], meridiem:array,
 *     dateFormat:string, timeFormat:string, timezone:string
 * }
 * @since 1.0.0
 */
if (!function_exists('rox_appointment_booking_js_locale')) {
    function rox_appointment_booking_js_locale(): array
    {
        global $wp_locale;

        $locale = determine_locale();
        $bcp47  = rox_appointment_booking_locale_to_bcp47($locale);

        $months        = [];
        $months_short  = [];
        $weekdays      = [];
        $weekdays_abbr = [];
        $weekdays_min  = [];
        $meridiem      = ['am' => 'am', 'pm' => 'pm', 'AM' => 'AM', 'PM' => 'PM'];

        // $wp_locale is set up on `init`; guard so an early call still returns a
        // usable payload rather than a fatal.
        if ($wp_locale instanceof WP_Locale) {
            // WP_Locale::$month is keyed '01'..'12' and $weekday 0..6 (Sunday
            // first) — the same order Day.js and antd expect.
            $months = array_values($wp_locale->month);

            foreach ($months as $month) {
                $months_short[] = $wp_locale->get_month_abbrev($month);
            }

            $weekdays = array_values($wp_locale->weekday);

            foreach ($weekdays as $weekday) {
                $weekdays_abbr[] = $wp_locale->get_weekday_abbrev($weekday);
                $weekdays_min[]  = $wp_locale->get_weekday_initial($weekday);
            }

            foreach (array_keys($meridiem) as $key) {
                $value = $wp_locale->get_meridiem($key);
                if (!empty($value)) {
                    $meridiem[$key] = $value;
                }
            }
        }

        return apply_filters('rox_appointment_booking_js_locale', [
            'locale'        => $locale,
            'language'      => strtolower(strtok($locale, '_-') ?: 'en'),
            'bcp47'         => $bcp47,
            'direction'     => is_rtl() ? 'rtl' : 'ltr',
            // get_option('start_of_week') is 0 (Sunday) to 6 (Saturday).
            'startOfWeek'   => (int) get_option('start_of_week', 0),
            'months'        => $months,
            'monthsShort'   => $months_short,
            'weekdays'      => $weekdays,
            'weekdaysShort' => $weekdays_abbr,
            'weekdaysMin'   => $weekdays_min,
            'meridiem'      => $meridiem,
            'dateFormat'    => get_option('date_format') ?: 'F j, Y',
            'timeFormat'    => get_option('time_format') ?: 'g:i a',
            'timezone'      => wp_timezone_string(),
        ]);
    }
}
