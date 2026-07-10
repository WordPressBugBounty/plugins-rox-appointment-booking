<?php

namespace RoxAppointmentBooking\Modules\Calendar\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Supports\Security;
use RoxAppointmentBooking\Modules\Calendar\Services\CalendarService;

/**
 * Class CheckHoliday
 *
 * @package RoxAppointmentBooking\Modules\Calendar\REST
 * @description Check if a date is a global holiday via REST API.
 */
class CheckHoliday extends AbstractREST
{
    /**
     * Whether the endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for holiday checks.
     *
     * @var string
     */
    public static string $route = '/calendar/check-holiday';
    /**
     * Usable route template for docs.
     *
     * @var string
     */
    public static string $usableRoute = '/calendar/check-holiday';

    /**
     * Get the methods allowed for this route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'GET';
    }

    /**
     * Handle the REST API request.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $calendarService = new CalendarService();

        $date = $request->get_param('date');
        $startDate = $request->get_param('start_date');
        $endDate = $request->get_param('end_date');

        // Check single date
        if (!empty($date)) {
            // Validate date format
            $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
            if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
                return rox_appointment_booking_rest_response(
                    data: null,
                    code: 400,
                    message: esc_html__('Invalid date format. Use Y-m-d format.', 'rox-appointment-booking'),
                    headers: ['status' => 400]
                );
            }

            $holidayInfo = $calendarService->checkGlobalHoliday($date);

            return rox_appointment_booking_rest_response(
                data: [
                    'date' => $date,
                    'is_holiday' => $holidayInfo['is_holiday'],
                    'is_day_off' => $holidayInfo['is_day_off'],
                    'is_disabled' => $holidayInfo['is_holiday'] || $holidayInfo['is_day_off'],
                ],
                message: esc_html__('Holiday check completed successfully', 'rox-appointment-booking')
            );
        }

        // Check date range
        if (!empty($startDate) && !empty($endDate)) {
            // Validate date formats
            $startDateObj = \DateTime::createFromFormat('Y-m-d', $startDate);
            $endDateObj = \DateTime::createFromFormat('Y-m-d', $endDate);
            
            if (!$startDateObj || $startDateObj->format('Y-m-d') !== $startDate) {
                return rox_appointment_booking_rest_response(
                    data: null,
                    code: 400,
                    message: esc_html__('Invalid start_date format. Use Y-m-d format.', 'rox-appointment-booking'),
                    headers: ['status' => 400]
                );
            }
            
            if (!$endDateObj || $endDateObj->format('Y-m-d') !== $endDate) {
                return rox_appointment_booking_rest_response(
                    data: null,
                    code: 400,
                    message: esc_html__('Invalid end_date format. Use Y-m-d format.', 'rox-appointment-booking'),
                    headers: ['status' => 400]
                );
            }

            $holidays = $calendarService->getHolidaysInRange($startDate, $endDate);

            return rox_appointment_booking_rest_response(
                data: [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'holidays' => $holidays['holidays'],
                    'day_offs' => $holidays['day_offs'],
                    'disabled_dates' => $holidays['disabled_dates'],
                ],
                message: esc_html__('Holiday range check completed successfully', 'rox-appointment-booking')
            );
        }

        return rox_appointment_booking_rest_response(
            data: null,
            code: 400,
            message: esc_html__('Either date or start_date and end_date are required', 'rox-appointment-booking'),
            headers: ['status' => 400]
        );
    }

    /**
     * Check if the user has permission to access the endpoint.
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
}
