<?php

namespace RoxAppointmentBooking\Modules\Agent\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Supports\Traits\RoxAppointmentBookingFilter;
use RoxAppointmentBooking\Modules\Agent\Data\AgentModel;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceAgentRelationModel;

/**
 * Class GetAgent
 *
 * @package RoxAppointmentBooking\Modules\Agent\REST
 * @description Handles retrieving list of agents and single agent via REST API.
 */
class GetAgent extends AbstractREST
{
    use RoxAppointmentBookingFilter;

    /**
     * Whether the endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for listing agents.
     *
     * @var string
     */
    public static string $route = '/agent(?:/(?P<id>\d+))?';
    /**
     * Usable route template for docs.
     *
     * @var string
     */
    public static string $usableRoute = '/agent/';

    /**
     * Gets HTTP methods for the route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'GET';
    }

    /**
     * Defines searchable fields for the filter helper.
     *
     * @return array
     */
    protected function getSearchableFields(): array
    {
        return ['id', 'first_name', 'last_name', 'email', 'phone', 'title','linkedin','twitter','bio'];
    }

    /**
     * Check user permissions.
     *
     * @since 1.0.0
     *
     * @param WP_REST_Request $request
     *
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
     * Formats agent data for list or detail responses.
     *
     * @param AgentModel $agent
     * @param bool $detailed
     * @param string $mode
     * @param bool $withAvatar
     * @return array
     */
    protected function getAgentData(AgentModel $agent, bool $detailed = false, string $mode = 'default', bool $withAvatar = false): array
    {
        if ($mode === 'list') {
            $data = [
                'value' => $agent->getID(),
                'label' => $agent->getFullName(),
            ];
            // Add thumbnail if with_avatar is true
            if ($withAvatar && !empty($agent->thumbnail_id)) {
                $data['thumbnail'] = wp_get_attachment_url($agent->thumbnail_id);
            }
            return $data;
        }
        $data = [
            'id' => $agent->getID(),
            'agent' => [
                'name' => $agent->getFullName(),
                'thumbnail' => $agent->thumbnail_id ? wp_get_attachment_url($agent->thumbnail_id) : '',
            ],
            'email' => $agent->email,
            'phone' => $agent->phone,
        ];
        if ($detailed) {
            $data = array_merge($data, [
                'thumbnail_id' => $agent->thumbnail_id ?? null,
                'first_name' => $agent->first_name ?? null,
                'last_name' => $agent->last_name ?? null,
                'full_name' => $agent->getFullName(),
                'title' => $agent->title ?? null,
                'location_id' => $agent->location_id ?? null,
                'service_ids' => ServiceAgentRelationModel::query()
                    ->where('agent_id', $agent->getID())
                    ->pluck('service_id')
                    ->toArray(),
                'weekly_schedule' => json_decode($agent->weekly_schedule ?? '[]', true),
                'special_days' => json_decode($agent->special_days ?? '[]', true),
                'availability' => $agent->availability ?? [],
                'holiday' => json_decode($agent->holiday ?? '[]', true),
                'internal_notes' => $agent->internal_notes ?? null,
                'allow_to_login' => (bool)$agent->allow_to_login,
                'user_type' => !empty($agent->wp_user_id) ? 'existing' : ($agent->user_type ?? null),
                'existing_user' => !empty($agent->wp_user_id) ? (($user = get_userdata($agent->wp_user_id)) ? $user->user_login : null) : null,
                'experience_years' => $agent->experience_years ?? null,
                'certifications' => $agent->certifications ?? null,
                'linkedin' => $agent->linkedin ?? null,
                'twitter' => $agent->twitter ?? null,
                'bio' => $agent->bio ?? null,
            ]);
        }
        return $data;
    }

	/**
	 * Handles agent list or single agent requests.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = $request->get_param('id');
        if ($id) {
            $agent = AgentModel::find($id);
            if (!$agent) {
                return rox_appointment_booking_rest_response(
                    data : null,
                    code : 404,
                    message : esc_html__('Agent not found', 'rox-appointment-booking'),
                    headers : ['status' => 404]
                );
            }
            return rox_appointment_booking_rest_response(
                data : $this->getAgentData($agent, true),
                message : esc_html__('Agent retrieved successfully', 'rox-appointment-booking')
            );
        }
        $pagination = $this->getPaginationParams($request);
        $mode = $request->get_param('mode') ?? 'default';
        $service_id =  $request->get_param('service') ?? $request->get_param('service_id') ?? null;
        $with_avatar = filter_var($request->get_param('with_avatar'), FILTER_VALIDATE_BOOLEAN);
        
        $query = AgentModel::query();
        
        // Filter by service if service_id is provided
        if (!empty($service_id)) {
            // Get agent IDs that belong to the specified service
            $agentIds = ServiceAgentRelationModel::where('service_id', $service_id)
                ->pluck('agent_id')
                ->toArray();
            
            // Filter agents by the IDs found in the service relation
            if (!empty($agentIds)) {
                $query->whereIn('id', $agentIds);
            } else {
                // If no agents found for this service, return empty result
                $query->where('id', 0);
            }
        }
        
        $query = $this->applyFilters($request, $query);
        
        $total = $query->count();
        // List mode feeds select dropdowns (optionsapi): return every matching
        // agent so an already-selected value always has a matching option/label.
        // The paginated default mode still drives the table view.
        if ($mode === 'list') {
            $agents = $query->orderBy('created_at', 'DESC')->get();
        } else {
            $agents = $query->offset(($pagination['page'] - 1) * $pagination['per_page'])
                             ->limit($pagination['per_page'])
                             ->orderBy('created_at', 'DESC')
                             ->get();
        }
        $data = [];
        
        // If mode is list, return minimal data for dropdowns, otherwise return detailed data
        if ($mode === 'list') {
            foreach ($agents as $agent) {
                $data[] = $this->getAgentData($agent, false, 'list', $with_avatar);
            }
        } else {
            foreach ($agents as $agent) {
                $data[] = $this->getAgentData($agent);
            }
        }
        
        return rox_appointment_booking_rest_response(
            data : $data,
            message : esc_html__('Agents retrieved successfully', 'rox-appointment-booking'),
            options : $this->buildPaginationMeta($total, $pagination['page'], $pagination['per_page'])
        );
    }
}