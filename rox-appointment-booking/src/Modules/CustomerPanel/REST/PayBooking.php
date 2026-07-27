<?php

namespace RoxAppointmentBooking\Modules\CustomerPanel\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\CustomerPanel\Services\CustomerPanelService;
use RoxAppointmentBooking\Modules\Order\Data\OrderModel;
use RoxAppointmentBooking\Modules\Payment\Data\PaymentModel;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\Payment\Services\StripePaymentService;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * POST /customer-panel/pay — settle the outstanding balance on one of the
 * logged-in customer's OWN orders (a pay-later booking) with a Stripe card.
 * The client collects card details via Stripe.js and sends a payment-method id
 * (pm_…); the charge amount is computed server-side (order total minus what is
 * already paid) so the client can never dictate it. Ownership is enforced
 * server-side. On success the order + its bookings + the payment record all
 * become paid.
 */
class PayBooking extends AbstractREST
{
    public static $loadable = true;

    public static string $route = '/customer-panel/pay';

    public static string $usableRoute = '/customer-panel/pay';

    protected function getMethods(): string|array
    {
        return 'POST';
    }

    public function permissionCheck(WP_REST_Request $request): bool
    {
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return false;
        }

        return CustomerPanelService::isCurrentUserCustomer();
    }

    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $customerId = CustomerPanelService::currentCustomerId();
        if (!$customerId) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 403,
                message: esc_html__('Customer account not found for this user.', 'rox-appointment-booking'),
                headers: ['status' => 403]
            );
        }

        $orderId = (int) $request->get_param('order_id');
        $paymentMethod = sanitize_text_field((string) $request->get_param('payment_method'));

        if (!$orderId) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('An order id is required.', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        if ($paymentMethod === '') {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('A payment method is required.', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        $order = OrderModel::find($orderId);
        if (!$order) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 404,
                message: esc_html__('Order not found.', 'rox-appointment-booking'),
                headers: ['status' => 404]
            );
        }

        // Ownership guard — a customer may only pay their own order.
        if ((int) ($order->customer_id ?? 0) !== $customerId) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 403,
                message: esc_html__('You are not allowed to pay for this order.', 'rox-appointment-booking'),
                headers: ['status' => 403]
            );
        }

        if ($order->isPaid()) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('This order has already been paid.', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        // Outstanding = order total minus whatever has already been settled.
        $amount = round($this->outstandingAmount($order, $orderId), 2);
        if ($amount <= 0) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('There is nothing left to pay on this order.', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        $stripe = new StripePaymentService();
        if (!$stripe->isConfigured()) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('Online payment is not available right now. Please contact us to complete payment.', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        $result = $stripe->createAndConfirmPayment(
            $amount,
            $paymentMethod,
            ['customer_id' => $customerId, 'order_id' => $orderId],
        );

        if (empty($result['success']) || ($result['status'] ?? '') !== 'succeeded') {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: $result['error'] ?? esc_html__('Payment could not be completed. Please try again.', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        $transactionId = (string) ($result['payment_intent_id'] ?? '');
        $this->markPaid($order, $orderId, $customerId, $amount, $transactionId);

        return rox_appointment_booking_rest_response(
            data: [
                'order_id' => $orderId,
                'status' => 'paid',
                'transaction_id' => $transactionId,
                'amount' => $amount,
            ],
            message: esc_html__('Payment successful', 'rox-appointment-booking')
        );
    }

    /**
     * How much is still owed on the order: total minus the sum of already-paid
     * payment rows. Falls back to the full total when nothing has been paid.
     */
    private function outstandingAmount(OrderModel $order, int $orderId): float
    {
        $total = (float) ($order->total_amount ?? 0);

        $paid = (float) PaymentModel::query()
            ->where('order_id', $orderId)
            ->where('status', PaymentModel::STATUS_PAID)
            ->sum('amount');

        return $total - $paid;
    }

    /**
     * Settle the order after a successful charge: mark the outstanding payment
     * row (or create one) paid, flip the order to paid, and mark every booking
     * on the order paid.
     */
    private function markPaid(OrderModel $order, int $orderId, int $customerId, float $amount, string $transactionId): void
    {
        $payment = PaymentModel::query()
            ->where('order_id', $orderId)
            ->where('status', PaymentModel::STATUS_UNPAID)
            ->first();

        if ($payment) {
            $payment->update([
                'status' => PaymentModel::STATUS_PAID,
                'payment_method' => 'stripe',
                'transaction_id' => $transactionId,
                'payment_time' => gmdate('Y-m-d H:i:s'),
            ]);
        } else {
            PaymentModel::create([
                'customer_id' => $customerId,
                'order_id' => $orderId,
                'amount' => $amount,
                'status' => PaymentModel::STATUS_PAID,
                'payment_method' => 'stripe',
                'transaction_id' => $transactionId,
                'payment_time' => gmdate('Y-m-d H:i:s'),
            ]);
        }

        $order->update([
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'payment_transaction_id' => $transactionId,
        ]);

        foreach ($order->getBookingIds() as $bookingId) {
            $booking = AppointmentModel::find((int) $bookingId);
            if ($booking) {
                $booking->update(['payment_status' => 'paid']);
            }
        }
    }
}
