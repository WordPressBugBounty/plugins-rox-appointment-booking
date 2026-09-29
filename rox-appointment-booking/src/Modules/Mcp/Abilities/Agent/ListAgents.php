<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Agent;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractAbility;

/**
 * Class ListAgents
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Agent
 * @description Lists agents with pagination.
 */
class ListAgents extends AbstractAbility
{
    protected string $name = 'list-agents';

    protected string $capability = 'agent.view';

    public function label(): string
    {
        return __('List Agents', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Lists agents (staff who serve appointments), newest first, with e-mail, phone and status. Optional search matches name, e-mail, phone or job title; service_id limits to agents who provide that service.';
    }

    protected function properties(): array
    {
        return array_merge([
            'search'     => ['type' => 'string', 'description' => 'Match on name, e-mail, phone, title or bio.'],
            'status'     => ['type' => 'string', 'enum' => ['active', 'inactive'], 'description' => 'Only agents with this status.'],
            'service_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Only agents who provide this service.'],
        ], $this->paginationProperties());
    }

    protected function run(array $input)
    {
        $paging = $this->pageParams($input);
        $params = array_merge($paging, array_filter([
            'search'     => sanitize_text_field($input['search'] ?? ''),
            'status'     => sanitize_key($input['status'] ?? ''),
            'service_id' => absint($input['service_id'] ?? 0),
        ]));

        $result = $this->dispatch('GET', 'agent', $params);

        return is_wp_error($result) ? $result : $this->paged($result, $paging);
    }
}
