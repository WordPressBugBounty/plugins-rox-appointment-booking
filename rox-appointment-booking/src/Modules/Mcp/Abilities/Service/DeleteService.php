<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Service;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractDeleteAbility;

/**
 * Class DeleteService
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Service
 * @description Permanently deletes a service.
 */
class DeleteService extends AbstractDeleteAbility
{
    protected string $name = 'delete-service';

    protected string $capability = 'service.delete';

    protected string $route = 'service';

    public function label(): string
    {
        return __('Delete Service', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Permanently deletes a service and its category, agent and location links. Refused while the service is used by appointments; set-service-status inactive is the safer choice. This cannot be undone, so confirm with the user first.';
    }
}
