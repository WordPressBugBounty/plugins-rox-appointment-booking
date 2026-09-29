<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractAbility;

/**
 * Class ListAppointments
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment
 * @description Lists appointments with filters and pagination.
 */
class ListAppointments extends AbstractAbility
{
    protected string $name = 'list-appointments';

    protected string $capability = 'appointment.view';

    public function label(): string
    {
        return __('List Appointments', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Lists appointments, newest booked first, with optional filters. Returns a short summary per appointment (id, date, time, service, customer, agent, duration, status); call get-appointment with an id for full details. Dates are Y-m-d in the site timezone. Agents only ever see appointments assigned to them.';
    }

    protected function properties(): array
    {
        $statuses = array_values(array_filter(array_column(rox_appointment_booking_appointment_statuses(), 'value')));

        return array_merge([
            'status'      => ['type' => 'string', 'enum' => $statuses, 'description' => 'Only appointments with this status.'],
            'date_from'   => ['type' => 'string', 'format' => 'date', 'description' => 'Earliest appointment date (Y-m-d), inclusive.'],
            'date_to'     => ['type' => 'string', 'format' => 'date', 'description' => 'Latest appointment date (Y-m-d), inclusive.'],
            'agent_id'    => ['type' => 'integer', 'minimum' => 1, 'description' => 'Only appointments served by this agent.'],
            'service_id'  => ['type' => 'integer', 'minimum' => 1, 'description' => 'Only appointments for this service.'],
            'customer_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Only appointments booked by this customer.'],
            'location_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Only appointments at this location.'],
            'search'      => ['type' => 'string', 'description' => 'Free-text match on appointment id, service details, status or payment status.'],
        ], $this->paginationProperties());
    }

    protected function run(array $input)
    {
        $page    = max(1, absint($input['page'] ?? 1));
        $perPage = min(100, max(1, absint($input['per_page'] ?? 20)));
        $params  = ['page' => $page, 'per_page' => $perPage];

        if (!empty($input['status'])) {
            $params['status'] = sanitize_key($input['status']);
        }

        foreach (['agent_id', 'service_id', 'customer_id', 'location_id'] as $key) {
            if (!empty($input[$key])) {
                $params[$key] = absint($input[$key]);
            }
        }

        if (!empty($input['search'])) {
            $params['search'] = sanitize_text_field($input['search']);
        }

        $from = $this->date($input['date_from'] ?? '');
        $to   = $this->date($input['date_to'] ?? '');
        if ($from || $to) {
            $params['date'] = wp_json_encode([$from ?: '1970-01-01', $to ?: '9999-12-31']);
        }

        $result = $this->dispatch('GET', 'appointment', $params);
        if (is_wp_error($result)) {
            return $result;
        }

        $items = [];
        foreach ((array) $result['data'] as $group) {
            foreach ((array) ($group['appointments'] ?? []) as $appointment) {
                $items[] = array_merge(['date' => $group['date'] ?? ''], $appointment);
            }
        }

        $meta = $result['options'];

        return [
            'items'       => $items,
            'total'       => (int) ($meta['total'] ?? count($items)),
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ($meta['total_pages'] ?? 1),
        ];
    }
}
