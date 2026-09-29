<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Service;

defined('ABSPATH') || exit;

/**
 * Class SetServiceStatus
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Service
 * @description Enables or disables a service.
 */
class SetServiceStatus extends UpdateService
{
    protected string $name = 'set-service-status';

    public function label(): string
    {
        return __('Set Service Status', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Enables (active) or disables (inactive) a service. Inactive services cannot be booked from the booking panel; existing appointments are not touched.';
    }

    protected function properties(): array
    {
        return array_merge($this->idProperty('Service id.'), [
            'status' => ['type' => 'string', 'enum' => ['active', 'inactive'], 'description' => 'New status.'],
        ]);
    }

    protected function required(): array
    {
        return ['id', 'status'];
    }

    protected function run(array $input)
    {
        $status = ($input['status'] ?? '') === 'inactive' ? 'inactive' : 'active';

        return $this->update(absint($input['id'] ?? 0), ['status' => $status]);
    }
}
