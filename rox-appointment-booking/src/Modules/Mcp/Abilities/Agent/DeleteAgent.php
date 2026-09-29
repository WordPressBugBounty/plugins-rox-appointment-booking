<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Agent;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractDeleteAbility;

/**
 * Class DeleteAgent
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Agent
 * @description Permanently deletes an agent.
 */
class DeleteAgent extends AbstractDeleteAbility
{
    protected string $name = 'delete-agent';

    protected string $capability = 'agent.delete';

    protected string $route = 'agent';

    public function label(): string
    {
        return __('Delete Agent', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return "Permanently deletes an agent. Refused while the agent still has appointments; update-agent with status inactive is the safer choice. A linked WordPress account is not deleted. This cannot be undone, so confirm with the user first.";
    }
}
