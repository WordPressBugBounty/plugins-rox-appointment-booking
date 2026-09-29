<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Calendar;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractAbility;

/**
 * Class GetAvailableSlots
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Calendar
 * @description Returns bookable time slots for a day.
 */
class GetAvailableSlots extends AbstractAbility
{
    protected string $name = 'get-available-slots';

    protected string $capability = 'calendar.view';

    public function label(): string
    {
        return __('Get Available Slots', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Returns the time slots on one day for a service (optionally for a specific agent and location) and which of them are still free. Call this before create-appointment to pick a valid start time. Date is Y-m-d, times are in the site timezone. Holidays and days off return no slots.';
    }

    protected function properties(): array
    {
        return [
            'date'        => ['type' => 'string', 'format' => 'date', 'description' => 'Day to check (Y-m-d).'],
            'service_id'  => ['type' => 'integer', 'minimum' => 1, 'description' => 'Service to book.'],
            'agent_id'    => ['type' => 'integer', 'minimum' => 1, 'description' => 'Agent to book with; omit to use the service schedule.'],
            'location_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Location, when locations are in use.'],
        ];
    }

    protected function required(): array
    {
        return ['date', 'service_id'];
    }

    protected function run(array $input)
    {
        $params = array_filter([
            'date'        => $this->date($input['date'] ?? ''),
            'service_id'  => absint($input['service_id'] ?? 0),
            'agent_id'    => absint($input['agent_id'] ?? 0),
            'location_id' => absint($input['location_id'] ?? 0),
        ]);

        $result = $this->dispatch('GET', 'calendar/slots', $params);

        return is_wp_error($result) ? $result : (array) $result['data'];
    }
}
