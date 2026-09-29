<?php

namespace RoxAppointmentBooking\Modules\Mcp\REST;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Mcp\Services\McpSettings;

/**
 * Class GetMcpSettings
 *
 * @package RoxAppointmentBooking\Modules\Mcp\REST
 * @description Retrieves the MCP settings via REST API.
 */
class GetMcpSettings extends AbstractREST
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
    public static string $route = '/mcp-settings/get';

    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/mcp-settings/get';

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
     * Administrators only: MCP hands AI clients access to all business data.
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public function permissionCheck(WP_REST_Request $request): bool
    {
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return false;
        }

        return is_user_logged_in() && current_user_can('manage_options');
    }

    /**
     * Handle the REST API request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return rox_appointment_booking_rest_response(
            data: McpSettings::forResponse(),
            code: 200,
            message: esc_html__('MCP settings retrieved successfully', 'rox-appointment-booking')
        );
    }
}
