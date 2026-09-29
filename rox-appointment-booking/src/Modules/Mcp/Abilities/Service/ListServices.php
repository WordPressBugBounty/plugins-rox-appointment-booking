<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Service;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractAbility;
use RoxAppointmentBooking\Supports\Access\Permissions;

/**
 * Class ListServices
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Service
 * @description Lists services with pagination and filters.
 */
class ListServices extends AbstractAbility
{
    protected string $name = 'list-services';

    protected string $capability = 'service.view';

    public function label(): string
    {
        return __('List Services', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Lists bookable services with price (site currency), duration, status, categories and assigned agents. Agents only see the services assigned to them. Filters narrow by category, agent, location, status or text.';
    }

    protected function properties(): array
    {
        return array_merge([
            'search'      => ['type' => 'string', 'description' => 'Match on title or description.'],
            'status'      => ['type' => 'string', 'enum' => ['active', 'inactive'], 'description' => 'Only services with this status.'],
            'category_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Only services in this category.'],
            'agent_id'    => ['type' => 'integer', 'minimum' => 1, 'description' => 'Only services this agent provides.'],
            'location_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Only services offered at this location.'],
        ], $this->paginationProperties());
    }

    protected function run(array $input)
    {
        $paging = $this->pageParams($input);

        // Without service.edit the admin list is refused, so fall back to the caller's own services.
        if (!Permissions::can('service.edit')) {
            $result = $this->dispatch('GET', 'agent/my-services', array_filter(['search' => sanitize_text_field($input['search'] ?? '')]));
            if (is_wp_error($result)) {
                return $result;
            }

            $items = array_values((array) $result['data']);
            if (!empty($input['status'])) {
                $items = array_values(array_filter($items, function ($item) use ($input) {
                    return ($item['status'] ?? '') === $input['status'];
                }));
            }

            $total = count($items);
            $items = array_slice($items, ($paging['page'] - 1) * $paging['per_page'], $paging['per_page']);

            return $this->paged(['data' => $items, 'options' => ['total' => $total]], $paging);
        }

        $params = array_merge($paging, array_filter([
            'search'      => sanitize_text_field($input['search'] ?? ''),
            'status'      => sanitize_key($input['status'] ?? ''),
            'cat_id'      => absint($input['category_id'] ?? 0),
            'agent_id'    => absint($input['agent_id'] ?? 0),
            'location_id' => absint($input['location_id'] ?? 0),
        ]));

        $result = $this->dispatch('GET', 'service', $params);

        return is_wp_error($result) ? $result : $this->paged($result, $paging);
    }
}
