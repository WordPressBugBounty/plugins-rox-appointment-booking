<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment;

defined('ABSPATH') || exit;

use WP_Error;

/**
 * Class CreateAppointment
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment
 * @description Books a new appointment the way the dashboard's New Booking form does.
 */
class CreateAppointment extends AbstractAppointmentWrite
{
    protected string $name = 'create-appointment';

    protected string $capability = 'appointment.create';

    public function label(): string
    {
        return __('Create Appointment', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Books a new appointment for an existing customer and creates its order. Call get-available-slots first and pass one of its free times. agent_id is required unless the service can be booked without an agent. Date is Y-m-d and time is H:i (24-hour) in the site timezone. Side effects: notifies the admin and, unless notify_customer is false, e-mails the customer a confirmation. Returns the new appointment.';
    }

    protected function properties(): array
    {
        return [
            'customer_id'       => ['type' => 'integer', 'minimum' => 1, 'description' => 'Existing customer id (see search-customers / create-customer).'],
            'service_id'        => ['type' => 'integer', 'minimum' => 1, 'description' => 'Service to book.'],
            'agent_id'          => ['type' => 'integer', 'minimum' => 1, 'description' => 'Agent who will serve it.'],
            'location_id'       => ['type' => 'integer', 'minimum' => 1, 'description' => 'Location, when locations are in use.'],
            'date'              => ['type' => 'string', 'format' => 'date', 'description' => 'Day (Y-m-d).'],
            'time'              => ['type' => 'string', 'pattern' => '^\d{2}:\d{2}$', 'description' => 'Start time (H:i, 24-hour).'],
            'status'            => ['type' => 'string', 'enum' => $this->statuses(), 'description' => 'Initial status; defaults to the site setting.'],
            'total_attendees'   => ['type' => 'integer', 'minimum' => 1, 'default' => 1, 'description' => 'Number of people, for group services.'],
            'extra_service_ids' => $this->idsProperty('Extra services to add, when available.'),
            'notify_customer'   => ['type' => 'boolean', 'default' => true, 'description' => 'E-mail the customer a booking confirmation.'],
        ];
    }

    protected function required(): array
    {
        return ['customer_id', 'service_id', 'date', 'time'];
    }

    protected function run(array $input)
    {
        $date  = $this->date($input['date'] ?? '');
        $start = $this->dateTime($date, $input['time'] ?? '');
        if (!$start) {
            return new WP_Error('invalid_datetime', 'date must be Y-m-d and time H:i.', ['status' => 400]);
        }

        $agentId = absint($input['agent_id'] ?? 0);

        // The save handler checks overlaps but not working hours or holidays, so ask the calendar first.
        if ($agentId) {
            $check = $this->dispatch('GET', 'calendar/check-availability', array_filter([
                'date'        => $date,
                'slot'        => substr($start, 11, 5),
                'service_id'  => absint($input['service_id'] ?? 0),
                'agent_id'    => $agentId,
                'location_id' => absint($input['location_id'] ?? 0),
            ]));
            if (is_wp_error($check)) {
                return $check;
            }
            if (empty($check['data']['available'])) {
                // Each reason is a {type, message} pair.
                $reasons = array_map(function ($reason) {
                    return is_array($reason) ? (string) ($reason['message'] ?? $reason['type'] ?? '') : (string) $reason;
                }, (array) ($check['data']['reasons'] ?? []));

                return new WP_Error(
                    'slot_unavailable',
                    'That time is not available: ' . implode('. ', array_filter($reasons)) . '. Call get-available-slots to find a free time.',
                    ['status' => 409]
                );
            }
        }

        $payload = array_filter([
            'customer_id'     => absint($input['customer_id'] ?? 0),
            'service_id'      => absint($input['service_id'] ?? 0),
            'agent_id'        => $agentId,
            'location_id'     => absint($input['location_id'] ?? 0),
            'date'            => $date,
            'start_time'      => $start,
            'status'          => in_array($input['status'] ?? '', $this->statuses(), true) ? $input['status'] : '',
            'total_attendees' => max(1, absint($input['total_attendees'] ?? 1)),
            'extra_services'  => $this->ids($input['extra_service_ids'] ?? []),
        ]);
        $payload['send_notification'] = rest_sanitize_boolean($input['notify_customer'] ?? true) ? 1 : 0;

        $result = $this->dispatch('POST', 'appointment', $payload);
        if (is_wp_error($result)) {
            return $result;
        }

        $id = (int) ($result['data']['id'] ?? 0);

        return $id ? $this->fresh($id) : (array) $result['data'];
    }
}
