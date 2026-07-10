<?php

namespace RoxAppointmentBooking\Modules\Customer\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;
use RoxAppointmentBooking\Modules\Agent\Data\AgentModel;

/**
 * Class GetWordPressUsers
 * 
 * @package RoxAppointmentBooking\Modules\Customer\REST
 * @description Handles retrieving WordPress users with email and name via REST API.
 */
class GetWordPressUsers extends AbstractREST
{
    /**
     * Whether this REST endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * REST route for retrieving WordPress users.
     *
     * @var string
     */
    public static string $route = '/wordpress-users';

    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/wordpress-users';

    /**
     * Get the HTTP methods allowed for this route.
     * 
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'GET';
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
        // Get search parameter if provided
        $search = sanitize_text_field($request->get_param('search'));

        // Optional mode selector for mapping source: customer or agent.
        $mode = sanitize_key((string) $request->get_param('mode'));
        if (empty($mode)) {
            $mode = 'customer';
        }

        if (!in_array($mode, ['customer', 'agent'], true)) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('Invalid mode. Use mode=customer or mode=agent', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        // Collect mapped WordPress user IDs based on selected mode.
        $mapped_wp_user_ids = $mode === 'agent'
            ? AgentModel::query()->whereNotNull('wp_user_id')->pluck('wp_user_id')->toArray()
            : CustomerModel::query()->whereNotNull('wp_user_id')->pluck('wp_user_id')->toArray();

        $mapped_wp_user_ids = array_values(array_unique(array_filter(array_map('absint', $mapped_wp_user_ids))));
        
        // Get number parameter for limit
        $number = absint($request->get_param('number'));
        if (empty($number)) {
            $number = 100; // Default limit
        }

        // Prepare user query arguments
        $args = [
            'number' => $number,
            'orderby' => 'display_name',
            'order' => 'ASC',
        ];

        if (!empty($mapped_wp_user_ids)) {
            // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Excluding mapped users is required to return only unlinked accounts.
            $args['exclude'] = $mapped_wp_user_ids;
        }

        // Add search if provided
        if (!empty($search)) {
            $args['search'] = '*' . esc_attr($search) . '*';
            $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }

        // Get WordPress users
        $wp_users = get_users($args);

        // Format response
        $users = [];
        foreach ($wp_users as $user) {
            $users[] = [
                'name' => $user->display_name,
                'first_name' => get_user_meta($user->ID, 'first_name', true),
                'last_name' => get_user_meta($user->ID, 'last_name', true),
                'email' => $user->user_email,
            ];
        }

        return rox_appointment_booking_rest_response(
            data: $users,
            message: [
                'success' => [
                    esc_html__('WordPress users retrieved successfully', 'rox-appointment-booking')
                ]
            ]
        );
    }
}
