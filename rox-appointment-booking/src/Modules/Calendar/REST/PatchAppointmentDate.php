<?php

namespace RoxAppointmentBooking\Modules\Calendar\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Supports\Security;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;
use RoxAppointmentBooking\Modules\Service\Data\ServiceModel;
use RoxAppointmentBooking\Modules\Notification\Services\NotificationService;

/**
 * Class PatchAppointmentDate
 *
 * @package RoxAppointmentBooking\Modules\Calendar\REST
 * @description Handles drag-and-drop date updates for calendar events via PATCH.
 *              When a user drags an event to a new date, this endpoint updates
 *              the appointment's date, start_time, and end_time while preserving
 *              the original time values.
 */
class PatchAppointmentDate extends AbstractREST
{
    /**
     * Whether the endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for appointment reschedule.
     *
     * @var string
     */
    public static string $route = '/calendar/appointment/reschedule';

    /**
     * Get the methods allowed for this route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return ['POST', 'PATCH'];
    }

    /**
     * Check if the user has permission to access the endpoint.
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public function permissionCheck(WP_REST_Request $request): bool
    {
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return false;
        }

        if (!is_user_logged_in() || !Security::canViewBookings()) {
            return false;
        }

        return true;
    }

    /**
     * Handle the REST API request.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $body = $request->get_json_params();
        $id      = intval($body['id'] ?? 0);
        $newDate = sanitize_text_field($body['date'] ?? '');

        if (!$id) {
            return new WP_Error(
                'invalid_id',
                esc_html__('Invalid appointment ID', 'rox-appointment-booking'),
                ['status' => 400]
            );
        }

        if (empty($newDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
            return new WP_Error(
                'invalid_date',
                esc_html__('A valid date (YYYY-MM-DD) is required', 'rox-appointment-booking'),
                ['status' => 400]
            );
        }

        $appointment = AppointmentModel::find($id);

        if (!$appointment) {
            return new WP_Error(
                'appointment_not_found',
                esc_html__('Appointment not found', 'rox-appointment-booking'),
                ['status' => 404]
            );
        }

        // Preserve existing times, only change the date part
        $oldStartTime = $appointment->start_time; // e.g. "2026-02-19 10:00:00"
        $oldEndTime   = $appointment->end_time;   // e.g. "2026-02-19 11:00:00"

        $newStartTime = $oldStartTime
            ? $newDate . ' ' . gmdate('H:i:s', strtotime($oldStartTime))
            : null;

        $newEndTime = $oldEndTime
            ? $newDate . ' ' . gmdate('H:i:s', strtotime($oldEndTime))
            : null;

        $updateData = [
            'date'       => $newDate,
            'start_time' => $newStartTime,
            'end_time'   => $newEndTime,
            'updated_by' => get_current_user_id(),
        ];

        $result = $appointment->update($updateData);

        if (!$result) {
            return new WP_Error(
                'update_failed',
                esc_html__('Failed to update appointment date', 'rox-appointment-booking'),
                ['status' => 500]
            );
        }

        // Fire reschedule notification
        $customerName = '';
        if (!empty($appointment->customer_id)) {
            $customer = CustomerModel::find($appointment->customer_id);
            if ($customer) {
                $customerName = $customer->full_name ?? '';
            }
        }
        $serviceName = '';
        if (!empty($appointment->service_id)) {
            $service = ServiceModel::find($appointment->service_id);
            if ($service) {
                $serviceName = $service->title ?? '';
            }
        }
        NotificationService::createRescheduleNotification([
            'admin_user_id'  => get_current_user_id(),
            'appointment_id' => $id,
            'customer_name'  => $customerName,
            'service_name'   => $serviceName,
            'old_date'       => $appointment->date ?? '',
            'old_start_time' => $oldStartTime ?? '',
            'new_date'       => $newDate,
            'new_start_time' => $newStartTime ?? '',
        ]);

        return rox_appointment_booking_rest_response(
            data: [
                'id'         => $id,
                'date'       => $newDate,
                'start_time' => $newStartTime,
                'end_time'   => $newEndTime,
            ],
            message: [
                'success' => [
                    esc_html__('Appointment date updated successfully', 'rox-appointment-booking')
                ]
            ]
        );
    }
}
