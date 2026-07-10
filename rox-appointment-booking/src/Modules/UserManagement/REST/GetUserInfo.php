<?php

namespace RoxAppointmentBooking\Modules\UserManagement\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\UserManagement\Util\UserInfo;

/**
 * Class GetUserInfo
 *
 * @package RoxAppointmentBooking\Modules\UserManagement\REST
 * @description Provides API endpoint for fetching current user information.
 */
class GetUserInfo extends AbstractREST
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
    public static string $route = 'get-user-info';

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
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return false;
        }
        return true;
    }

    /**
     * Handle the REST API request to fetch current user information
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userInfo = new UserInfo();

        if (!$userInfo->isLoggedIn()) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 401,
                message: esc_html__('User is not logged in', 'rox-appointment-booking'),
                headers: ['status' => 401]
            );
        }

        $userData = $userInfo->getUserData();

        // Apply filter to allow extensions to modify user data
        $userData = apply_filters('rox_appointment_booking_get_user_info_data', $userData, $userInfo);

        return rox_appointment_booking_rest_response(
            data: $userData,
            message: esc_html__('User info fetched successfully', 'rox-appointment-booking'),
            code: 200
        );
    }
}
