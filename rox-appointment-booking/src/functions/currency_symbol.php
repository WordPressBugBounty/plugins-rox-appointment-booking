<?php

/**
 * Currency utility functions for Booking Engine
 *
 * @package RoxAppointmentBooking
 * @subpackage Functions
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Get currency symbol for a given currency code
 *
 * @param string $currencyCode ISO 4217 currency code (e.g., 'USD', 'EUR', 'BDT')
 * @return string Currency symbol or currency code if symbol not found
 * @since 1.0.0
 */
if (!function_exists('rox_appointment_booking__get_currency_symbol')) {
    function rox_appointment_booking__get_currency_symbol($currencyCode)
    {
        $symbols = [
            'AED' => 'د.إ', 'AFN' => '؋', 'ALL' => 'L', 'AMD' => '֏', 'ANG' => 'ƒ', 'AOA' => 'Kz', 'ARS' => '$', 'AUD' => 'A$', 'AWG' => 'ƒ', 'AZN' => '₼',
            'BAM' => 'KM', 'BBD' => '$', 'BDT' => '৳', 'BGN' => 'лв', 'BHD' => '.د.ب', 'BIF' => 'Fr', 'BMD' => '$', 'BND' => '$', 'BOB' => 'Bs.', 'BRL' => 'R$',
            'BSD' => '$', 'BWP' => 'P', 'BYN' => 'Br', 'BZD' => '$', 'CAD' => 'C$', 'CDF' => 'Fr', 'CHF' => 'Fr', 'CLP' => '$', 'CNY' => '¥', 'COP' => '$',
            'CRC' => '₡', 'CVE' => '$', 'CZK' => 'Kč', 'DJF' => 'Fr', 'DKK' => 'kr', 'DOP' => '$', 'DZD' => 'د.ج', 'EGP' => '£', 'ETB' => 'Br', 'EUR' => '€',
            'FJD' => '$', 'FKP' => '£', 'GBP' => '£', 'GEL' => '₾', 'GIP' => '£', 'GMD' => 'D', 'GNF' => 'Fr', 'GTQ' => 'Q', 'GYD' => '$', 'HKD' => 'HK$',
            'HNL' => 'L', 'HRK' => 'kn', 'HTG' => 'G', 'HUF' => 'Ft', 'IDR' => 'Rp', 'ILS' => '₪', 'INR' => '₹', 'ISK' => 'kr', 'JMD' => '$', 'JOD' => 'د.ا',
            'JPY' => '¥', 'KES' => 'Sh', 'KGS' => 'с', 'KHR' => '៛', 'KMF' => 'Fr', 'KRW' => '₩', 'KWD' => 'د.ك', 'KYD' => '$', 'KZT' => '₸', 'LAK' => '₭',
            'LBP' => 'ل.ل', 'LKR' => 'Rs', 'LRD' => '$', 'LSL' => 'L', 'MAD' => 'د.م.', 'MDL' => 'L', 'MGA' => 'Ar', 'MKD' => 'ден', 'MMK' => 'K', 'MNT' => '₮',
            'MOP' => 'P', 'MUR' => '₨', 'MVR' => 'ރ.', 'MWK' => 'MK', 'MXN' => '$', 'MYR' => 'RM', 'MZN' => 'MT', 'NAD' => '$', 'NGN' => '₦', 'NIO' => 'C$',
            'NOK' => 'kr', 'NPR' => 'Rs', 'NZD' => 'NZ$', 'OMR' => 'ر.ع.', 'PAB' => 'B/.', 'PEN' => 'S/', 'PGK' => 'K', 'PHP' => '₱', 'PKR' => '₨', 'PLN' => 'zł',
            'PYG' => '₲', 'QAR' => 'ر.ق', 'RON' => 'lei', 'RSD' => 'дин', 'RUB' => '₽', 'RWF' => 'Fr', 'SAR' => 'ر.س', 'SBD' => '$', 'SCR' => '₨', 'SEK' => 'kr',
            'SGD' => 'S$', 'SHP' => '£', 'SLE' => 'Le', 'SLL' => 'Le', 'SOS' => 'Sh', 'SRD' => '$', 'STD' => 'Db', 'SZL' => 'L', 'THB' => '฿', 'TJS' => 'ЅМ',
            'TND' => 'د.ت', 'TOP' => 'T$', 'TRY' => '₺', 'TTD' => '$', 'TWD' => 'NT$', 'TZS' => 'Sh', 'UAH' => '₴', 'UGX' => 'Sh', 'USD' => '$', 'UYU' => '$',
            'UZS' => 'so\'m', 'VND' => '₫', 'VUV' => 'Vt', 'WST' => 'T', 'XAF' => 'Fr', 'XCD' => '$', 'XOF' => 'Fr', 'XPF' => 'Fr', 'YER' => 'ر.ي', 'ZAR' => 'R', 'ZMW' => 'ZK'
        ];
        
        $normalizedCode = strtoupper((string) ($currencyCode ?? ''));

        return apply_filters('rox_appointment_booking_currency_symbol', $symbols[$normalizedCode] ?? (string) ($currencyCode ?? ''));
    }
}

/**
 * Get list of Stripe supported currencies
 *
 * @return array Array of lowercase ISO 4217 currency codes supported by Stripe
 * @since 1.0.0
 */
if (!function_exists('rox_appointment_booking_get_stripe_supported_currencies')) {
    function rox_appointment_booking_stripe_supported_currencies() {
        return ['aed', 'afn', 'all', 'amd', 'ang', 'aoa', 'ars', 'aud', 'awg', 'azn', 'bam', 'bbd', 'bdt', 'bgn', 'bhd', 'bif', 'bmd', 'bnd', 'bob', 'brl', 'bsd', 'bwp', 'byn', 'bzd', 'cad', 'cdf', 'chf', 'clp', 'cny', 'cop', 'crc', 'cve', 'czk', 'djf', 'dkk', 'dop', 'dzd', 'egp', 'etb', 'eur', 'fjd', 'fkp', 'gbp', 'gel', 'gip', 'gmd', 'gnf', 'gtq', 'gyd', 'hkd', 'hnl', 'hrk', 'htg', 'huf', 'idr', 'ils', 'inr', 'isk', 'jmd', 'jod', 'jpy', 'kes', 'kgs', 'khr', 'kmf', 'krw', 'kwd', 'kyd', 'kzt', 'lak', 'lbp', 'lkr', 'lrd', 'lsl', 'mad', 'mdl', 'mga', 'mkd', 'mmk', 'mnt', 'mop', 'mur', 'mvr', 'mwk', 'mxn', 'myr', 'mzn', 'nad', 'ngn', 'nio', 'nok', 'npr', 'nzd', 'omr', 'pab', 'pen', 'pgk', 'php', 'pkr', 'pln', 'pyg', 'qar', 'ron', 'rsd', 'rub', 'rwf', 'sar', 'sbd', 'scr', 'sek', 'sgd', 'shp', 'sle', 'sll', 'sos', 'srd', 'std', 'szl', 'thb', 'tjs', 'tnd', 'top', 'try', 'ttd', 'twd', 'tzs', 'uah', 'ugx', 'usd', 'uyu', 'uzs', 'vnd', 'vuv', 'wst', 'xaf', 'xcd', 'xof', 'xpf', 'yer', 'zar', 'zmw'];
    }
}

