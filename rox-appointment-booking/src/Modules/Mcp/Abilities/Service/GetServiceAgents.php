<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Service;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractAbility;

/**
 * Class GetServiceAgents
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Service
 * @description Lists the agents who provide a service.
 */
class GetServiceAgents extends AbstractAbility
{
    protected string $name = 'get-service-agents';

    protected string $capability = 'agent.view';

    public function label(): string
    {
        return __('Get Service Agents', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Lists the agents assigned to a service, with contact details and status. Use it to choose an agent before get-available-slots or create-appointment.';
    }

    protected function properties(): array
    {
        return [
            'service_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Service id.'],
        ];
    }

    protected function required(): array
    {
        return ['service_id'];
    }

    protected function run(array $input)
    {
        $result = $this->dispatch('GET', 'agent', ['service_id' => absint($input['service_id'] ?? 0), 'per_page' => 100]);

        return is_wp_error($result) ? $result : ['items' => array_values((array) $result['data'])];
    }
}
