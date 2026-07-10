<?php

namespace RoxAppointmentBooking\Supports;

if (!defined('ABSPATH')) exit;

use WP_REST_Request;

/**
 * Security helpers for REST and input sanitization.
 *
 * @package RoxAppointmentBooking
 * @since 1.0.0
 */
class Security
{
    /**
     * Verifies REST nonce from the request headers.
     *
     * @param WP_REST_Request $request
     * @param string $action
     * @return bool
     */
    public static function verifyNonce(WP_REST_Request $request, string $action = 'wp_rest'): bool
    {
        $nonce = $request->get_header('X-WP-Nonce');
        return $nonce && wp_verify_nonce($nonce, $action);
    }

    /**
     * Checks whether the current user can manage bookings.
     *
     * @return bool
     */
    public static function canManageBookings(): bool
    {
        return current_user_can('manage_options') || current_user_can('rox_appointment_booking_manager');
    }

    /**
     * Checks whether the current user can view bookings.
     *
     * @return bool
     */
    public static function canViewBookings(): bool
    {
        return current_user_can('manage_options') ||
               current_user_can('rox_appointment_booking_manager') ||
               current_user_can('rox_appointment_booking_agent');
    }

    /**
     * Checks whether the current user can access the booking panel at all
     * (admin, manager, agent, or customer).
     *
     * @return bool
     */
    public static function canAccessPanel(): bool
    {
        return self::canManageBookings()
            || current_user_can('rox_appointment_booking_agent_capability')
            || current_user_can('rox_appointment_booking_customer_capability');
    }

    /**
     * Sanitizes a string using WordPress text field rules.
     *
     * @param string $value
     * @return string
     */
    public static function sanitizeTextField(string $value): string
    {
        return sanitize_text_field(wp_unslash($value));
    }

    /**
     * Sanitizes an email address.
     *
     * @param string $email
     * @return string
     */
    public static function sanitizeEmail(string $email): string
    {
        return sanitize_email(wp_unslash($email));
    }

    /**
     * Sanitizes multi-line text input.
     *
     * @param string $value
     * @return string
     */
    public static function sanitizeTextarea(string $value): string
    {
        return sanitize_textarea_field(wp_unslash($value));
    }

    /**
     * Sanitizes a value to a non-negative integer.
     *
     * @param mixed $value
     * @return int
     */
    public static function sanitizeInt($value): int
    {
        return absint($value);
    }

    /**
     * Sanitizes a value to a float.
     *
     * @param mixed $value
     * @return float
     */
    public static function sanitizeFloat($value): float
    {
        return floatval($value);
    }

    /**
     * Sanitizes an array of fields based on provided rules.
     *
     * @param array $data
     * @param array $rules
     * @return array
     */
    public static function sanitizeArray(array $data, array $rules): array
    {
        $sanitized = [];
        foreach ($rules as $key => $type) {
            if (!isset($data[$key])) continue;
            
            $sanitized[$key] = match($type) {
                'text' => self::sanitizeTextField($data[$key]),
                'email' => self::sanitizeEmail($data[$key]),
                'textarea' => self::sanitizeTextarea($data[$key]),
                'int' => self::sanitizeInt($data[$key]),
                'float' => self::sanitizeFloat($data[$key]),
                'bool' => (bool) $data[$key],
                'array' => is_array($data[$key]) ? $data[$key] : [],
                default => $data[$key]
            };
        }
        return $sanitized;
    }

    /**
     * Validates that required fields are present and non-empty.
     *
     * @param array $data
     * @param array $required
     * @return string[]
     */
    public static function validateRequired(array $data, array $required): array
    {
        $errors = [];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                // translators: %s = field name
                $errors[] = sprintf(esc_html__('%s is required', 'rox-appointment-booking'), esc_html($field));
            }
        }
        return $errors;
    }

    /**
     * Simple rate limiter using transients.
     *
     * @param string $key
     * @param int $limit
     * @param int $period
     * @return bool
     */
    public static function rateLimitCheck(string $key, int $limit = 60, int $period = 60): bool
    {
        $transient_key = 'rox_appointment_booking_rate_' . md5($key);
        $attempts = get_transient($transient_key);
        
        if ($attempts === false) {
            set_transient($transient_key, 1, $period);
            return true;
        }
        
        if ($attempts >= $limit) {
            return false;
        }
        
        set_transient($transient_key, $attempts + 1, $period);
        return true;
    }
}
