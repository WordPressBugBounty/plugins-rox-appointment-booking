<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Dashboard;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractAbility;

/**
 * Class GetDashboardStats
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Dashboard
 * @description Returns the dashboard headline numbers.
 */
class GetDashboardStats extends AbstractAbility
{
    protected string $name = 'get-dashboard-stats';

    protected string $capability = 'dashboard.view';

    public function label(): string
    {
        return __('Get Dashboard Stats', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Returns the dashboard headline numbers (appointments, revenue, customers and their change against the previous period) for an optional date range (Y-m-d), agent or service. Amounts are in the site currency.';
    }

    protected function properties(): array
    {
        return $this->periodProperties();
    }

    protected function run(array $input)
    {
        $result = $this->dispatch('GET', 'dashboard/stats', $this->periodParams($input));

        return is_wp_error($result) ? $result : (array) $result['data'];
    }

    /**
     * Shared period and agent/service filter properties.
     *
     * @return array
     */
    protected function periodProperties(): array
    {
        return [
            'start_date' => ['type' => 'string', 'format' => 'date', 'description' => 'Period start (Y-m-d).'],
            'end_date'   => ['type' => 'string', 'format' => 'date', 'description' => 'Period end (Y-m-d).'],
            'agent_id'   => ['type' => 'integer', 'minimum' => 1, 'description' => 'Only this agent.'],
            'service_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Only this service.'],
        ];
    }

    /**
     * Period params from input.
     *
     * @param array $input
     * @return array
     */
    protected function periodParams(array $input): array
    {
        return array_filter([
            'start_date' => $this->date($input['start_date'] ?? ''),
            'end_date'   => $this->date($input['end_date'] ?? ''),
            'agent_id'   => absint($input['agent_id'] ?? 0),
            'service_id' => absint($input['service_id'] ?? 0),
        ]);
    }
}
