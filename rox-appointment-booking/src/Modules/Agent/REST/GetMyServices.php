<?php

namespace RoxAppointmentBooking\Modules\Agent\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Supports\Security;
use RoxAppointmentBooking\Modules\Appointment\Services\AppointmentService;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceAgentRelationModel;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceCategoryRelationModel;

/**
 * Class GetMyServices
 *
 * @package RoxAppointmentBooking\Modules\Agent\REST
 * @description Read-only list of the services assigned to the CURRENT logged-in
 *              agent, for the agent panel's Services page.
 *
 *              Deliberately separate from Calendar\REST\GetAgentServices: that one
 *              takes an `agent_id` parameter (so an agent could ask for someone
 *              else's services) and falls back to returning EVERY service when the
 *              agent has none assigned. Both behaviours are wrong here — this
 *              endpoint takes no id (the agent is resolved from the login) and
 *              returns an empty list when nothing is assigned.
 *
 *              The assignment itself lives in the existing
 *              `rox_appointment_agent_service` pivot table, which is what the admin
 *              agent/service forms already write — no separate storage.
 */
class GetMyServices extends AbstractREST
{
    /**
     * Whether the endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * REST route for the current agent's assigned services.
     *
     * @var string
     */
    public static string $route = '/agent/my-services';

    /**
     * Usable route template for docs.
     *
     * @var string
     */
    public static string $usableRoute = '/agent/my-services';

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

    /**
     * Handle the REST API request.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (!AppointmentService::isAgentUser()) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 403,
                message: esc_html__('This endpoint is only available to agents.', 'rox-appointment-booking'),
                headers: ['status' => 403]
            );
        }

        $agentId = AppointmentService::getCurrentAgentId();

        if (!$agentId) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 403,
                message: esc_html__('Agent account not found for this user.', 'rox-appointment-booking'),
                headers: ['status' => 403]
            );
        }

        $search = sanitize_text_field((string) $request->get_param('search'));
        $services = ServiceAgentRelationModel::getServicesByAgent($agentId);

        $rows = [];
        foreach ($services as $service) {
            if ($search !== '' && stripos((string) $service->title, $search) === false) {
                continue;
            }

            $rows[] = $this->formatService($service);
        }

        usort($rows, function ($a, $b) {
            return $a['sort_order'] <=> $b['sort_order'];
        });

        return rox_appointment_booking_rest_response(
            data: $rows,
            message: esc_html__('Services retrieved successfully', 'rox-appointment-booking')
        );
    }

    /**
     * Shape one service for the agent's read-only list + view drawer.
     *
     * Only presentation fields are exposed — no `internal_notes`, no
     * `created_by`/`updated_by`, no assigned-agent list.
     *
     * @param mixed $service ServiceModel instance.
     * @return array
     */
    private function formatService($service): array
    {
        $categories = [];
        foreach (ServiceCategoryRelationModel::getCategoriesForService((int) $service->getID()) as $category) {
            $title = $category->title ?? '';
            if ($title !== '') {
                $categories[] = $title;
            }
        }

        return [
            'id' => (int) $service->getID(),
            'name' => $service->title ?? '',
            'description' => $service->description ?? '',
            'duration' => $service->getFormattedDuration(),
            'duration_minutes' => (int) ($service->duration ?? 0),
            'price' => (float) ($service->price ?? 0),
            'capacity' => $service->capacity !== null ? (int) $service->capacity : null,
            'max_capacity' => $service->max_capacity !== null ? (int) $service->max_capacity : null,
            'deposit' => (bool) $service->deposit,
            'deposit_type' => $service->deposit_type ?? '',
            'deposit_amount' => (float) ($service->deposit_amount ?? 0),
            'allow_without_agent' => (bool) $service->allow_without_agent,
            'status' => $service->status ?? 'active',
            'color' => $service->color ?? '',
            'thumbnail_url' => !empty($service->thumbnail_id) ? wp_get_attachment_url((int) $service->thumbnail_id) : null,
            'categories' => $categories,
            'sort_order' => (int) ($service->sort_order ?? 0),
        ];
    }
}
