<?php

/**
 * Timezone helpers for Booking Engine
 *
 * The booking panel can let a customer read the available slots on their own
 * clock instead of the site's. The list of zones it offers is built here, in
 * PHP, rather than from the browser's `Intl.supportedValuesOf('timeZone')`:
 * whatever the customer picks travels back to PHP (it is stored on the booking
 * row and read by the e-mail placeholders), so sourcing the list here
 * guarantees every value we offer is one `new DateTimeZone()` can construct.
 *
 * @package RoxAppointmentBooking
 * @subpackage Functions
 * @since 1.3.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Whether a string is a timezone identifier this site can use.
 *
 * @param mixed $timezone Candidate identifier.
 * @return bool
 * @since 1.3.0
 */
if (!function_exists('rox_appointment_booking_is_valid_timezone')) {
    function rox_appointment_booking_is_valid_timezone($timezone): bool
    {
        return is_string($timezone)
            && $timezone !== ''
            && in_array($timezone, timezone_identifiers_list(), true);
    }
}

/**
 * Format a datetime this plugin stored as site-local wall time.
 *
 * Booking `date`, `start_time` and `end_time` are wall clock on the site's own
 * timezone — not UTC. WordPress sets PHP's default timezone to UTC, so
 * `strtotime()` reads such a string as a UTC instant, and `wp_date()` then
 * renders that instant back in the site's zone: the pair shifts the value by
 * the site's own offset (six hours on an Asia/Dhaka site, and a whole day
 * either way for a date-only value on a site west of UTC). Anchoring the
 * string to the site timezone first makes the round trip a no-op.
 *
 * Not for columns written with `gmdate()` — `payment.payment_time` really is
 * UTC, and `wp_date(strtotime(...))` is already right for those.
 *
 * @param string $format WordPress date format.
 * @param string|null $datetime Stored value, `Y-m-d` or `Y-m-d H:i:s`.
 * @return string Formatted value, or an empty string when unparseable.
 * @since 1.3.0
 */
if (!function_exists('rox_appointment_booking_format_site_datetime')) {
    function rox_appointment_booking_format_site_datetime(string $format, ?string $datetime): string
    {
        $datetime = trim((string) $datetime);

        if ($datetime === '') {
            return '';
        }

        try {
            $at = new DateTime($datetime, wp_timezone());
        } catch (Exception $e) {
            return '';
        }

        return wp_date($format, $at->getTimestamp());
    }
}

/**
 * The timezone list the booking panel's selector renders.
 *
 * One flat list, ordered by region and then by name — so the zones of a region
 * still sit together, but the dropdown renders as a plain list rather than a
 * headed one. Each label carries the zone's offset *right now*: the offset
 * moves with DST, so it is a snapshot for reading, never a figure to
 * calculate with.
 *
 * @return array<int, array{value:string, label:string}>
 * @since 1.3.0
 */
if (!function_exists('rox_appointment_booking_timezone_choices')) {
    function rox_appointment_booking_timezone_choices(): array
    {
        static $choices = null;

        if ($choices !== null) {
            return $choices;
        }

        $now     = new DateTime('now', new DateTimeZone('UTC'));
        $grouped = [];

        foreach (timezone_identifiers_list() as $identifier) {
            $parts  = explode('/', $identifier);
            // A zone with no region ("UTC") is its own group.
            $region = count($parts) > 1 ? array_shift($parts) : $identifier;
            $name   = str_replace('_', ' ', implode('/', $parts));

            $offset  = (new DateTimeZone($identifier))->getOffset($now);
            $sign    = $offset < 0 ? '-' : '+';
            $offset  = abs($offset);
            $label   = sprintf(
                '%s (GMT%s%02d:%02d)',
                $name,
                $sign,
                intdiv($offset, HOUR_IN_SECONDS),
                intdiv($offset % HOUR_IN_SECONDS, MINUTE_IN_SECONDS)
            );

            $grouped[$region][] = [
                'value' => $identifier,
                'label' => $label,
            ];
        }

        ksort($grouped);

        $choices = [];

        foreach ($grouped as $zones) {
            usort($zones, static function ($a, $b) {
                return strcmp($a['label'], $b['label']);
            });

            // Appended rather than nested: the region only decides the order,
            // it is not drawn as a heading.
            $choices = array_merge($choices, $zones);
        }

        return $choices;
    }
}
