<?php

/**
 * REST API response helper functions  Booking Engine
 *
 * This file contains utility functions for formatting and sending
 * consistent REST API responses throughout the plugin.
 *
 * @package RoxAppointmentBooking
 * @subpackage Functions
 * @since 1.0.0
 */

if (! defined('ABSPATH')) exit; // Exit if accessed directly

// if function not exists
if (!function_exists('rox_appointment_booking_rest_response')) {

    function rox_appointment_booking_rest_response($data = null, $status = 200, $message = null, $headers = array(), $options = array(), $code = null)
    {
        $http_status = $code ?? $status;

        return new WP_REST_Response(
            [
                'code' => $code ?? $status,
                'message' => $message,
                'data' => $data,
                'success' => $http_status < 400,
                'options' => $options,
            ],
            $http_status,
            $headers,
        );
    }
}
