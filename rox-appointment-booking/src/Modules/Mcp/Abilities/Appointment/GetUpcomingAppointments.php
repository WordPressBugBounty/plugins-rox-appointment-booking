<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment;

defined('ABSPATH') || exit;

/**
 * Class GetUpcomingAppointments
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment
 * @description Returns the next appointments from now.
 */
class GetUpcomingAppointments extends AbstractAgenda
{
    protected string $name = 'get-upcoming-appointments';

    public function label(): string
    {
        return __('Get Upcoming Appointments', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Returns the next appointments from now, soonest first, within a look-ahead window of days. Cancelled and rejected appointments are left out unless a status is given. Agents only see their own appointments.';
    }

    protected function properties(): array
    {
        return array_merge([
            'days'  => ['type' => 'integer', 'minimum' => 1, 'maximum' => 90, 'default' => 7, 'description' => 'How many days ahead to look, including today.'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 10, 'description' => 'Maximum appointments to return.'],
        ], $this->filterProperties());
    }

    protected function run(array $input)
    {
        $days  = min(90, max(1, absint($input['days'] ?? 7)));
        $limit = min(100, max(1, absint($input['limit'] ?? 10)));
        $now   = current_datetime();
        $start = $now->format('Y-m-d');
        $end   = $now->modify('+' . ($days - 1) . ' days')->format('Y-m-d');

        $filters = $this->filterParams($input);
        $items   = $this->agenda($start, $end, $filters);
        if (is_wp_error($items)) {
            return $items;
        }

        $nowTs = strtotime($now->format('Y-m-d H:i'));
        $items = array_values(array_filter($items, function ($item) use ($filters, $nowTs) {
            if (empty($filters['status']) && in_array($item['status'], ['cancelled', 'rejected'], true)) {
                return false;
            }
            return strtotime($item['date'] . ' ' . $item['time']) >= $nowTs;
        }));

        return ['from' => $start, 'to' => $end, 'items' => array_slice($items, 0, $limit)];
    }
}
