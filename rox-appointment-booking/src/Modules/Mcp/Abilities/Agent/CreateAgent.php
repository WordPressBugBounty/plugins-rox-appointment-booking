<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Agent;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractWriteAbility;

/**
 * Class CreateAgent
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Agent
 * @description Adds an agent.
 */
class CreateAgent extends AbstractWriteAbility
{
    protected string $name = 'create-agent';

    protected string $capability = 'agent.create';

    public function label(): string
    {
        return __('Create Agent', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Adds an agent (staff member who serves appointments). The e-mail must not already belong to another agent or a customer. Assign services with service_ids. With allow_to_login true a WordPress account with the agent role is created and its login details are e-mailed; by default no account is made. New agents are active and follow the business hours until a schedule is set in the dashboard. Returns the new agent.';
    }

    protected function properties(): array
    {
        return $this->fields();
    }

    protected function required(): array
    {
        return ['first_name', 'last_name', 'email'];
    }

    protected function run(array $input)
    {
        $payload = $this->payload($input);
        $payload['allow_to_login'] = rest_sanitize_boolean($input['allow_to_login'] ?? false);

        $result = $this->dispatch('POST', 'agent', $payload);
        if (is_wp_error($result)) {
            return $result;
        }

        $id = (int) ($result['data']['id'] ?? 0);

        return $id ? $this->fresh($id) : (array) $result['data'];
    }

    /**
     * Editable agent fields.
     *
     * @return array
     */
    protected function fields(): array
    {
        return [
            'first_name'     => ['type' => 'string', 'minLength' => 1, 'description' => 'First name.'],
            'last_name'      => ['type' => 'string', 'minLength' => 1, 'description' => 'Last name.'],
            'email'          => ['type' => 'string', 'format' => 'email', 'description' => 'E-mail address.'],
            'phone'          => ['type' => 'string', 'description' => 'Phone number with country code.'],
            'title'          => ['type' => 'string', 'description' => 'Job title, e.g. "Senior Stylist".'],
            'bio'            => ['type' => 'string', 'description' => 'Short biography.'],
            'status'         => ['type' => 'string', 'enum' => ['active', 'inactive'], 'description' => 'Whether the agent takes bookings.'],
            'service_ids'    => $this->idsProperty('Services the agent provides; replaces the current ones.'),
            'allow_to_login' => ['type' => 'boolean', 'description' => 'Give the agent a WordPress login (e-mails credentials).'],
        ];
    }

    /**
     * Sanitised agent fields present in the input.
     *
     * @param array $input
     * @return array
     */
    protected function payload(array $input): array
    {
        $payload = $this->texts($input, ['first_name', 'last_name', 'phone', 'title', 'status']);

        if (array_key_exists('email', $input)) {
            $payload['email'] = sanitize_email($input['email']);
        }
        if (array_key_exists('bio', $input)) {
            $payload['bio'] = sanitize_textarea_field((string) $input['bio']);
        }
        if (array_key_exists('service_ids', $input)) {
            $payload['service_ids'] = $this->ids($input['service_ids']);
        }

        return $payload;
    }

    /**
     * The agent as get-agent returns it.
     *
     * @param int $id
     * @return array|\WP_Error
     */
    protected function fresh(int $id)
    {
        $result = $this->dispatch('GET', 'agent/' . $id);
        if (is_wp_error($result)) {
            return $result;
        }

        $agent = (array) $result['data'];
        unset($agent['existing_user']);

        return $agent;
    }
}
