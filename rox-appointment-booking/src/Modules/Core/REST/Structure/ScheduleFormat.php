<?php

namespace RoxAppointmentBooking\Modules\Core\REST\Structure;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;

/**
 * Class ScheduleFormat
 *
 * @package RoxAppointmentBooking\Modules\Core\REST\Structure
 * @description Provides the structure for appointments schedule format via REST API.
 */
class ScheduleFormat extends AbstractREST
{
    /**
     * Whether the endpoint should be loadable.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for schedule format structure.
     *
     * @var string
     */
    public static string $route = 'structure/schedule-format';

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
        return rox_appointment_booking_rest_response(
            data: $this->getScheduleFormatStructure(),
            message: array(
                'success' => array(
                    esc_html__('Data Structure Retrieved Successfully', 'rox-appointment-booking')
                )
            )
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

        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return false;
        }

        return true;
    }

    /**
     * Returns the appointments table structure as a PHP array.
     *
     * @return array
     */
    private function getScheduleFormatStructure(): array
    {
        return apply_filters('rox_appointment_booking_schedule_format_structure', [
            [
                "day_name" => "Monday",
                "day_off" => false,
                "schedule" => [
                    "2025-10-08T19:00:00.000Z",
                    "2025-10-09T14:00:00.000Z"
                ],
                "breaks" => [
                    [
                        "2025-10-08T22:00:00.000Z",
                        "2025-10-09T17:00:00.000Z"
                    ]
                ],
            ],
            [
                "day_name" => "Tuesday",
                "day_off" => false,
                "schedule" => [
                    "2025-10-08T19:00:00.000Z",
                    "2025-10-09T14:00:00.000Z"
                ],
                "breaks" => [
                    [
                        "2025-10-08T22:00:00.000Z",
                        "2025-10-09T17:00:00.000Z"
                    ]
                ],
            ],
            [
                "day_name" => "Wednesday",
                "day_off" => false,
                "schedule" => [
                    "2025-10-08T19:00:00.000Z",
                    "2025-10-09T14:00:00.000Z"
                ],
                "breaks" => [
                    [
                        "2025-10-08T22:00:00.000Z",
                        "2025-10-09T17:00:00.000Z"
                    ]
                ],
            ],
            [
                "day_name" => "Thursday",
                "day_off" => false,
                "schedule" => [
                    "2025-10-08T19:00:00.000Z",
                    "2025-10-09T14:00:00.000Z"
                ],
                "breaks" => [],
            ],
            [
                "day_name" => "Friday",
                "day_off" => false,
                "schedule" => [
                    "2025-10-08T19:00:00.000Z",
                    "2025-10-09T14:00:00.000Z"
                ],
                "breaks" => [],
            ],
            [
                "day_name" => "Saturday",
                "day_off" => false,
                "schedule" => [
                    "2025-10-08T19:00:00.000Z",
                    "2025-10-09T14:00:00.000Z"
                ],
                "breaks" => [],
            ],
            [
                "day_name" => "Sunday",
                "day_off" => false,
                "schedule" => [
                    "2025-10-08T19:00:00.000Z",
                    "2025-10-09T14:00:00.000Z"
                ],
                "breaks" => [],
            ],
        ]);
    }
}