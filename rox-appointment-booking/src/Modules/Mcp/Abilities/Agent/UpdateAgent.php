<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Agent;

defined('ABSPATH') || exit;

use WP_Error;
use RoxAppointmentBooking\Modules\Agent\Data\AgentModel;

/**
 * Class UpdateAgent
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Agent
 * @description Changes an agent's details.
 */
class UpdateAgent extends CreateAgent
{
    protected string $name = 'update-agent';

    protected string $capability = 'agent.edit';

    protected bool $idempotent = true;

    public function label(): string
    {
        return __('Update Agent', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return "Changes an agent's details. Only the fields you pass change; service_ids replaces the assigned services when given. A linked WordPress account gets the new name and e-mail too. allow_to_login true creates an account and e-mails its login details; false removes the link to the account (the account itself stays).";
    }

    protected function properties(): array
    {
        return array_merge($this->idProperty('Agent id.'), $this->fields());
    }

    protected function required(): array
    {
        return ['id'];
    }

    protected function run(array $input)
    {
        $id       = absint($input['id'] ?? 0);
        $readable = $this->readable('agent/' . $id);
        if (is_wp_error($readable)) {
            return $readable;
        }

        $stored = $this->stored(AgentModel::class, $id);
        if (!$stored) {
            return new WP_Error('not_found', 'Agent not found.', ['status' => 404]);
        }

        $payload = array_merge($stored, $this->payload($input));
        $payload['allow_to_login'] = array_key_exists('allow_to_login', $input)
            ? rest_sanitize_boolean($input['allow_to_login'])
            : !empty($stored['allow_to_login']);
        unset($payload['wp_user_id']);

        $result = $this->dispatch('PUT', 'agent/' . $id, $payload);

        return is_wp_error($result) ? $result : $this->fresh($id);
    }
}
