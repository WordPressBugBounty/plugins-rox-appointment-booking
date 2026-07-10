<?php

namespace RoxAppointmentBooking\Modules\Agent\Services;

use RoxAppointmentBooking\Modules\Agent\Data\AgentModel;

/**
 * Class AgentService
 *
 * @package RoxAppointmentBooking\Modules\Agent\Services
 * @description Handles agent-related business logic.
 */
class AgentService
{
    /**
     * Whether the service should be loadable.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * Returns paginated agents with optional filters.
     *
     * @param array $filters
     * @param int $page
     * @param int $per_page
     * @return array
     */
    public function getAgents(array $filters = [], int $page = 1, int $per_page = 10): array
    {
        $query = AgentModel::query();
        if (!empty($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('full_name', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('email', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('phone', 'LIKE', "%{$filters['search']}%");
            });
        }
        $total = $query->count();
        $agents = $query->offset(($page - 1) * $per_page)
                         ->limit($per_page)
                         ->orderBy('created_at', 'DESC')
                         ->get();
        return [
            'items' => $agents,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ];
    }

	/**
	 * Creates or updates an agent.
	 *
	 * @param array $data
	 * @param int|null $id
	 * @return AgentModel
	 * @throws \Exception
	 */
    public function saveAgent(array $data, ?int $id = null): AgentModel
    {
        $required = ['full_name', 'email'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                // translators: %s = field name
                throw new \Exception(sprintf(esc_html__('%s is required', 'rox-appointment-booking'), esc_html($field)));
            }
        }
        if (!is_email($data['email'])) {
            throw new \Exception(esc_html__('Invalid email address', 'rox-appointment-booking'));
        }
        $existing = AgentModel::query()
            ->where('email', $data['email'])
            ->when($id, function($q) use ($id) {
                $q->where('id', '!=', $id);
            })
            ->first();
        if ($existing) {
            throw new \Exception(esc_html__('An agent with this email already exists', 'rox-appointment-booking'));
        }
        $agent = $id ? AgentModel::find($id) : new AgentModel();
        if ($id && !$agent) {
            throw new \Exception(esc_html__('Agent not found', 'rox-appointment-booking'));
        }
        $agent->fill($data);
        $agent->save();
        return $agent;
    }

	/**
	 * Deletes an agent.
	 *
	 * @param int $id
	 * @return bool
	 * @throws \Exception
	 */
    public function deleteAgent(int $id): bool
    {
        $agent = AgentModel::find($id);
        if (!$agent) {
            throw new \Exception(esc_html__('Agent not found', 'rox-appointment-booking'));
        }
        return $agent->delete();
    }

	/**
	 * Retrieves an agent by ID.
	 *
	 * @param int $id
	 * @return AgentModel|null
	 */
    public function getAgent(int $id): ?AgentModel
    {
        return AgentModel::find($id);
    }

	/**
	 * Checks whether an agent has appointments.
	 *
	 * @param int $agent_id
	 * @return bool
	 */
    public static function hasAppointments(int $agent_id): bool
    {
        $count = \RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel::query()
            ->where('agent_id', $agent_id)
            ->count();
        return $count > 0;
    }

    /**
     * Get weekly schedule by agent ID
     *
     * @param int $agent_id Agent ID
     * @return array Weekly schedule data with agent_id, is_enabled, and weekly_schedule
     */
    public function getWeeklySchedule(int $agent_id): array
    {
        $agent = $this->getAgent($agent_id);
        if (!$agent) {
            throw new \Exception(esc_html__('Agent not found', 'rox-appointment-booking'));
        }

        $schedule_data = is_string($agent->weekly_schedule) 
            ? json_decode($agent->weekly_schedule, true) 
            : $agent->weekly_schedule;

        return [
            'agent_id' => $agent_id,
            'is_enabled' => $schedule_data['enabled'] ?? false,
            'weekly_schedule' => $schedule_data['weekly_schedule'] ?? []
        ];
    }

    /**
     * Get holidays by agent ID
     *
     * @param int $agent_id Agent ID
     * @return array Holidays data with agent_id and holidays
     */
    public function getHolidays(int $agent_id): array
    {
        $agent = $this->getAgent($agent_id);
        if (!$agent) {
            throw new \Exception(esc_html__('Agent not found', 'rox-appointment-booking'));
        }

        $holidays_data = is_string($agent->holiday) 
            ? json_decode($agent->holiday, true) 
            : $agent->holiday;

        return [
            'agent_id' => $agent_id,
            'holidays' => $holidays_data ?? []
        ];
    }

    /**
     * Get special days by agent ID
     *
     * @param int $agent_id Agent ID
     * @return array Special days data with agent_id and special_days
     */
    public function getSpecialDays(int $agent_id): array
    {
        $agent = $this->getAgent($agent_id);
        if (!$agent) {
            throw new \Exception(esc_html__('Agent not found', 'rox-appointment-booking'));
        }

        $special_days_data = is_string($agent->special_days) 
            ? json_decode($agent->special_days, true) 
            : $agent->special_days;

        return [
            'agent_id' => $agent_id,
            'special_days' => $special_days_data ?? []
        ];
    }
}