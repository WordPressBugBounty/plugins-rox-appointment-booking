<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Calendar;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractAbility;

/**
 * Class ListHolidays
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Calendar
 * @description Lists holidays and days off.
 */
class ListHolidays extends AbstractAbility
{
    protected string $name = 'list-holidays';

    protected string $capability = 'calendar.view';

    public function label(): string
    {
        return __('List Holidays', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Without dates: lists the business holidays as configured. With start_date and end_date (Y-m-d): lists which dates in that range are holidays or weekly days off and therefore cannot be booked.';
    }

    protected function properties(): array
    {
        return [
            'start_date' => ['type' => 'string', 'format' => 'date', 'description' => 'Range start (Y-m-d).'],
            'end_date'   => ['type' => 'string', 'format' => 'date', 'description' => 'Range end (Y-m-d).'],
        ];
    }

    protected function run(array $input)
    {
        $start = $this->date($input['start_date'] ?? '');
        $end   = $this->date($input['end_date'] ?? '');

        $result = ($start && $end)
            ? $this->dispatch('GET', 'calendar/check-holiday', ['start_date' => $start, 'end_date' => $end])
            : $this->dispatch('GET', 'menueapi/holiday/get');

        return is_wp_error($result) ? $result : (array) $result['data'];
    }
}
