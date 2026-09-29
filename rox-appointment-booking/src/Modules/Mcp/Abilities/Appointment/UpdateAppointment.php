<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment;

defined('ABSPATH') || exit;

/**
 * Class UpdateAppointment
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment
 * @description Changes an appointment's agent, service, location, attendees or payment status.
 */
class UpdateAppointment extends AbstractAppointmentWrite
{
    protected string $name = 'update-appointment';

    protected string $capability = 'appointment.edit';

    public function label(): string
    {
        return __('Update Appointment', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Changes the agent, service, location, number of attendees, extra services or payment status of an existing appointment. Only the fields you pass change. Changing the agent or service is refused when it would overlap another appointment. Use reschedule-appointment to move the date or time and change-appointment-status for the status. The order total follows a service change.';
    }

    protected function properties(): array
    {
        return array_merge($this->idProperty('Appointment id.'), [
            'agent_id'          => ['type' => 'integer', 'minimum' => 1, 'description' => 'New agent.'],
            'service_id'        => ['type' => 'integer', 'minimum' => 1, 'description' => 'New service.'],
            'location_id'       => ['type' => 'integer', 'minimum' => 1, 'description' => 'New location.'],
            'total_attendees'   => ['type' => 'integer', 'minimum' => 1, 'description' => 'New number of people.'],
            'extra_service_ids' => $this->idsProperty('Replaces the extra services.'),
            'payment_status'    => ['type' => 'string', 'enum' => array_values(array_filter(array_column(rox_appointment_booking_payment_statuses(), 'value'))), 'description' => 'New payment status.'],
        ]);
    }

    protected function required(): array
    {
        return ['id'];
    }

    protected function run(array $input)
    {
        $id     = absint($input['id'] ?? 0);
        $stored = $this->load($id);
        if (is_wp_error($stored)) {
            return $stored;
        }

        $changes = [];
        foreach (['agent_id', 'service_id', 'location_id', 'total_attendees'] as $key) {
            if (!empty($input[$key])) {
                $changes[$key] = absint($input[$key]);
            }
        }
        if (array_key_exists('extra_service_ids', $input)) {
            $changes['extra_services'] = $this->ids($input['extra_service_ids']);
        }
        if (!empty($input['payment_status'])) {
            $changes['payment_status'] = sanitize_key($input['payment_status']);
        }

        if (isset($changes['agent_id']) || isset($changes['service_id'])) {
            $conflict = $this->conflict($id, $stored, $changes);
            if ($conflict) {
                return $conflict;
            }
        }

        return $this->save($id, $stored, $changes);
    }
}
