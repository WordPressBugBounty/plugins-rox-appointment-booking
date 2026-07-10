<?php

namespace RoxAppointmentBooking\Modules\UserManagement\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Supports\Security;

/**
 * Class UserMenu
 *
 * @package RoxAppointmentBooking\Modules\UserManagement\REST
 * @description Provides the structure for creating a user menu via REST API.
 */
class UserMenu extends AbstractREST
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
    public static string $route = 'user-menu';

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
     * Handle the REST API request.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return rox_appointment_booking_rest_response(
            data: $this->getUserMenuStructure(),
            message: array(
                'success' => array(
                    esc_html__('Data Structure Retrieved Successfully', 'rox-appointment-booking')
                )
            )
        );
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
        if (!is_user_logged_in() || !Security::canAccessPanel()) {
            return false;
        }
        return true;
    }

    /**
     * Returns the create customer structure as a PHP array.
     *
     * @return array
     */
    private function getUserMenuStructure(): array
    {
        return apply_filters('rox_appointment_booking_user_menu_structure', [
            "items" => [
                [
                    "key" => "profile",
                    "label" => "Setting",
                    "icon" => "setting",
                    "action" => [
                        "type" => "navigate",
                        "route" => "/global-settings"
                    ]
                ],
                [
                    "key" => "logout",
                    "label" => "Logout",
                    "icon" => "logout",
                    "action" => [
                        "type" => "logout",
                        "url" => html_entity_decode(wp_logout_url())
                    ]
                ]
            ]
        ]);
    }
}
