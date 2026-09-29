<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment;

defined('ABSPATH') || exit;

/**
 * Class CancelAppointment
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment
 * @description Cancels an appointment.
 */
class CancelAppointment extends ChangeAppointmentStatus
{
    protected string $name = 'cancel-appointment';

    protected string $capability = 'appointment.cancel';

    public function label(): string
    {
        return __('Cancel Appointment', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Cancels an appointment (status "cancelled"). The record and its order stay and nothing is refunded (see refund-order). Side effect: the cancellation e-mails are sent to the customer and agent.';
    }

    protected function properties(): array
    {
        return $this->idProperty('Appointment id.');
    }

    protected function required(): array
    {
        return ['id'];
    }

    protected function run(array $input)
    {
        return $this->setStatus(absint($input['id'] ?? 0), 'cancelled');
    }
}
