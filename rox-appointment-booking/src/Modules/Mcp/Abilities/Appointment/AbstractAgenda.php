<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractAbility;

/**
 * Class AbstractAgenda
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment
 * @description Shared day-agenda lookup over the calendar endpoint.
 */
abstract class AbstractAgenda extends AbstractAbility
{
    protected string $capability = 'calendar.view';

    /**
     * Appointments between two dates, sorted by date and start time.
     *
     * @param string $start
     * @param string $end
     * @param array  $filters
     * @return array|\WP_Error
     */
    protected function agenda(string $start, string $end, array $filters = [])
    {
        $params = array_merge(['start' => $start, 'end' => $end], array_filter($filters));
        $result = $this->dispatch('GET', 'calendar', $params);
        if (is_wp_error($result)) {
            return $result;
        }

        $items = [];
        foreach ((array) ($result['data']['events'] ?? []) as $event) {
            $props   = (array) ($event['extendedProps'] ?? []);
            $items[] = [
                'id'             => (int) ($props['appointment_id'] ?? $event['id'] ?? 0),
                'date'           => $event['start'] ?? '',
                'time'           => $props['time'] ?? '',
                'service_id'     => $props['service_id'] ?? null,
                'service'        => $props['service_name'] ?? '',
                'customer_id'    => $props['customer_id'] ?? null,
                'customer'       => $props['customer_name'] ?? '',
                'agent_id'       => $props['agent_id'] ?? null,
                'agent'          => $props['agent_name'] ?? '',
                'location'       => $props['location_name'] ?? '',
                'status'         => $props['status'] ?? '',
                'payment_status' => $props['payment_status'] ?? '',
            ];
        }

        usort($items, function ($a, $b) {
            return strtotime($a['date'] . ' ' . $a['time']) <=> strtotime($b['date'] . ' ' . $b['time']);
        });

        return $items;
    }

    /**
     * Shared agent/service/status filter properties.
     *
     * @return array
     */
    protected function filterProperties(): array
    {
        return [
            'agent_id'   => ['type' => 'integer', 'minimum' => 1, 'description' => 'Only appointments served by this agent.'],
            'service_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Only appointments for this service.'],
            'status'     => ['type' => 'string', 'enum' => array_values(array_column(rox_appointment_booking_appointment_statuses(), 'value')), 'description' => 'Only appointments with this status.'],
        ];
    }

    /**
     * Filter params from input.
     *
     * @param array $input
     * @return array
     */
    protected function filterParams(array $input): array
    {
        return [
            'agent_id'   => absint($input['agent_id'] ?? 0),
            'service_id' => absint($input['service_id'] ?? 0),
            'status'     => sanitize_key($input['status'] ?? ''),
        ];
    }
}
