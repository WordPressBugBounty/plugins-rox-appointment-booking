<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Service;

defined('ABSPATH') || exit;

use WP_Error;
use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractAbility;
use RoxAppointmentBooking\Supports\Access\Permissions;

/**
 * Class GetService
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Service
 * @description Returns one service in full.
 */
class GetService extends AbstractAbility
{
    protected string $name = 'get-service';

    protected string $capability = 'service.view';

    public function label(): string
    {
        return __('Get Service', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Returns one service in full: title, description, price, duration, capacity, deposit, categories, assigned agents, locations, booking window and whether it can be booked without an agent. Agents can only open services assigned to them.';
    }

    protected function properties(): array
    {
        return $this->idProperty('Service id.');
    }

    protected function required(): array
    {
        return ['id'];
    }

    protected function run(array $input)
    {
        $id = absint($input['id'] ?? 0);

        if (!Permissions::can('service.edit')) {
            $result = $this->dispatch('GET', 'agent/my-services');
            if (is_wp_error($result)) {
                return $result;
            }

            foreach ((array) $result['data'] as $service) {
                if ((int) ($service['id'] ?? 0) === $id) {
                    return $service;
                }
            }

            return new WP_Error('not_found', 'Service not found.', ['status' => 404]);
        }

        $result = $this->dispatch('GET', 'service/' . $id);

        return is_wp_error($result) ? $result : (array) $result['data'];
    }
}
