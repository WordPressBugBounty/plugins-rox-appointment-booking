<?php

namespace RoxAppointmentBooking\Modules\CustomerPanel\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\CustomerPanel\Services\CustomerPanelService;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * POST /customer-panel/cancel — cancel one of the logged-in customer's OWN
 * bookings (status → cancelled). Ownership is enforced server-side; already
 * completed/cancelled bookings can't be cancelled.
 */
class CancelBooking extends AbstractREST
{
    public static $loadable = true;

    public static string $route = '/customer-panel/cancel';

    public static string $usableRoute = '/customer-panel/cancel';

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

        // The agent panel's "My Bookings" page cancels through here too, exactly
        // as it reschedules (see RescheduleBooking): an agent who booked with
        // their own email holds a customer row alongside their agent row, so
        // they fail the customer-role test above but are the customer on that
        // booking — and handleRequest() scopes the request to that row. A caller
        // with no customer row at all still gets nothing.
        return CustomerPanelService::currentCustomerId() !== null;
    }

    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        // Admin switches (Settings > Booking). Hiding the Cancel buttons is not
        // enough — a direct call must be refused too. Customers and agents
        // answer to their own switch; an administrator cancelling a booking of
        // their own is governed by neither.
        if (CustomerPanelService::isCurrentUserCustomer()) {
            $allowed = rox_appointment_booking_customer_can_cancel();
        } elseif (!current_user_can('manage_options') && current_user_can('rox_appointment_booking_agent')) {
            $allowed = rox_appointment_booking_agent_can_cancel();
        } else {
            $allowed = true;
        }

        if (!$allowed) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 403,
                message: esc_html__('Cancelling is currently disabled.', 'rox-appointment-booking'),
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
        if (!$id) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('A booking id is required.', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        $booking = AppointmentModel::find($id);
        if (!$booking) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 404,
                message: esc_html__('Booking not found.', 'rox-appointment-booking'),
                headers: ['status' => 404]
            );
        }

        // Ownership guard — a customer may only cancel their own booking.
        if ((int) ($booking->customer_id ?? 0) !== $customerId) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 403,
                message: esc_html__('You are not allowed to cancel this booking.', 'rox-appointment-booking'),
                headers: ['status' => 403]
            );
        }

        $status = strtolower($booking->status ?? '');
        if ($status === 'cancelled' || $status === 'completed') {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('This booking can no longer be cancelled.', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        $booking->update(['status' => 'cancelled']);

        return rox_appointment_booking_rest_response(
            data: ['id' => $id, 'status' => 'cancelled'],
            message: esc_html__('Booking cancelled successfully', 'rox-appointment-booking')
        );
    }
}
