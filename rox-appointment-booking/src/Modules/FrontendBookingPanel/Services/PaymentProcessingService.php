<?php
namespace RoxAppointmentBooking\Modules\FrontendBookingPanel\Services;

use WP_Error;
use RoxAppointmentBooking\Modules\Payment\Services\StripePaymentService;
use RoxAppointmentBooking\Modules\Payment\Data\PaymentModel;
use RoxAppointmentBooking\Modules\Order\Data\OrderModel;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;

/**
 * Class PaymentProcessingService
 *
 * @package RoxAppointmentBooking\Modules\FrontendBookingPanel\Services
 * @description Handles frontend booking payment processing.
 */
class PaymentProcessingService
{
    /**
     * Process frontend booking payment.
     *
     * @param array $params Payment request parameters.
     * @param int $customerId Customer ID.
     * @param int $orderId Order ID.
     * @return array|WP_Error
     */
    public function processPayment(array $params, int $customerId, int $orderId): array|WP_Error
    {
        // The order's amount_due_now/total_amount are server-computed at
        // order-creation time (AppointmentService::saveOrder) from the actual
        // service/extra-service prices and each service's deposit rule — this
        // is what's actually charged, never the client-submitted amount,
        // which can be tampered with.
        $order = OrderModel::find($orderId);
        if (!$order) {
            return new WP_Error('order_not_found', esc_html__('Order not found', 'rox-appointment-booking'), ['status' => 404]);
        }

        // Deposit-enabled bookings default to charging just the deposit
        // (amount_due_now); the customer may opt to pay the full amount now
        // instead (§7.2). Non-deposit orders have amount_due_now === total_amount
        // already, so this is a no-op for them.
        $payFullNow = strtolower($params['payment_amount_choice'] ?? 'deposit') === 'full';
        $amount = $payFullNow ? (float) $order->total_amount : (float) $order->amount_due_now;
        if ($amount <= 0) {
            return new WP_Error('invalid_amount', esc_html__('Invalid amount', 'rox-appointment-booking'), ['status' => 400]);
        }

        $paymentType = strtolower($params['payment_type'] ?? 'credit');

        // Let Pro (or any add-on) handle a custom payment_type, e.g. 'woocommerce'.
        // Returning non-null here short-circuits the built-in later/Stripe handling.
        $customResult = apply_filters(
            'rox_appointment_booking_process_payment',
            null,
            $paymentType,
            $params,
            $customerId,
            $orderId
        );
        if ($customResult !== null) {
            return $customResult;
        }

        if ($paymentType === 'later') {
            return $this->handlePayLater($amount, $customerId, $orderId, $order);
        }

        return $this->handleStripePayment($params, $amount, $customerId, $orderId, $order);
    }

    /**
     * Handle pay later payment flow.
     *
     * @param float $amount Payment amount.
     * @param int $customerId Customer ID.
     * @param int $orderId Order ID.
     * @param OrderModel $order Order the payment belongs to.
     * @return array|WP_Error
     */
    private function handlePayLater(float $amount, int $customerId, int $orderId, OrderModel $order): array|WP_Error
    {
        $paymentResult = [
            'transaction_id' => 'pl_' . wp_generate_uuid4(),
            'status' => 'pending',
            'amount' => $amount,
            'payment_method' => 'later'
        ];

        $paymentId = $this->savePayment($customerId, $orderId, $paymentResult, $order);
        if (is_wp_error($paymentId)) {
            return $paymentId;
        }

        return array_merge($paymentResult, ['payment_id' => $paymentId]);
    }

    /**
     * Handle Stripe payment flow.
     *
     * @param array $params Payment request parameters.
     * @param float $amount Server-computed amount to charge.
     * @param int $customerId Customer ID.
     * @param int $orderId Order ID.
     * @param OrderModel $order Order the payment belongs to.
     * @return array|WP_Error
     */
    private function handleStripePayment(array $params, float $amount, int $customerId, int $orderId, OrderModel $order): array|WP_Error
    {
        if (empty($params['payment_method'])) {
            return new WP_Error('missing_payment_method', esc_html__('Payment method is required', 'rox-appointment-booking'), ['status' => 400]);
        }

        $service = new StripePaymentService();
        if (!$service->isConfigured()) {
            return new WP_Error('stripe_not_configured', esc_html__('Stripe not configured', 'rox-appointment-booking'), ['status' => 500]);
        }

        $result = $service->createAndConfirmPayment(
            $amount,
            $params['payment_method'],
            array_merge($params['metadata'] ?? [], ['customer_id' => $customerId, 'order_id' => $orderId]),
            $params['idempotency_key'] ?? wp_generate_uuid4()
        );

        if (!$result['success']) {
            return new WP_Error('payment_failed', $result['error'] ?? esc_html__('Payment failed', 'rox-appointment-booking'), ['status' => 400]);
        }

        $paymentResult = [
            'transaction_id' => $result['payment_intent_id'],
            'status' => $result['status'],
            'amount' => $result['amount'],
            'payment_method' => 'credit'
        ];
        
        $paymentId = $this->savePayment($customerId, $orderId, $paymentResult, $order);
        if (is_wp_error($paymentId)) {
            return $paymentId;
        }

        return array_merge($paymentResult, ['payment_id' => $paymentId]);
    }

    /**
     * Save payment record(s) for this charge. When the charge covers the
     * order's full total (the normal case — no partial deposit left over),
     * one row is created per appointment on the order, each carrying that
     * appointment's own line amount, so it can later be paid/viewed
     * independently (My Bookings "Pay Now", Payment History) instead of only
     * as one lump sum for the whole order. A deposit that leaves a balance
     * due later can't be attributed to individual lines this way, so it
     * keeps the previous single order-level row.
     *
     * @param int $customerId Customer ID.
     * @param int $orderId Order ID.
     * @param array $paymentResult Payment result.
     * @param OrderModel $order Order the payment belongs to.
     * @return int|WP_Error ID of the first payment row created.
     */
    private function savePayment(int $customerId, int $orderId, array $paymentResult, OrderModel $order): int|WP_Error
    {
        $appointmentIds = $order->getBookingIds();
        $chargedAmount = round((float) $paymentResult['amount'], 2);
        $orderTotal = round((float) $order->total_amount, 2);
        $isFullOrderCharge = !empty($appointmentIds) && abs($chargedAmount - $orderTotal) < 0.01;

        if (!$isFullOrderCharge) {
            return $this->savePaymentRow($customerId, $orderId, null, $chargedAmount, $paymentResult);
        }

        $appointmentService = new AppointmentService();
        $firstPaymentId = null;

        foreach ($appointmentIds as $appointmentId) {
            $appointment = AppointmentModel::find($appointmentId);
            $lineAmount = $appointment
                ? $appointmentService->calculateAppointmentLineTotal($appointment)
                : round($chargedAmount / count($appointmentIds), 2);

            $paymentId = $this->savePaymentRow($customerId, $orderId, $appointmentId, $lineAmount, $paymentResult);
            if (is_wp_error($paymentId)) {
                return $paymentId;
            }
            if ($firstPaymentId === null) {
                $firstPaymentId = $paymentId;
            }
        }

        return $firstPaymentId;
    }

    /**
     * Insert one payment row.
     *
     * @param int $customerId Customer ID.
     * @param int $orderId Order ID.
     * @param int|null $bookingId Appointment this row settles, or null for an order-level row.
     * @param float $amount Row amount.
     * @param array $paymentResult Payment result (for status/transaction id).
     * @return int|WP_Error
     */
    private function savePaymentRow(int $customerId, int $orderId, ?int $bookingId, float $amount, array $paymentResult): int|WP_Error
    {
        $payment = new PaymentModel();
        $payment->customer_id = $customerId;
        $payment->order_id = $orderId;
        $payment->booking_id = $bookingId;
        $payment->amount = $amount;
        $payment->status = $paymentResult['status'] === 'succeeded' ? 'paid' : 'unpaid';
        $payment->payment_method = isset($paymentResult['transaction_id']) && strpos($paymentResult['transaction_id'], 'pl_') === 0 ? 'pay_later' : 'stripe';
        $payment->transaction_id = $paymentResult['transaction_id'];
        $payment->payment_time = gmdate('Y-m-d H:i:s');
        $payment->save();

        return $payment->getID();
    }
}
