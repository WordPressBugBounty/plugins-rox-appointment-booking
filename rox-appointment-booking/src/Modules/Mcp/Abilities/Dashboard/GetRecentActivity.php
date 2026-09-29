<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Dashboard;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractAbility;

/**
 * Class GetRecentActivity
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Dashboard
 * @description Returns the latest activity feed.
 */
class GetRecentActivity extends AbstractAbility
{
    protected string $name = 'get-recent-activity';

    protected string $capability = 'dashboard.view';

    public function label(): string
    {
        return __('Get Recent Activity', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Returns the latest activity feed shown on the dashboard: new bookings, status changes, payments and similar events, newest first.';
    }

    protected function properties(): array
    {
        return [];
    }

    protected function run(array $input)
    {
        $result = $this->dispatch('GET', 'dashboard/recent-activity');

        return is_wp_error($result) ? $result : ['items' => $result['data']];
    }
}
