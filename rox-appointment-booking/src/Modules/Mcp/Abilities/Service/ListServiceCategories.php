<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Service;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractAbility;

/**
 * Class ListServiceCategories
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Service
 * @description Lists service categories.
 */
class ListServiceCategories extends AbstractAbility
{
    protected string $name = 'list-service-categories';

    protected string $capability = 'service.view';

    public function label(): string
    {
        return __('List Service Categories', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Lists service categories with their id and title. Use a category id to filter list-services.';
    }

    protected function properties(): array
    {
        return [
            'search'      => ['type' => 'string', 'description' => 'Match on title or description.'],
            'location_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Only categories with services at this location.'],
        ];
    }

    protected function run(array $input)
    {
        $params = array_merge(['mode' => 'list'], array_filter([
            'search'      => sanitize_text_field($input['search'] ?? ''),
            'location_id' => absint($input['location_id'] ?? 0),
        ]));

        $result = $this->dispatch('GET', 'category', $params);
        if (is_wp_error($result)) {
            return $result;
        }

        $items = array_map(function ($row) {
            return ['id' => $row['value'] ?? null, 'title' => $row['label'] ?? ''];
        }, (array) $result['data']);

        return ['items' => array_values($items)];
    }
}
