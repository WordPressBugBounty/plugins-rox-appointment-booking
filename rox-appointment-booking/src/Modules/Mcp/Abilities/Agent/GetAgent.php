<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Agent;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractAbility;

/**
 * Class GetAgent
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Agent
 * @description Returns one agent's profile.
 */
class GetAgent extends AbstractAbility
{
    protected string $name = 'get-agent';

    protected string $capability = 'agent.view';

    public function label(): string
    {
        return __('Get Agent', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return "Returns one agent's profile: name, job title, contact details, status, assigned service ids, location, bio, experience, weekly schedule, special days and holidays.";
    }

    protected function properties(): array
    {
        return $this->idProperty('Agent id.');
    }

    protected function required(): array
    {
        return ['id'];
    }

    protected function run(array $input)
    {
        $result = $this->dispatch('GET', 'agent/' . absint($input['id'] ?? 0));
        if (is_wp_error($result)) {
            return $result;
        }

        // The linked WordPress login name is not the AI's business.
        $agent = (array) $result['data'];
        unset($agent['existing_user']);

        return $agent;
    }
}
