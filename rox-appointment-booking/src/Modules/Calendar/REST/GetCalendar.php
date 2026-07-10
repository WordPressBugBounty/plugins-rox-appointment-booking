<?php

namespace RoxAppointmentBooking\Modules\Calendar\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Supports\Security;
use RoxAppointmentBooking\Modules\Calendar\Services\CalendarService;

/**
 * Class GetCalendar
 *
 * @package RoxAppointmentBooking\Modules\Calendar\REST
 * @description Provides calendar events data via REST API.
 */
class GetCalendar extends AbstractREST
{
    /**
     * Whether the endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for calendar events.
     *
     * @var string
     */
    public static string $route = '/calendar';
    /**
     * Usable route template for docs.
     *
     * @var string
     */
    public static string $usableRoute = '/calendar';

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

        // Get filter parameters
        $filters = [
            'start' => $request->get_param('start'),
            'end' => $request->get_param('end'),
            'status' => $request->get_param('status'),
            'agent_id' => $request->get_param('agent_id'),
            'location_id' => $request->get_param('location_id'),
            'service_id' => $request->get_param('service_id'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });

        // Get calendar data (events, services, locations, users)
        $calendarData = $calendarService->getEvents($filters);

        return rox_appointment_booking_rest_response(
            data: $calendarData,
            message: esc_html__('Calendar events retrieved successfully', 'rox-appointment-booking')
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
