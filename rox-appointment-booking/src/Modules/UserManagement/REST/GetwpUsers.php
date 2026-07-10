<?php

namespace RoxAppointmentBooking\Modules\UserManagement\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;

/**
 * Class GetwpUsers
 *
 * @package RoxAppointmentBooking\Modules\UserManagement\REST
 * @description Provides API endpoint for fetching WordPress users in two formats.
 */
class GetwpUsers extends AbstractREST
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
    public static string $route = 'get-wp-users';

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
     * Handle the REST API request to fetch WordPress users
     * 
     * - Default response: associative array with username keys and ['email'=>email] values.
     * - If ?mode=username is provided: returns list of ['label'=>username,'value'=>username].
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $params = $request->get_params();
        $mode = isset($params['mode']) ? sanitize_text_field($params['mode']) : '';

        $users = get_users();

        if (!is_array($users)) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 500,
                message: esc_html__('Failed to fetch users', 'rox-appointment-booking'),
                headers: ['status' => 500]
            );
        }

        if ($mode === 'username') {
            $result = [];
            foreach ($users as $user) {
                $login = $user->user_login;
                $result[] = [
                    'label' => $login,
                    'value' => $login,
                ];
            }
        } else {
            $result = [];
            foreach ($users as $user) {
                $login = $user->user_login;
                $email = $user->user_email;
                $result[$login] = [
                    'email' => $email,
                ];
            }
        }

        return rox_appointment_booking_rest_response(
            data: $result,
            message: esc_html__('Users fetched successfully', 'rox-appointment-booking'),
            code: 200
        );
    }
}
