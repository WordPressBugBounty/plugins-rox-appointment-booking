<?php

namespace RoxAppointmentBooking\Modules\Calendar\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Supports\Security;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceAgentRelationModel;
use RoxAppointmentBooking\Modules\Agent\Services\AgentService;
use RoxAppointmentBooking\Modules\Service\Services\ServiceService;

/**
 * Class GetAgentServices
 *
 * @package RoxAppointmentBooking\Modules\Calendar\REST
 * @description Get services provided by a specific agent via REST API.
 *              If no services are explicitly assigned to the agent,
 *              returns all available services as fallback.
 */
class GetAgentServices extends AbstractREST
{
    /**
     * Whether the endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for agent service list.
     *
     * @var string
     */
    public static string $route = '/calendar/agent-services';
    /**
     * Usable route template for docs.
     *
     * @var string
     */
    public static string $usableRoute = '/calendar/agent-services';

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
        $agentId = $request->get_param('agent_id');
        $fallbackToAll = $request->get_param('fallback_to_all') !== 'false'; // Default to true
        
        // Validate agent_id is provided
        if (empty($agentId)) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('agent_id is required', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        $agentId = (int) $agentId;

        try {
            // Verify agent exists
            $agentService = new AgentService();
            $agent = $agentService->getAgent($agentId);
            
            if (!$agent) {
                return rox_appointment_booking_rest_response(
                    data: null,
                    code: 404,
                    message: esc_html__('Agent not found', 'rox-appointment-booking'),
                    headers: ['status' => 404]
                );
            }

            // Get services provided by this agent
            $services = ServiceAgentRelationModel::getServicesByAgent($agentId);
            $isUsingFallback = false;
            
            // If no services assigned and fallback is enabled, get all services
            // Use count() or ->isEmpty() for Collection objects
            $servicesCount = is_countable($services) ? count($services) : 0;
            if ($servicesCount === 0 && $fallbackToAll) {
                $serviceService = new ServiceService();
                $allServices = $serviceService->getVisibleServices();
                $services = $allServices;
                $isUsingFallback = true;
            }
            
            // Format services for dropdown/list use
            $formattedServices = [];
            foreach ($services as $service) {
                $formattedServices[] = [
                    'id' => $service->id,
                    'value' => $service->id,
                    'label' => $service->title,
                    'title' => $service->title,
                    'description' => $service->description ?? '',
                    'duration' => $service->duration,
                    'price' => $service->price,
                    'deposit' => $service->deposit ?? 0,
                    'deposit_type' => $service->deposit_type ?? 'fixed',
                    'status' => $service->status ?? 'active',
                    'color' => $service->color ?? '#1890ff',
                    'thumbnail_id' => $service->thumbnail_id ?? null,
                    'thumbnail_url' => !empty($service->thumbnail_id) ? wp_get_attachment_url($service->thumbnail_id) : null,
                ];
            }

            // Get agent info for context
            $agentInfo = [
                'id' => $agent->id,
                'full_name' => $agent->full_name,
                'email' => $agent->email,
                'thumbnail_id' => $agent->thumbnail_id,
                'avatar' => $agent->thumbnail_id ? wp_get_attachment_url($agent->thumbnail_id) : null,
            ];

            $message = $isUsingFallback
                ? sprintf(
                    // translators: %1$s = agent full name, %2$d = number of available services
                    esc_html__('No specific services assigned to %1$s. Showing all %2$d available services.', 'rox-appointment-booking'),
                    esc_html($agent->full_name),
                    count($formattedServices)
                )
                : sprintf(
                    // translators: %1$d = number of services, %2$s = agent full name
                    esc_html__('Found %1$d services for agent %2$s', 'rox-appointment-booking'),
                    count($formattedServices),
                    esc_html($agent->full_name)
                );

            return rox_appointment_booking_rest_response(
                data: [
                    'agent' => $agentInfo,
                    'services' => $formattedServices,
                    'total' => count($formattedServices),
                    'is_fallback' => $isUsingFallback,
                ],
                message: $message
            );

        } catch (\Exception $e) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 500,
                message: $e->getMessage(),
                headers: ['status' => 500]
            );
        }
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
