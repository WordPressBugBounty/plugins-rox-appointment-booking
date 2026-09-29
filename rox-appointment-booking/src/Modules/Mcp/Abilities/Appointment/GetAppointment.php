<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractAbility;

/**
 * Class GetAppointment
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment
 * @description Returns one appointment in full detail.
 */
class GetAppointment extends AbstractAbility
{
    protected string $name = 'get-appointment';

    protected string $capability = 'appointment.view';

    public function label(): string
    {
        return __('Get Appointment', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Returns one appointment in full: service, extra services, agent, customer contact, date and start/end time (site timezone), status, payment status and price breakdown. Order and price fields are omitted for users who may not view orders. Use list-appointments first if you do not know the id.';
    }

    protected function properties(): array
    {
        return [
            'id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Appointment id.'],
        ];
    }

    protected function required(): array
    {
        return ['id'];
    }

    protected function run(array $input)
    {
        $result = $this->dispatch('GET', 'appointment/' . absint($input['id'] ?? 0));

        return is_wp_error($result) ? $result : (array) $result['data'];
    }
}
