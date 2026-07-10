<?php

namespace RoxAppointmentBooking\Modules\Settings\REST\Menueapi;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;

/**
 * Class SaveWorkingHours
 *
 * @package RoxAppointmentBooking\Modules\Settings\REST\Menueapi
 * @description Handles saving working hours/weekly schedule data via REST API.
 */
class SaveWorkingHours extends AbstractREST
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
    public static string $route = '/menueapi/working-hours/save';
    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/menueapi/working-hours/save';

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
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return false;
        }

        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return false;
        }

        return true;
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
        
        // Get weekly schedule data from request
        $weekly_schedule_data = isset($params['weekly_schedule']) ? $params['weekly_schedule'] : null;
        
        if (!isset($weekly_schedule_data)) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('Weekly schedule data is required', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        try {
            $response_data = [];
            
            // Process and save weekly schedule (including empty arrays to clear schedule)
            if (is_array($weekly_schedule_data) && !empty($weekly_schedule_data)) {
                // Validate weekly schedule data
                $validation = $this->validateWeeklyScheduleData($weekly_schedule_data);
                if (is_wp_error($validation)) {
                    return rox_appointment_booking_rest_response(
                        data: null,
                        code: 400,
                        message: $validation->get_error_message(),
                        headers: ['status' => 400]
                    );
                }
                
                // Process the weekly schedule data
                $processed_schedule = $weekly_schedule_data;
            } else {
                // Empty array - clear schedule
                $processed_schedule = [];
            }
            
            // Simply encode as JSON like SaveAgent.php does
            $json_schedule = json_encode($processed_schedule);
            
            // Update the option
            update_option('rox_appointment_booking_weekly_schedule', $json_schedule);
            
            $response_data['weekly_schedule'] = $processed_schedule;
            
            return rox_appointment_booking_rest_response(
                data: $response_data,
                code: 200,
                message: esc_html__('Weekly schedule saved successfully', 'rox-appointment-booking')
            );
            
        } catch (\Exception $e) {            
            return rox_appointment_booking_rest_response(
                data: null,
                code: 500,
                message: [
                    'error' => [
                        // translators: %s = error message
                        sprintf(esc_html__('Error saving working hours: %s', 'rox-appointment-booking'), esc_html($e->getMessage()))
                    ]
                ],
                headers: ['status' => 500]
            );
        }
    }

    /**
     * Validate weekly schedule data
     * 
     * @param mixed $schedule_data
     * @return bool|WP_Error
     */
    private function validateWeeklyScheduleData($schedule_data)
    {
        if (!is_array($schedule_data)) {
            return new WP_Error(
                'invalid_data',
                esc_html__('Weekly schedule data must be an array', 'rox-appointment-booking')
            );
        }

        // Validate each day entry
        foreach ($schedule_data as $index => $day) {
            if (!is_array($day)) {
                return new WP_Error(
                    'invalid_day_data',
                    // translators: %d = array index
                    sprintf(esc_html__('Invalid day data at index %d', 'rox-appointment-booking'), $index)
                );
            }

            // Check required fields
            if (!isset($day['day_name'])) {
                return new WP_Error(
                    'missing_day_name',
                    // translators: %d = array index
                    sprintf(esc_html__('Missing day_name at index %d', 'rox-appointment-booking'), $index)
                );
            }

            if (!isset($day['day_off'])) {
                return new WP_Error(
                    'missing_day_off',
                    // translators: %d = array index
                    sprintf(esc_html__('Missing day_off at index %d', 'rox-appointment-booking'), $index)
                );
            }

            // Validate schedule format if day is not off
            if (!$day['day_off'] && isset($day['schedule'])) {
                if (!is_array($day['schedule']) || count($day['schedule']) !== 2) {
                    return new WP_Error(
                        'invalid_schedule_format',
                        // translators: %d = array index
                        sprintf(esc_html__('Invalid schedule format at index %d. Expected [start_time, end_time]', 'rox-appointment-booking'), $index)
                    );
                }
            }

            // Validate breaks format if provided
            if (isset($day['breaks']) && !is_array($day['breaks'])) {
                return new WP_Error(
                    'invalid_breaks_format',
                    // translators: %d = array index
                    sprintf(esc_html__('Invalid breaks format at index %d. Expected array of time ranges', 'rox-appointment-booking'), $index)
                );
            }
        }

        return true;
    }

    /**
     * Get required fields for settings data
     * 
     * @return array
     */
    public static function getRequiredFields()
    {
        return []; // weekly_schedule can be sent
    }
}
