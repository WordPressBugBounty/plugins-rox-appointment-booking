<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment;

defined('ABSPATH') || exit;

use WP_Error;

/**
 * Class RescheduleAppointment
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment
 * @description Moves an appointment to another date and/or time.
 */
class RescheduleAppointment extends AbstractAppointmentWrite
{
    protected string $name = 'reschedule-appointment';

    protected string $capability = 'appointment.reschedule';

    public function label(): string
    {
        return __('Reschedule Appointment', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Moves an appointment to a new date and, optionally, a new start time (omit time to keep the current one). Refused with a conflict when the agent or customer is already booked then; call get-available-slots first. Date is Y-m-d and time H:i in the site timezone. Side effect: the reschedule e-mail is sent to the customer and agent.';
    }

    protected function properties(): array
    {
        return array_merge($this->idProperty('Appointment id.'), [
            'date' => ['type' => 'string', 'format' => 'date', 'description' => 'New day (Y-m-d).'],
            'time' => ['type' => 'string', 'pattern' => '^\d{2}:\d{2}$', 'description' => 'New start time (H:i, 24-hour); omit to keep the current time.'],
        ]);
    }

    protected function required(): array
    {
        return ['id', 'date'];
    }

    protected function run(array $input)
    {
        $id   = absint($input['id'] ?? 0);
        $date = $this->date($input['date'] ?? '');
        if (!$date) {
            return new WP_Error('invalid_date', 'date must be Y-m-d.', ['status' => 400]);
        }

        $stored = $this->load($id);
        if (is_wp_error($stored)) {
            return $stored;
        }

        $currentTime = substr((string) ($stored['start_time'] ?? ''), 11, 5);
        $time        = !empty($input['time']) ? (string) $input['time'] : $currentTime;
        $start       = $this->dateTime($date, $time);
        if (!$start) {
            return new WP_Error('invalid_time', 'time must be H:i.', ['status' => 400]);
        }

        $holiday = $this->dispatch('GET', 'calendar/check-holiday', ['date' => $date]);
        if (!is_wp_error($holiday) && !empty($holiday['data']['is_disabled'])) {
            return new WP_Error('day_closed', 'That date is a holiday or a day off.', ['status' => 409]);
        }

        // A date-only move keeps the time, which is exactly what the calendar's own reschedule does.
        if ($time === $currentTime) {
            $result = $this->dispatch('PATCH', 'calendar/appointment/reschedule', ['id' => $id, 'date' => $date]);

            return is_wp_error($result) ? $result : $this->fresh($id);
        }

        $changes  = ['date' => $date, 'start_time' => $start];
        $conflict = $this->conflict($id, $stored, $changes);
        if ($conflict) {
            return $conflict;
        }

        return $this->save($id, $stored, $changes);
    }
}
