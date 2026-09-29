<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Agent;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\Service\ListServices;

/**
 * Class GetAgentServices
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Agent
 * @description Lists the services one agent provides.
 */
class GetAgentServices extends ListServices
{
    protected string $name = 'get-agent-services';

    public function label(): string
    {
        return __('Get Agent Services', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Lists the services one agent provides, with price, duration and status. Agents calling this always get their own services.';
    }

    protected function properties(): array
    {
        return array_merge([
            'agent_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Agent id.'],
        ], $this->paginationProperties());
    }

    protected function required(): array
    {
        return ['agent_id'];
    }
}
