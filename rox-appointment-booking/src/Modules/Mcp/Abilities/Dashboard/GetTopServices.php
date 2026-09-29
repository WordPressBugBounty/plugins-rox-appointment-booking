<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Dashboard;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractAbility;

/**
 * Class GetTopServices
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Dashboard
 * @description Returns the most booked services.
 */
class GetTopServices extends AbstractAbility
{
    protected string $name = 'get-top-services';

    protected string $capability = 'dashboard.view';

    public function label(): string
    {
        return __('Get Top Services', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Returns the most booked services with their booking count and revenue for an optional date range (Y-m-d). Amounts are in the site currency.';
    }

    protected function properties(): array
    {
        return [
            'start_date' => ['type' => 'string', 'format' => 'date', 'description' => 'Period start (Y-m-d).'],
            'end_date'   => ['type' => 'string', 'format' => 'date', 'description' => 'Period end (Y-m-d).'],
            'limit'      => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 10, 'description' => 'How many services to return.'],
        ];
    }

    protected function run(array $input)
    {
        $params = array_filter([
            'start_date' => $this->date($input['start_date'] ?? ''),
            'end_date'   => $this->date($input['end_date'] ?? ''),
            'limit'      => min(50, max(1, absint($input['limit'] ?? 10))),
        ]);

        $result = $this->dispatch('GET', 'dashboard/top-services', $params);

        if (is_wp_error($result)) {
            return $result;
        }

        $data = (array) $result['data'];

        return isset($data['items']) ? $data : ['items' => array_values($data)];
    }
}
