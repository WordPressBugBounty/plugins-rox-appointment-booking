<?php
namespace RoxAppointmentBooking\Modules\FrontendBookingPanel\Services;

use WP_Error;
use RoxAppointmentBooking\Modules\Payment\Services\StripePaymentService;
use RoxAppointmentBooking\Modules\Payment\Data\PaymentModel;

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
        if (!isset($params['amount']) || (float)$params['amount'] <= 0) {
            return new WP_Error('invalid_amount', esc_html__('Invalid amount', 'rox-appointment-booking'), ['status' => 400]);
        }

        $paymentType = strtolower($params['payment_type'] ?? 'credit');
        
        if ($paymentType === 'later') {
            return $this->handlePayLater($params['amount'], $customerId, $orderId);
        }

        return $this->handleStripePayment($params, $customerId, $orderId);
    }

    /**
     * Handle pay later payment flow.
     *
     * @param float $amount Payment amount.
     * @param int $customerId Customer ID.
     * @param int $orderId Order ID.
     * @return array|WP_Error
     */
    private function handlePayLater(float $amount, int $customerId, int $orderId): array|WP_Error
    {
        $paymentResult = [
            'transaction_id' => 'pl_' . wp_generate_uuid4(),
            'status' => 'pending',
            'amount' => $amount,
            'payment_method' => 'later'
        ];
        
        $paymentId = $this->savePayment($customerId, $orderId, $paymentResult);
        if (is_wp_error($paymentId)) {
            return $paymentId;
        }
        
        return array_merge($paymentResult, ['payment_id' => $paymentId]);
    }

    /**
     * Handle Stripe payment flow.
     *
     * @param array $params Payment request parameters.
     * @param int $customerId Customer ID.
     * @param int $orderId Order ID.
     * @return array|WP_Error
     */
    private function handleStripePayment(array $params, int $customerId, int $orderId): array|WP_Error
    {
        if (empty($params['payment_method'])) {
            return new WP_Error('missing_payment_method', esc_html__('Payment method is required', 'rox-appointment-booking'), ['status' => 400]);
        }

        $service = new StripePaymentService();
        if (!$service->isConfigured()) {
            return new WP_Error('stripe_not_configured', esc_html__('Stripe not configured', 'rox-appointment-booking'), ['status' => 500]);
        }

        $result = $service->createAndConfirmPayment(
            (float)$params['amount'],
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
        
        $paymentId = $this->savePayment($customerId, $orderId, $paymentResult);
        if (is_wp_error($paymentId)) {
            return $paymentId;
        }
        
        return array_merge($paymentResult, ['payment_id' => $paymentId]);
    }

    /**
     * Save payment record.
     *
     * @param int $customerId Customer ID.
     * @param int $orderId Order ID.
     * @param array $paymentResult Payment result.
     * @return int|WP_Error
     */
    private function savePayment(int $customerId, int $orderId, array $paymentResult): int|WP_Error
    {
        $payment = new PaymentModel();
        $payment->customer_id = $customerId;
        $payment->order_id = $orderId;
        $payment->amount = $paymentResult['amount'];
        $payment->status = $paymentResult['status'] === 'succeeded' ? 'paid' : 'unpaid';
        $payment->payment_method = isset($paymentResult['transaction_id']) && strpos($paymentResult['transaction_id'], 'pl_') === 0 ? 'pay_later' : 'stripe';
        $payment->transaction_id = $paymentResult['transaction_id'];
        $payment->payment_time = gmdate('Y-m-d H:i:s');
        $payment->save();

        return $payment->getID();
    }
}
