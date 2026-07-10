<?php

namespace RoxAppointmentBooking\Modules\Settings\REST\Menueapi;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;

/**
 * Class SaveHoliday
 *
 * @package RoxAppointmentBooking\Modules\Settings\REST\Menueapi
 * @description Handles saving holiday data via REST API.
 */
class SaveHoliday extends AbstractREST
{
    /**
     * Whether this class should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for this endpoint.
     *
     * @var string
     */
    public static string $route = '/menueapi/holiday/save';
    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/menueapi/holiday/save';

    /**
     * Get the methods allowed for this route
     * 
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return ['POST', 'PUT'];
    }

    /**
     * Check if the user has permission to access this endpoint
     * 
     * @param WP_REST_Request $request
     * @return bool
     */
    public function permissionCheck(WP_REST_Request $request): bool
    {
        // Check if user has appropriate capabilities
        return  true;
    }

    /**
     * Handle the REST API request
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $params = $request->get_params();
        
        // Get holiday data from request
        $holiday_data = isset($params['holiday']) ? $params['holiday'] : null;
        
        if (!isset($holiday_data)) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('Holiday data is required', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        try {
            // Process and save holidays (including empty arrays to clear holidays)
            if (is_array($holiday_data) && !empty($holiday_data)) {
                // Validate holiday data structure
                $validation = $this->validateHolidayData($holiday_data);
                if (is_wp_error($validation)) {
                    return rox_appointment_booking_rest_response(
                        data: null,
                        code: 400,
                        message: $validation->get_error_message(),
                        headers: ['status' => 400]
                    );
                }
                
                // Process the holiday data
                $processed_holidays = $holiday_data;
            } else {
                // Empty array - clear all holidays
                $processed_holidays = [];
            }
            
            // Update the option
            update_option('rox_appointment_booking_holidays', $processed_holidays);
            
            return rox_appointment_booking_rest_response(
                data: ['holiday' => $processed_holidays],
                code: 200,
                message: esc_html__('Holidays saved successfully', 'rox-appointment-booking')
            );
            
        } catch (\Exception $e) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 500,
                message: [
                    'error' => [
                        // translators: %s = error message
                        sprintf(esc_html__('Error saving holidays: %s', 'rox-appointment-booking'), esc_html($e->getMessage()))
                    ]
                ],
                headers: ['status' => 500]
            );
        }
    }

    /**
     * Validate holiday data
     * 
     * @param mixed $holiday_data
     * @return bool|WP_Error
     */
    private function validateHolidayData($holiday_data)
    {
        if (!is_array($holiday_data)) {
            return new WP_Error(
                'invalid_data',
                esc_html__('Holiday data must be an array', 'rox-appointment-booking')
            );
        }

        // Validate each holiday entry
        foreach ($holiday_data as $index => $holiday) {
            // If it's a simple date string
            if (is_string($holiday)) {
                // Validate date format
                if (!$this->isValidDate($holiday)) {
                    return new WP_Error(
                        'invalid_date',
                        // translators: %1$d = array index, %2$s = invalid date value
                        sprintf(esc_html__('Invalid date format at index %1$d: %2$s', 'rox-appointment-booking'), $index, esc_html($holiday))
                    );
                }
            }
            // If it's an array with date and optional description
            elseif (is_array($holiday)) {
                if (!isset($holiday['date'])) {
                    return new WP_Error(
                        'missing_date',
                        // translators: %d = array index
                        sprintf(esc_html__('Missing date field at index %d', 'rox-appointment-booking'), $index)
                    );
                }

                if (!$this->isValidDate($holiday['date'])) {
                    return new WP_Error(
                        'invalid_date',
                        // translators: %1$d = array index, %2$s = invalid date value
                        sprintf(esc_html__('Invalid date format at index %1$d: %2$s', 'rox-appointment-booking'), $index, esc_html($holiday['date']))
                    );
                }
            }
        }

        return true;
    }

    /**
     * Check if a date string is valid
     * 
     * @param string $date
     * @return bool
     */
    private function isValidDate($date)
    {
        if (empty($date)) {
            return false;
        }

        // Try to parse the date
        $timestamp = strtotime($date);
        
        if ($timestamp === false) {
            return false;
        }

        // Verify it's a valid date
        return checkdate(
            gmdate('m', $timestamp),
            gmdate('d', $timestamp),
            gmdate('Y', $timestamp)
        );
    }


    /**
     * Get required fields for settings data
     * 
     * @return array
     */
    public static function getRequiredFields()
    {
        return ['holiday'];
    }
}
