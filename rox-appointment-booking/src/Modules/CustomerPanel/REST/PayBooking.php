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
 * POST /customer-panel/pay — settle an outstanding (pay-later) charge of the
 * logged-in customer's own with a Stripe card. The client collects card
 * details via Stripe.js and sends a payment-method id (pm_…); the charge
 * amount is always computed server-side so the client can never dictate it.
 * Ownership is enforced server-side.
 *
 * Two modes, tried in this order:
 *  - booking_id: pay just ONE appointment's own payment row (the normal case
 *    since PaymentProcessingService now creates one row per appointment on
 *    an order). Only that appointment + its row become paid; the order only
 *    flips to paid once every one of its rows is settled this way.
 *  - order_id: legacy fallback — settle the WHOLE order in one charge. Used
 *    when a row has no booking_id (pre-migration data, or a deposit charge
 *    that covered less than the full order and so couldn't be split per
 *    line — see PaymentProcessingService::savePayment()).
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

        $bookingId = (int) $request->get_param('booking_id');
        $orderId = (int) $request->get_param('order_id');
        $paymentMethod = sanitize_text_field((string) $request->get_param('payment_method'));

        if (!$bookingId && !$orderId) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('A booking id or order id is required.', 'rox-appointment-booking'),
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

        if ($bookingId) {
            $result = $this->payBooking($bookingId, $customerId, $paymentMethod);
            // No per-line row to pay (legacy data or already settled another
            // way) — fall back to paying off the whole order this booking
            // belongs to, same as the pre-split behavior.
            if (is_wp_error($result) && $result->get_error_code() === 'no_booking_payment') {
                $order = $this->findOrderForBooking($bookingId);
                if ($order) {
                    return $this->payOrder((int) $order->getID(), $customerId, $paymentMethod);
                }
            }
            return $result instanceof WP_Error ? $this->errorResponse($result) : $result;
        }

        $result = $this->payOrder($orderId, $customerId, $paymentMethod);
        return $result instanceof WP_Error ? $this->errorResponse($result) : $result;
    }

    /**
     * Pay a single appointment's own payment row.
     */
    private function payBooking(int $bookingId, int $customerId, string $paymentMethod): WP_REST_Response|WP_Error
    {
        $appointment = AppointmentModel::find($bookingId);
        if (!$appointment) {
            return new WP_Error('booking_not_found', esc_html__('Booking not found.', 'rox-appointment-booking'));
        }

        if ((int) ($appointment->customer_id ?? 0) !== $customerId) {
            return new WP_Error('forbidden', esc_html__('You are not allowed to pay for this booking.', 'rox-appointment-booking'));
        }

        $payment = PaymentModel::query()
            ->where('booking_id', $bookingId)
            ->where('status', PaymentModel::STATUS_UNPAID)
            ->first();

        if (!$payment) {
            return new WP_Error('no_booking_payment', esc_html__('There is nothing left to pay on this booking.', 'rox-appointment-booking'));
        }

        $amount = round((float) $payment->amount, 2);
        if ($amount <= 0) {
            return new WP_Error('invalid_amount', esc_html__('There is nothing left to pay on this booking.', 'rox-appointment-booking'));
        }

        $stripe = new StripePaymentService();
        if (!$stripe->isConfigured()) {
            return new WP_Error('stripe_not_configured', esc_html__('Online payment is not available right now. Please contact us to complete payment.', 'rox-appointment-booking'));
        }

        $result = $stripe->createAndConfirmPayment(
            $amount,
            $paymentMethod,
            ['customer_id' => $customerId, 'order_id' => $payment->order_id, 'booking_id' => $bookingId],
        );

        if (empty($result['success']) || ($result['status'] ?? '') !== 'succeeded') {
            return new WP_Error('payment_failed', $result['error'] ?? esc_html__('Payment could not be completed. Please try again.', 'rox-appointment-booking'));
        }

        $transactionId = (string) ($result['payment_intent_id'] ?? '');

        $payment->update([
            'status' => PaymentModel::STATUS_PAID,
            'payment_method' => 'stripe',
            'transaction_id' => $transactionId,
            'payment_time' => gmdate('Y-m-d H:i:s'),
        ]);

        $appointment->update(['payment_status' => 'paid']);

        // Flip the order to paid only once every one of its lines is settled.
        $orderId = (int) $payment->order_id;
        if ($orderId) {
            $stillUnpaid = PaymentModel::query()
                ->where('order_id', $orderId)
                ->where('status', PaymentModel::STATUS_UNPAID)
                ->exists();

            if (!$stillUnpaid) {
                $order = OrderModel::find($orderId);
                if ($order) {
                    $order->update([
                        'payment_status' => 'paid',
                        'payment_method' => 'stripe',
                        'payment_transaction_id' => $transactionId,
                    ]);
                }
            }
        }

        // This path settles the payment itself rather than going through
        // PaymentStatusSyncService, so it has to raise the e-mail event too.
        do_action(
            'rox_appointment_booking_email_event',
            'payment_received',
            [
                'customer_id' => $customerId,
                'order_id'    => $orderId,
                'payment'     => [
                    'amount'         => $amount,
                    'transaction_id' => $transactionId,
                    'payment_method' => 'stripe',
                ],
            ]
        );

        return rox_appointment_booking_rest_response(
            data: [
                'order_id' => $orderId,
                'booking_id' => $bookingId,
                'status' => 'paid',
                'transaction_id' => $transactionId,
                'amount' => $amount,
            ],
            message: esc_html__('Payment successful', 'rox-appointment-booking')
        );
    }

    /**
     * Legacy path: settle the WHOLE order in one charge. Kept for payment
     * rows that predate the per-booking split, or a deposit row that only
     * ever covered part of the order (booking_id stays null for those).
     */
    private function payOrder(int $orderId, int $customerId, string $paymentMethod): WP_REST_Response|WP_Error
    {
        $order = OrderModel::find($orderId);
        if (!$order) {
            return new WP_Error('order_not_found', esc_html__('Order not found.', 'rox-appointment-booking'));
        }

        // Ownership guard — a customer may only pay their own order.
        if ((int) ($order->customer_id ?? 0) !== $customerId) {
            return new WP_Error('forbidden', esc_html__('You are not allowed to pay for this order.', 'rox-appointment-booking'));
        }

        if ($order->isPaid()) {
            return new WP_Error('already_paid', esc_html__('This order has already been paid.', 'rox-appointment-booking'));
        }

        // Outstanding = order total minus whatever has already been settled.
        $amount = round($this->outstandingAmount($order, $orderId), 2);
        if ($amount <= 0) {
            return new WP_Error('nothing_to_pay', esc_html__('There is nothing left to pay on this order.', 'rox-appointment-booking'));
        }

        $stripe = new StripePaymentService();
        if (!$stripe->isConfigured()) {
            return new WP_Error('stripe_not_configured', esc_html__('Online payment is not available right now. Please contact us to complete payment.', 'rox-appointment-booking'));
        }

        $result = $stripe->createAndConfirmPayment(
            $amount,
            $paymentMethod,
            ['customer_id' => $customerId, 'order_id' => $orderId],
        );

        if (empty($result['success']) || ($result['status'] ?? '') !== 'succeeded') {
            return new WP_Error('payment_failed', $result['error'] ?? esc_html__('Payment could not be completed. Please try again.', 'rox-appointment-booking'));
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

        // A deposit payment row is marked partially_paid (not paid) once it
        // leaves a balance due later — see AppointmentService::updatePaymentStatus().
        // It still represents money actually collected, so it must count here too.
        $paid = (float) PaymentModel::query()
            ->where('order_id', $orderId)
            ->whereIn('status', [PaymentModel::STATUS_PAID, PaymentModel::STATUS_PARTIALLY_PAID])
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

        // Settled here rather than through PaymentStatusSyncService, so this
        // path raises the e-mail event itself.
        do_action(
            'rox_appointment_booking_email_event',
            'payment_received',
            [
                'customer_id' => $customerId,
                'order_id'    => $orderId,
                'payment'     => [
                    'amount'         => $amount,
                    'transaction_id' => $transactionId,
                    'payment_method' => 'stripe',
                ],
            ]
        );
    }

    /** Find the order (if any) whose booking_ids contains this appointment id. */
    private function findOrderForBooking(int $bookingId): ?OrderModel
    {
        if ($bookingId <= 0) {
            return null;
        }
        return OrderModel::whereRaw('JSON_CONTAINS(booking_ids, %s)', [wp_json_encode($bookingId)])->first();
    }

    /** Convert a WP_Error into the plugin's standard REST error response shape. */
    private function errorResponse(WP_Error $error): WP_REST_Response
    {
        return rox_appointment_booking_rest_response(
            data: null,
            code: 400,
            message: $error->get_error_message(),
            headers: ['status' => 400]
        );
    }
}
