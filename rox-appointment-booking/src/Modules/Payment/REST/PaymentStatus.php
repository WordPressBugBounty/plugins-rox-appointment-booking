<?php

namespace RoxAppointmentBooking\Modules\Payment\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Payment\Data\PaymentModel;
use RoxAppointmentBooking\Modules\Order\Data\OrderModel;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\Notification\Services\NotificationService;
use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;

defined('ABSPATH') || exit;

/**
 * Class PaymentStatus
 *
 * @package RoxAppointmentBooking\Modules\Payment
 * @description Handles PaymentStatus functionality.
 */
class PaymentStatus extends AbstractREST
{
    /**
     * Whether this class should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for this endpoint.
     *
     * @var string
     */
    public static string $route = '/payment/status-update/(?P<id>\d+)';

    /**
     * Get the HTTP methods allowed for this route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return ['PUT'];
    }

    /**
     * Check whether the current user can access this endpoint.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return bool
     */
    public function permissionCheck(WP_REST_Request $request): bool
    {
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return false;
        }

        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return false;
        }

        return true;
    }

    /**
     * Handle the REST API request.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = intval($request->get_param('id'));
        $body = $request->get_json_params();
        $status = sanitize_text_field($body['status'] ?? '');

        $allowed = [
            PaymentModel::STATUS_UNPAID,
            PaymentModel::STATUS_PAID,
            PaymentModel::STATUS_FAILED,
            PaymentModel::STATUS_REFUNDED
        ];

        if (!in_array($status, $allowed, true)) {
            return new WP_Error(
                'invalid_status',
                sprintf(
                    // translators: %s = comma-separated list of allowed statuses
                    esc_html__('Invalid status. Allowed: %s', 'rox-appointment-booking'),
                    implode(', ', $allowed)
                ),
                ['status' => 400]
            );
        }

        $payment = PaymentModel::find($id);

        if (!$payment) {
            return new WP_Error(
                'payment_not_found',
                esc_html__('Payment not found', 'rox-appointment-booking'),
                ['status' => 404]
            );
        }

        $oldStatus = $payment->status;
        $payment->update(['status' => $status]);
        if ($oldStatus !== $status) {
            // Create Payment Notification
            $customerName = __('Customer', 'rox-appointment-booking');
            if ($payment->customer_id) {
                $customer = CustomerModel::find($payment->customer_id);
                if ($customer && (!empty($customer->full_name) || !empty($customer->first_name))) {
                    $customerName = $customer->full_name ?? trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
                }
            }
            NotificationService::createPaymentNotification([
                'admin_user_id' => get_current_user_id() ?: null,
                'customer_name' => $customerName,
                'amount' => $payment->amount,
                'payment_id' => $payment->id,
                'status' => $status
            ]);
        }

        if ($payment->order_id) {
            $order = OrderModel::find($payment->order_id);
            if ($order) {
                $order->update(['payment_status' => $status]);

                $bookingIds = $order->getBookingIds();
                if (!empty($bookingIds)) {
                    AppointmentModel::whereIn('id', $bookingIds)
                        ->update(['payment_status' => $status]);
                }
            }
        }

        return rox_appointment_booking_rest_response(
            data: ['id' => $id, 'status' => $status],
            message: [
                'success' => [
                    esc_html__('Payment status updated successfully', 'rox-appointment-booking')
                ]
            ]
        );
    }
}
