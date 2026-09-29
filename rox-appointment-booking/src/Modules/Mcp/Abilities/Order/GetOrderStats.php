<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Order;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractAbility;

/**
 * Class GetOrderStats
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Order
 * @description Returns order totals for a period.
 */
class GetOrderStats extends AbstractAbility
{
    protected string $name = 'get-order-stats';

    protected string $capability = 'order.view';

    public function label(): string
    {
        return __('Get Order Stats', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Returns order counts and revenue totals, broken down by status, for an optional creation date range (Y-m-d). Amounts are in the site currency. Use it for revenue questions.';
    }

    protected function properties(): array
    {
        return [
            'date_from' => ['type' => 'string', 'format' => 'date', 'description' => 'Period start (Y-m-d).'],
            'date_to'   => ['type' => 'string', 'format' => 'date', 'description' => 'Period end (Y-m-d).'],
        ];
    }

    protected function run(array $input)
    {
        $params = array_filter([
            'date_from' => $this->date($input['date_from'] ?? ''),
            'date_to'   => $this->date($input['date_to'] ?? ''),
        ]);

        $result = $this->dispatch('GET', 'orders/stats', $params);

        return is_wp_error($result) ? $result : (array) $result['data'];
    }
}
