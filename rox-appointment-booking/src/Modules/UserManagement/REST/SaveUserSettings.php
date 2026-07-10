<?php

namespace RoxAppointmentBooking\Modules\UserManagement\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\UserManagement\Util\UserInfo;

/**
 * Class SaveUserSettings
 *
 * @package RoxAppointmentBooking\Modules\UserManagement\REST
 * @description Handles saving current user profile data via REST API.
 */
class SaveUserSettings extends AbstractREST
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
    public static string $route = 'user-settings';

    /**
     * Get the HTTP methods allowed for this route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'POST';
    }

    /**
     * Check whether the current user can access this endpoint.
     *
     * @param WP_REST_Request $request REST request instance.
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
     * Handle the REST API request.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 401,
                message: esc_html__('User is not logged in', 'rox-appointment-booking'),
                headers: ['status' => 401]
            );
        }

        $params = $request->get_params();

        $first_name = isset($params['first_name']) ? sanitize_text_field($params['first_name']) : '';
        $last_name = isset($params['last_name']) ? sanitize_text_field($params['last_name']) : '';
        $gender = isset($params['gender']) ? sanitize_text_field($params['gender']) : '';
        $dob = isset($params['dob']) ? sanitize_text_field($params['dob']) : '';
        $phone = isset($params['phone']) ? sanitize_text_field($params['phone']) : '';
        $internal_notes = isset($params['internal_notes']) ? sanitize_textarea_field($params['internal_notes']) : '';

        if ($first_name === '') {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('First Name is required', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        if ($last_name === '') {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('Last Name is required', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        $update_result = wp_update_user([
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
        ]);

        if (is_wp_error($update_result)) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: $update_result->get_error_message(),
                headers: ['status' => 400]
            );
        }

        update_user_meta($user_id, 'gender', $gender);
        update_user_meta($user_id, 'date_of_birth', $dob);
        update_user_meta($user_id, 'phone', $phone);
        update_user_meta($user_id, 'internal_notes', $internal_notes);

        $userInfo = new UserInfo();
        $userData = $userInfo->getUserData();

        return rox_appointment_booking_rest_response(
            data: $userData,
            message: esc_html__('Profile updated successfully', 'rox-appointment-booking'),
            code: 200
        );
    }
}
