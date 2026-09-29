<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Agent;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment\ListAppointments;

/**
 * Class GetAgentAppointments
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Agent
 * @description Lists one agent's appointments.
 */
class GetAgentAppointments extends ListAppointments
{
    protected string $name = 'get-agent-appointments';

    public function label(): string
    {
        return __('Get Agent Appointments', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return "Lists the appointments one agent serves, newest booked first, with the same filters and summary fields as list-appointments. Agents only ever get their own.";
    }

    protected function required(): array
    {
        return ['agent_id'];
    }
}
