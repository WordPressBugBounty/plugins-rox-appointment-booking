<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment;

defined('ABSPATH') || exit;

use WP_Error;

/**
 * Class ChangeAppointmentStatus
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment
 * @description Sets an appointment's status.
 */
class ChangeAppointmentStatus extends AbstractAppointmentWrite
{
    protected string $name = 'change-appointment-status';

    protected string $capability = 'appointment.change_status';

    protected bool $idempotent = true;

    public function label(): string
    {
        return __('Change Appointment Status', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Sets the status of an appointment (see get-appointment-statuses). The linked order status follows. Side effect: when the status really changes, the same status e-mails as the dashboard are sent to the customer and agent.';
    }

    protected function properties(): array
    {
        return array_merge($this->idProperty('Appointment id.'), [
            'status' => ['type' => 'string', 'enum' => $this->statuses(), 'description' => 'New status.'],
        ]);
    }

    protected function required(): array
    {
        return ['id', 'status'];
    }

    protected function run(array $input)
    {
        return $this->setStatus(absint($input['id'] ?? 0), (string) ($input['status'] ?? ''));
    }

    /**
     * Save a new status on an appointment.
     *
     * @param int    $id
     * @param string $status
     * @return array|WP_Error
     */
    protected function setStatus(int $id, string $status)
    {
        if (!in_array($status, $this->statuses(), true)) {
            return new WP_Error('invalid_status', 'Unknown status. Call get-appointment-statuses.', ['status' => 400]);
        }

        $stored = $this->load($id);
        if (is_wp_error($stored)) {
            return $stored;
        }

        return $this->save($id, $stored, ['status' => $status]);
    }
}
