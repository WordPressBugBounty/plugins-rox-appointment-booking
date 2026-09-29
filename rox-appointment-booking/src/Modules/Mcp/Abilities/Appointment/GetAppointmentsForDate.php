<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment;

defined('ABSPATH') || exit;

use WP_Error;

/**
 * Class GetAppointmentsForDate
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment
 * @description Returns the agenda for one day.
 */
class GetAppointmentsForDate extends AbstractAgenda
{
    protected string $name = 'get-appointments-for-date';

    public function label(): string
    {
        return __('Get Appointments for Date', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Returns every appointment on one day, sorted by start time, with service, customer, agent, location and status. Use this for "what is booked on <day>" questions; the date is Y-m-d in the site timezone. Agents only see their own appointments.';
    }

    protected function properties(): array
    {
        return array_merge([
            'date' => ['type' => 'string', 'format' => 'date', 'description' => 'Day to look at (Y-m-d).'],
        ], $this->filterProperties());
    }

    protected function required(): array
    {
        return ['date'];
    }

    protected function run(array $input)
    {
        $date = $this->date($input['date'] ?? '');
        if (!$date) {
            return new WP_Error('invalid_date', 'date must be Y-m-d.', ['status' => 400]);
        }

        $items = $this->agenda($date, $date, $this->filterParams($input));

        return is_wp_error($items) ? $items : ['date' => $date, 'total' => count($items), 'items' => $items];
    }
}
