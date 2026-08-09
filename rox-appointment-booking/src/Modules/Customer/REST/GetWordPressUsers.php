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

        // Optional mode selector, kept for API compatibility: both modes exclude
        // accounts already taken by an agent or a customer.
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

        // Collect mapped WordPress user IDs from both agents and customers, so an
        // account already taken by either one is never offered in the lookup.
        $mapped_wp_user_ids = array_merge(
            AgentModel::query()->whereNotNull('wp_user_id')->pluck('wp_user_id')->toArray(),
            CustomerModel::query()->whereNotNull('wp_user_id')->pluck('wp_user_id')->toArray()
        );

        $mapped_wp_user_ids = array_values(array_unique(array_filter(array_map('absint', $mapped_wp_user_ids))));

        // Agents/customers saved without a linked WordPress account still own their
        // email address, so exclude WordPress users sharing one of those emails too.
        $taken_emails = array_merge(
            AgentModel::query()->whereNotNull('email')->pluck('email')->toArray(),
            CustomerModel::query()->whereNotNull('email')->pluck('email')->toArray()
        );

        $mapped_wp_user_ids = array_values(array_unique(array_merge(
            $mapped_wp_user_ids,
            $this->getUserIdsByEmails($taken_emails)
        )));


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

    /**
     * Resolve a list of email addresses to WordPress user IDs in a single query.
     *
     * @param array $emails Email addresses.
     * @return array List of WordPress user IDs.
     */
    private function getUserIdsByEmails(array $emails): array
    {
        global $wpdb;

        $emails = array_values(array_unique(array_filter(array_map('sanitize_email', $emails))));

        if (empty($emails)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($emails), '%s'));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Placeholders are generated from a counted array and every value is passed through prepare().
        $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->users} WHERE user_email IN ({$placeholders})", $emails));

        return array_values(array_filter(array_map('absint', (array) $ids)));
    }
}
