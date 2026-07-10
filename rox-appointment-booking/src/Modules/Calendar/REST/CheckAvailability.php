<?php

namespace RoxAppointmentBooking\Modules\Calendar\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Supports\Security;
use RoxAppointmentBooking\Modules\Calendar\Services\CalendarService;

/**
 * Class CheckAvailability
 *
 * @package RoxAppointmentBooking\Modules\Calendar\REST
 * @description Check appointment availability for a specific date, time slot, service, and agent.
 */
class CheckAvailability extends AbstractREST
{
    /**
     * Whether the endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for availability checks.
     *
     * @var string
     */
    public static string $route = '/calendar/check-availability';
    /**
     * Usable route template for docs.
     *
     * @var string
     */
    public static string $usableRoute = '/calendar/check-availability';

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

        // Required parameters
        $date = $request->get_param('date');
        $slot = $request->get_param('slot');
        $serviceId = $request->get_param('service_id');
        $agentId = $request->get_param('agent_id');
        $locationId = $request->get_param('location_id');

        // Validate required parameters
        $errors = [];

        if (empty($date)) {
            $errors[] = 'date';
        }

        if (empty($slot)) {
            $errors[] = 'slot';
        }

        if (empty($serviceId)) {
            $errors[] = 'service_id';
        }

        if (empty($agentId)) {
            $errors[] = 'agent_id';
        }

        if (!empty($errors)) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                // translators: %s = comma-separated list of missing parameters
                message: sprintf(esc_html__('Missing required parameters: %s', 'rox-appointment-booking'), esc_html(implode(', ', $errors))),
                headers: ['status' => 400]
            );
        }

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

        // Validate slot format (HH:MM or HH:MM:SS)
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $slot)) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('Invalid slot format. Use HH:MM or HH:MM:SS format.', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        // Check availability
        $availability = $calendarService->checkSlotAvailability(
            date: $date,
            slot: $slot,
            serviceId: (int) $serviceId,
            agentId: (int) $agentId,
            locationId: $locationId ? (int) $locationId : null
        );

        return rox_appointment_booking_rest_response(
            data: $availability,
            message: $availability['available'] 
                ? esc_html__('Slot is available for booking', 'rox-appointment-booking')
                : esc_html__('Slot is not available', 'rox-appointment-booking')
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
