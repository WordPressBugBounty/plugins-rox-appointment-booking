<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractAbility;

/**
 * Class GetAppointmentStatuses
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment
 * @description Lists the appointment statuses.
 */
class GetAppointmentStatuses extends AbstractAbility
{
    protected string $name = 'get-appointment-statuses';

    protected string $capability = 'appointment.view';

    public function label(): string
    {
        return __('Get Appointment Statuses', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Lists every appointment status with its machine value and display label. Use the value when filtering or changing a status.';
    }

    protected function properties(): array
    {
        return [];
    }

    protected function run(array $input)
    {
        $items = array_map(function ($status) {
            return ['value' => $status['value'] ?? '', 'label' => $status['label'] ?? ''];
        }, rox_appointment_booking_appointment_statuses());

        return ['items' => array_values($items)];
    }
}
