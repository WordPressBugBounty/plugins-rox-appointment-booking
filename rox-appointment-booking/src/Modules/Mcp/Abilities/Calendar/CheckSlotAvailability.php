<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Calendar;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractAbility;

/**
 * Class CheckSlotAvailability
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Calendar
 * @description Checks whether one slot can be booked.
 */
class CheckSlotAvailability extends AbstractAbility
{
    protected string $name = 'check-slot-availability';

    protected string $capability = 'calendar.view';

    public function label(): string
    {
        return __('Check Slot Availability', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Checks whether one start time on a day is free for a service and agent, and says why not when it is taken. Use it to confirm a time the user asked for before creating or rescheduling an appointment.';
    }

    protected function properties(): array
    {
        return [
            'date'        => ['type' => 'string', 'format' => 'date', 'description' => 'Day (Y-m-d).'],
            'time'        => ['type' => 'string', 'pattern' => '^\d{2}:\d{2}$', 'description' => 'Start time (H:i, 24-hour, site timezone).'],
            'service_id'  => ['type' => 'integer', 'minimum' => 1, 'description' => 'Service to book.'],
            'agent_id'    => ['type' => 'integer', 'minimum' => 1, 'description' => 'Agent to book with.'],
            'location_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Location, when locations are in use.'],
        ];
    }

    protected function required(): array
    {
        return ['date', 'time', 'service_id', 'agent_id'];
    }

    protected function run(array $input)
    {
        $params = array_filter([
            'date'        => $this->date($input['date'] ?? ''),
            'slot'        => sanitize_text_field($input['time'] ?? ''),
            'service_id'  => absint($input['service_id'] ?? 0),
            'agent_id'    => absint($input['agent_id'] ?? 0),
            'location_id' => absint($input['location_id'] ?? 0),
        ]);

        $result = $this->dispatch('GET', 'calendar/check-availability', $params);

        return is_wp_error($result) ? $result : (array) $result['data'];
    }
}
