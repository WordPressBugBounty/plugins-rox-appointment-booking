<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Service;

defined('ABSPATH') || exit;

use WP_Error;
use RoxAppointmentBooking\Modules\Service\Data\ServiceModel;

/**
 * Class UpdateService
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Service
 * @description Changes a service.
 */
class UpdateService extends CreateService
{
    protected string $name = 'update-service';

    protected string $capability = 'service.edit';

    protected bool $idempotent = true;

    public function label(): string
    {
        return __('Update Service', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Changes a service. Only the fields you pass change; category_ids and agent_ids replace the current lists when given. Price is in the site currency, duration in minutes. Existing appointments keep their booked time.';
    }

    protected function properties(): array
    {
        return array_merge($this->idProperty('Service id.'), $this->fields());
    }

    protected function required(): array
    {
        return ['id'];
    }

    protected function run(array $input)
    {
        return $this->update(absint($input['id'] ?? 0), $this->payload($input));
    }

    /**
     * Save changes on top of the stored service.
     *
     * @param int   $id
     * @param array $changes
     * @return array|WP_Error
     */
    protected function update(int $id, array $changes)
    {
        $readable = $this->readable('service/' . $id);
        if (is_wp_error($readable)) {
            return $readable;
        }

        $stored = $this->stored(ServiceModel::class, $id);
        if (!$stored) {
            return new WP_Error('not_found', 'Service not found.', ['status' => 404]);
        }
        unset($stored['created_by'], $stored['updated_by']);

        $result = $this->dispatch('PUT', 'service/' . $id, array_merge($stored, $changes));

        return is_wp_error($result) ? $result : $this->fresh($id);
    }
}
