<?php

namespace RoxAppointmentBooking\Modules\FrontendBookingPanel\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;

/**
 * Class GetCurrentUser
 * 
 * @package RoxAppointmentBooking\Modules\FrontendBookingPanel\REST
 * @description Securely retrieves current logged-in user's profile data for pre-filling booking forms.
 * Only accessible to authenticated users and returns user's own data only.
 */
class GetCurrentUser extends AbstractREST
{
    /**
     * Whether this REST endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * REST route for retrieving the current customer profile.
     *
     * @var string
     */
    public static string $route = '/public/customer/me';

    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/public/customer/me';

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
     * Check if user is logged in - only logged-in users can access their own data
     * 
     * @param WP_REST_Request $request
     * @return bool
     */
    public function permissionCheck(WP_REST_Request $request): bool
    {
        return is_user_logged_in();
    }

    /**
     * Returns current logged-in user's profile data
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $wp_user = wp_get_current_user();

        if (!$wp_user || !$wp_user->ID) {
            return new WP_REST_Response([
                'success' => false,
                'code' => 401,
                'message' => 'User not authenticated',
                'data' => null
            ], 401);
        }

        // Start with WordPress user data
        $first_name = $wp_user->user_firstname;
        $last_name = $wp_user->user_lastname;
        $email = $wp_user->user_email;
        $phone = '';
        $id = null;
        $is_agent = false;
        $is_customer = false;

        // 1. Check if user is an Agent
        $agent = \RoxAppointmentBooking\Modules\Agent\Data\AgentModel::query()
            ->where('wp_user_id', $wp_user->ID)
            ->orWhere('email', $wp_user->user_email)
            ->first();

        if ($agent) {
            $is_agent = true;
            $id = $agent->getID();
            $first_name = $agent->first_name ?: $first_name;
            $last_name = $agent->last_name ?: $last_name;
            $phone = $agent->phone ?: $phone;
        } else {
            // 2. Check if user is a Customer
            $customer = \RoxAppointmentBooking\Modules\Customer\Data\CustomerModel::query()
                ->where('wp_user_id', $wp_user->ID)
                ->orWhere('email', $wp_user->user_email)
                ->first();

            if ($customer) {
                $is_customer = true;
                $id = $customer->getID();
                $first_name = $customer->first_name ?: $first_name;
                $last_name = $customer->last_name ?: $last_name;
                $phone = $customer->phone ?: $phone;
            }
        }

        // Build response with user profile data
        $user_data = [
            'id' => $id,
            'email' => $email,
            'first_name' => $first_name ?: $wp_user->display_name,
            'last_name' => $last_name,
            'phone' => $phone,
            'is_agent' => $is_agent,
            'is_customer' => $is_customer,
        ];

        // Allow filtering for custom user data
        $user_data = apply_filters('rox_appointment_booking/frontend/current_user_data', $user_data, $wp_user);

        return rox_appointment_booking_rest_response(
            data: $user_data,
            message: [
                'success' => [
                    esc_html__('Current user data retrieved successfully', 'rox-appointment-booking')
                ]
            ]
        );
    }
}
