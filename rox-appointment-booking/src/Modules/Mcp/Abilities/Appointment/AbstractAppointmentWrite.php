<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment;

defined('ABSPATH') || exit;

use WP_Error;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractWriteAbility;
use RoxAppointmentBooking\Modules\Service\Data\ServiceModel;

/**
 * Class AbstractAppointmentWrite
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment
 * @description Shared load, merge, overlap check and save for appointment changes.
 */
abstract class AbstractAppointmentWrite extends AbstractWriteAbility
{
    /**
     * Keys the appointment save handler reads; anything left out is reset by it.
     */
    private const PAYLOAD_KEYS = [
        'location_id', 'category_id', 'service_id', 'agent_id', 'customer_id',
        'date', 'start_time', 'end_time', 'extra_services', 'coupon_id',
        'status', 'payment_status', 'total_attendees', 'internal_notes', 'send_notification',
    ];

    /**
     * Stored appointment the caller may read, or an error.
     *
     * @param int $id
     * @return array|WP_Error
     */
    protected function load(int $id)
    {
        $readable = $this->readable('appointment/' . $id);
        if (is_wp_error($readable)) {
            return $readable;
        }

        $stored = $this->stored(AppointmentModel::class, $id);

        return $stored ?? new WP_Error('not_found', 'Appointment not found.', ['status' => 404]);
    }

    /**
     * Save stored values with changes applied, then return the fresh record.
     *
     * @param int   $id
     * @param array $stored
     * @param array $changes
     * @return array|WP_Error
     */
    protected function save(int $id, array $stored, array $changes)
    {
        $payload = array_intersect_key(array_merge($stored, $changes), array_flip(self::PAYLOAD_KEYS));
        $payload['extra_services'] = array_values(array_filter((array) ($payload['extra_services'] ?? [])));

        $result = $this->dispatch('PUT', 'appointment/' . $id, $payload);
        if (is_wp_error($result)) {
            return $result;
        }

        return $this->fresh($id);
    }

    /**
     * The appointment as get-appointment returns it.
     *
     * @param int $id
     * @return array|WP_Error
     */
    protected function fresh(int $id)
    {
        $result = $this->dispatch('GET', 'appointment/' . $id);

        return is_wp_error($result) ? $result : (array) $result['data'];
    }

    /**
     * 'Y-m-d H:i:s' from a date and an H:i time, or '' when either is invalid.
     *
     * @param string $date
     * @param mixed  $time
     * @return string
     */
    protected function dateTime(string $date, $time): string
    {
        $time = sanitize_text_field((string) $time);
        if (!$date || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            return '';
        }

        return $date . ' ' . $time . ':00';
    }

    /**
     * Refuse a move that would overlap the agent's or the customer's other appointments.
     *
     * @param int    $id      Appointment being changed (excluded from the check).
     * @param array  $stored  Its stored values.
     * @param array  $changes New agent_id / service_id / date / start_time.
     * @return WP_Error|null
     */
    protected function conflict(int $id, array $stored, array $changes): ?WP_Error
    {
        $next      = array_merge($stored, $changes);
        $date      = (string) ($next['date'] ?? '');
        $start     = (string) ($next['start_time'] ?? '');
        $serviceId = (int) ($next['service_id'] ?? 0);
        $agentId   = (int) ($next['agent_id'] ?? 0);

        if (!$date || !$start) {
            return null;
        }

        // Same service keeps its booked length; a new service brings its own.
        $minutes = (int) round((strtotime((string) ($stored['end_time'] ?? '')) - strtotime((string) ($stored['start_time'] ?? ''))) / 60);
        if ($serviceId !== (int) ($stored['service_id'] ?? 0) || $minutes <= 0) {
            $service = ServiceModel::find($serviceId);
            $minutes = $service ? (int) $service->duration : 0;
        }
        $end = gmdate('Y-m-d H:i:s', strtotime($start) + max(1, $minutes) * 60);

        $overlapping = function () use ($id, $date, $start, $end) {
            return AppointmentModel::where('date', $date)
                ->where('id', '!=', $id)
                ->whereNotIn('status', ['cancelled', 'canceled', 'rejected'])
                ->where(function ($query) use ($start, $end) {
                    $query->where('start_time', '<', $end)->where('end_time', '>', $start);
                });
        };

        if ($agentId && $overlapping()->where('agent_id', $agentId)->first()) {
            return new WP_Error('slot_already_booked', 'The agent already has an appointment at that time. Call get-available-slots to find a free time.', ['status' => 409]);
        }

        if (!$agentId && $serviceId) {
            $service  = ServiceModel::find($serviceId);
            $capacity = $service && (int) $service->without_agent_capacity > 0 ? (int) $service->without_agent_capacity : 1;
            if ($overlapping()->where('service_id', $serviceId)->count() >= $capacity) {
                return new WP_Error('slot_already_booked', 'That time is fully booked for this service. Call get-available-slots to find a free time.', ['status' => 409]);
            }
        }

        $customerId = (int) ($next['customer_id'] ?? 0);
        if ($customerId && $overlapping()->where('customer_id', $customerId)->first()) {
            return new WP_Error('customer_double_booked', 'The customer already has another appointment at that time.', ['status' => 409]);
        }

        return null;
    }

    /**
     * Appointment status enum values.
     *
     * @return string[]
     */
    protected function statuses(): array
    {
        return array_values(array_filter(array_column(rox_appointment_booking_appointment_statuses(), 'value')));
    }
}
