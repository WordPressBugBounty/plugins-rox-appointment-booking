<?php

namespace RoxAppointmentBooking\Modules\Settings\REST\Menueapi;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Supports\Security;

/**
 * Class GetHoliday
 *
 * @package RoxAppointmentBooking\Modules\Settings\REST\Menueapi
 * @description Retrieves holiday data via REST API.
 */
class GetHoliday extends AbstractREST
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
    public static string $route = '/menueapi/holiday/get';
    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/menueapi/holiday/get';

    /**
     * Get the methods allowed for this route
     * 
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'GET';
    }

    /**
     * Check if the user has permission to access this endpoint
     * 
     * @param WP_REST_Request $request
     * @return bool
     */
    public function permissionCheck(WP_REST_Request $request): bool
    {
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return false;
        }

        if (!is_user_logged_in() || !Security::canAccessPanel()) {
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
        try {
            // Get holidays
            $holidays = get_option('rox_appointment_booking_holidays', []);

            // Get query parameters for filtering holidays
            $year = $request->get_param('year');
            $month = $request->get_param('month');
            $start_date = $request->get_param('start_date');
            $end_date = $request->get_param('end_date');

            // Filter holidays based on query parameters
            if (!empty($year) || !empty($month) || !empty($start_date) || !empty($end_date)) {
                $holidays = $this->filterHolidays($holidays, $year, $month, $start_date, $end_date);
            }

            // Transform holidays to array of date strings for the calendar component
            // Holidays are stored as [{date: "2024-05-03", description: ""}]
            // But form needs ["2024-05-03"]
            $holiday_dates = array_map(function($holiday) {
                return is_array($holiday) && isset($holiday['date']) ? $holiday['date'] : $holiday;
            }, $holidays);

            return rox_appointment_booking_rest_response(
                data: [
                    'holiday' => $holiday_dates,  // Use 'holiday' to match form field name
                    'holidays_count' => count($holidays)
                ],
                code: 200,
                message: [
                    'success' => [
                        esc_html__('Holidays retrieved successfully', 'rox-appointment-booking')
                    ]
                ]
            );
            
        } catch (\Exception $e) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 500,
                message: [
                    'error' => [
                        // translators: %s = error message
                        sprintf(esc_html__('Error retrieving holidays: %s', 'rox-appointment-booking'), esc_html($e->getMessage()))
                    ]
                ],
                headers: ['status' => 500]
            );
        }
    }

    /**
     * Filter holidays based on date criteria
     * 
     * @param array $holidays
     * @param string|null $year
     * @param string|null $month
     * @param string|null $start_date
     * @param string|null $end_date
     * @return array
     */
    private function filterHolidays($holidays, $year = null, $month = null, $start_date = null, $end_date = null)
    {
        if (empty($holidays)) {
            return [];
        }

        $filtered = array_filter($holidays, function($holiday) use ($year, $month, $start_date, $end_date) {
            $holiday_date = $holiday['date'];

            // Filter by year
            if (!empty($year) && gmdate('Y', strtotime($holiday_date)) != $year) {
                return false;
            }

            // Filter by month
            if (!empty($month) && gmdate('m', strtotime($holiday_date)) != sprintf('%02d', $month)) {
                return false;
            }

            // Filter by date range
            if (!empty($start_date) && strtotime($holiday_date) < strtotime($start_date)) {
                return false;
            }

            if (!empty($end_date) && strtotime($holiday_date) > strtotime($end_date)) {
                return false;
            }

            return true;
        });

        return array_values($filtered);
    }
}
