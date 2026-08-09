<?php

namespace RoxAppointmentBooking\Modules\CustomerPanel\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\CustomerPanel\Services\CustomerPanelService;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\Service\Data\ServiceModel;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * POST /customer-panel/reschedule — move one of the logged-in customer's OWN
 * bookings to a new date/time. Ownership is enforced server-side; the original
 * slot length is preserved and re-checked for agent conflicts.
 */
class RescheduleBooking extends AbstractREST
{
    public static $loadable = true;

    public static string $route = '/customer-panel/reschedule';

    public static string $usableRoute = '/customer-panel/reschedule';

    protected function getMethods(): string|array
    {
        return 'POST';
    }

    public function permissionCheck(WP_REST_Request $request): bool
    {
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return false;
        }

        if (CustomerPanelService::isCurrentUserCustomer()) {
            return true;
        }

        // The agent panel's "My Bookings" page reschedules through here too. An
        // agent who booked with their own email holds a customer row alongside
        // their agent row, so they fail the customer-role test above but are a
        // customer on that booking — and handleRequest() scopes the request to
        // that row, rejecting any id that is not theirs. A caller with no
        // customer row at all still gets nothing.
        return CustomerPanelService::currentCustomerId() !== null;
    }

    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        // Admin switches (Settings > Booking). Hiding the Reschedule buttons is
        // not enough — a direct call must be refused too. Customers and agents
        // answer to their own switch; an administrator reaching this endpoint
        // for a booking of their own is governed by neither.
        if (CustomerPanelService::isCurrentUserCustomer()) {
            $allowed = rox_appointment_booking_customer_can_reschedule();
        } elseif (!current_user_can('manage_options') && current_user_can('rox_appointment_booking_agent')) {
            $allowed = rox_appointment_booking_agent_can_reschedule();
        } else {
            $allowed = true;
        }

        if (!$allowed) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 403,
                message: esc_html__('Rescheduling is currently disabled.', 'rox-appointment-booking'),
                headers: ['status' => 403]
            );
        }

        $customerId = CustomerPanelService::currentCustomerId();
        if (!$customerId) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 403,
                message: esc_html__('Customer account not found for this user.', 'rox-appointment-booking'),
                headers: ['status' => 403]
            );
        }

        $id = (int) $request->get_param('id');
        $date = sanitize_text_field((string) $request->get_param('date'));
        $startTime = sanitize_text_field((string) $request->get_param('start_time'));

        if (!$id || !$this->isValidDate($date) || $startTime === '') {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('A booking id, date and start time are required.', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        // Normalise "H:i" or "H:i:s" to a full time string.
        $startTime = gmdate('H:i:s', strtotime($date . ' ' . $startTime));

        $booking = AppointmentModel::find($id);
        if (!$booking) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 404,
                message: esc_html__('Booking not found.', 'rox-appointment-booking'),
                headers: ['status' => 404]
            );
        }

        // Ownership guard — a customer may only reschedule their own booking.
        if ((int) ($booking->customer_id ?? 0) !== $customerId) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 403,
                message: esc_html__('You are not allowed to reschedule this booking.', 'rox-appointment-booking'),
                headers: ['status' => 403]
            );
        }

        // Preserve the original slot length; fall back to the service duration.
        $durationMinutes = $this->originalDurationMinutes($booking);

        $startDateTime = $date . ' ' . $startTime;
        $endDateTime = gmdate('Y-m-d H:i:s', strtotime($startDateTime) + $durationMinutes * 60);

        // Agent conflict check (skipped for agent-less/service-capacity bookings).
        $agentId = (int) ($booking->agent_id ?? 0);
        if ($agentId && $this->hasConflict($agentId, $id, $date, $startDateTime, $endDateTime)) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 409,
                message: esc_html__('That time slot is no longer available. Please pick another.', 'rox-appointment-booking'),
                headers: ['status' => 409]
            );
        }

        $booking->update([
            'date' => $date,
            'start_time' => $startDateTime,
            'end_time' => $endDateTime,
        ]);

        return rox_appointment_booking_rest_response(
            data: [
                'id' => $id,
                'date' => $date,
                'start_time' => $startTime,
            ],
            message: esc_html__('Booking rescheduled successfully', 'rox-appointment-booking')
        );
    }

    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    private function originalDurationMinutes(AppointmentModel $booking): int
    {
        $start = $booking->start_time ?? '';
        $end = $booking->end_time ?? '';
        if ($start && $end) {
            $diff = (int) round((strtotime($end) - strtotime($start)) / 60);
            if ($diff > 0) {
                return $diff;
            }
        }

        $service = !empty($booking->service_id) ? ServiceModel::find((int) $booking->service_id) : null;
        $duration = $service ? (int) ($service->duration ?? 0) : 0;

        return $duration > 0 ? $duration : 30;
    }

    private function hasConflict(int $agentId, int $excludeId, string $date, string $startDateTime, string $endDateTime): bool
    {
        return AppointmentModel::query()
            ->where('agent_id', $agentId)
            ->where('date', $date)
            ->where('id', '!=', $excludeId)
            ->whereNotIn('status', ['cancelled', 'canceled', 'rejected'])
            ->where('start_time', '<', $endDateTime)
            ->where('end_time', '>', $startDateTime)
            ->exists();
    }
}
