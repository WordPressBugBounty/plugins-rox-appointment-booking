<?php

namespace RoxAppointmentBooking\Modules\FrontendBookingPanel\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
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
    /**
     * Whether this REST endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * REST route for listing and retrieving public agents.
     *
     * @var string
     */
    public static string $route = '/public/agent(?:/(?P<id>\d+))?';

    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/public/agent/';

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
        return true;
    }

    /**
     * Get work days from an agent or default weekly schedule.
     *
     * @param mixed $weeklySchedule Weekly schedule data.
     * @return array
     */
    private function getWorkDays($weeklySchedule): array
    {
        // First try agent's weekly_schedule
        if (!empty($weeklySchedule)) {
            $schedule = json_decode($weeklySchedule, true);
            if (is_array($schedule)) {
                $scheduleData = isset($schedule['weekly_schedule']) ? $schedule['weekly_schedule'] : $schedule;
                if (is_array($scheduleData) && !empty($scheduleData)) {
                    $workDays = [];
                    foreach ($scheduleData as $day) {
                        if (isset($day['day_name']) && (!isset($day['day_off']) || !$day['day_off'])) {
                            $workDays[] = $day['day_name'];
                        }
                    }
                    if (!empty($workDays)) {
                        return $workDays;
                    }
                }
            }
        }
        
        // Fallback to default schedule from options
        $defaultSchedule = get_option('rox_appointment_booking_weekly_schedule', '');
        if (!empty($defaultSchedule)) {
            $schedule = json_decode($defaultSchedule, true);
            if (is_array($schedule)) {
                $workDays = [];
                foreach ($schedule as $day) {
                    if (isset($day['day_name']) && (!isset($day['day_off']) || !$day['day_off'])) {
                        $workDays[] = $day['day_name'];
                    }
                }
                return $workDays;
            }
        }
        
        return [];
    }

    /**
     * Format agent data for REST responses.
     *
     * @param AgentModel $agent Agent model instance.
     * @param bool $detailed Whether to include detailed information.
     * @param string $mode Response mode.
     * @param bool $withAvatar Whether avatar data was requested.
     * @param bool $serviceFilter Whether response is filtered by service.
     * @return array
     */
    protected function getAgentData(AgentModel $agent, bool $detailed = false, string $mode = 'default', bool $withAvatar = false, bool $serviceFilter = false): array
    {
        if ($serviceFilter) {
            return [
                'id' => $agent->getID(),
                'thumbnail' => $agent->thumbnail_id ? wp_get_attachment_url($agent->thumbnail_id) : '',
                'name' => $agent->getFullName()
            ];
        }
        if ($mode === 'list') {
            return [
                'id' => $agent->getID(),
                'thumbnail' => $agent->thumbnail_id ? wp_get_attachment_url($agent->thumbnail_id) : '',
                'name' => $agent->getFullName()
            ];
        }
        $data = [
            'id' => $agent->getID(),
            'agent' => [
                'name' => $agent->getFullName(),
                'thumbnail' => $agent->thumbnail_id ? wp_get_attachment_url($agent->thumbnail_id) : '',
            ],
            'email' => $agent->email,
            'phone' => $agent->phone,
            'experience_years' => $agent->experience_years ?? 0,
            'happy_customers' => $agent->happy_customers ?? 0,
            'certifications' => $agent->certifications ?? 0,
            'work_days' => $this->getWorkDays($agent->weekly_schedule),
            'bio' => $agent->bio ?? '',
            'socials' => !empty($agent->socials) ? json_decode($agent->socials, true) : [
                'twitter' => $agent->twitter ?? '',
                'linkedin' => $agent->linkedin ?? ''
            ],
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
                'experience_years' => $agent->experience_years ?? 0,
                'happy_customers' => $agent->happy_customers ?? 0,
                'certifications' => $agent->certifications ?? 0,
                'work_days' => $this->getWorkDays($agent->weekly_schedule),
                'bio' => $agent->bio ?? '',
                'socials' => !empty($agent->socials) ? json_decode($agent->socials, true) : [
                    'twitter' => $agent->twitter ?? '',
                    'linkedin' => $agent->linkedin ?? ''
                ],
            ]);
        }
        
        return $data;
    }

    /**
     * Handle the REST API request.
     *
     * @param WP_REST_Request $request REST request instance.
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
        $page = $request->get_param('page') ?? 1;
        $per_page = $request->get_param('per_page') ?? 10;
        $search = $request->get_param('search') ?? '';
        $mode = $request->get_param('mode') ?? 'default';
        $service_id = $request->get_param('service_id') ?? null;
        $with_avatar = filter_var($request->get_param('with_avatar'), FILTER_VALIDATE_BOOLEAN);
        
        $query = AgentModel::query();
        
        if (!empty($service_id)) {
            $agentIds = ServiceAgentRelationModel::where('service_id', $service_id)
                ->pluck('agent_id')
                ->toArray();
            
            if (!empty($agentIds)) {
                $query->whereIn('id', $agentIds);
            } else {
                $query->where('id', 0);
            }
        }
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }
        $total = $query->count();
        $agents = $query->offset(($page - 1) * $per_page)
                         ->limit($per_page)
                         ->orderBy('created_at', 'DESC')
                         ->get();
        $data = [];
        foreach ($agents as $agent) {
            $data[] = $this->getAgentData($agent, false, $mode, $with_avatar, !empty($service_id));
        }
        $meta = [
            'total' => $total,
            'page' => (int)$page,
            'per_page' => (int)$per_page,
            'total_pages' => ceil($total / $per_page)
        ];
        return rox_appointment_booking_rest_response(
            data : $data,
            message : esc_html__('Agents retrieved successfully', 'rox-appointment-booking'),
            options : $meta
        );
    }
}
