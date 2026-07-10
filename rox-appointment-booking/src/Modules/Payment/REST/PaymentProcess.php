<?php

namespace RoxAppointmentBooking\Modules\Payment\REST;

use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Payment\Services\StripePaymentService;

defined('ABSPATH') || exit;

/**
 * Class PaymentProcess
 *
 * @package RoxAppointmentBooking\Modules\Payment
 * @description Handles PaymentProcess functionality.
 */
class PaymentProcess extends AbstractREST
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
    public static string $route = '/payment';
    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/payment';

    /**
     * Get the HTTP methods allowed for this route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'POST';
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
        if (!$this->isHttpsOrDev()) {
            // Non-HTTPS connection outside debug mode
        }

        // if (!$this->checkRateLimit()) {
        //     return new WP_Error('rate_limit_exceeded', esc_html__('Too many requests. Please try again later.', 'rox-appointment-booking'), ['status' => 429]);
        // }

        if (!$this->verifyGuestSecurity($request)) {
            return new WP_Error('security_check_failed', esc_html__('Security check failed', 'rox-appointment-booking'), ['status' => 403]);
        }

        try {
            $jsonParams = $request->get_json_params();
            if (!is_array($jsonParams)) {
                return new WP_Error('invalid_json', esc_html__('Invalid JSON data', 'rox-appointment-booking'), ['status' => 400]);
            }
            $paymentRequest = $this->createPaymentRequest($jsonParams);
        } catch (\InvalidArgumentException $e) {
            return new WP_Error('invalid_data', $e->getMessage(), ['status' => 400]);
        }

        // Handle payment type: card or paylater
        $paymentType = $paymentRequest->getPaymentType();

        if ($paymentType === 'later') {
            $processor = $this->processPayLaterPayment($paymentRequest);
        } else {
            $processor = $this->processStripePayment($paymentRequest);
        }

        if (!$processor['success']) {
            return rox_appointment_booking_rest_response(data: null, code: 400, message: $processor['error'] ?: esc_html__('Payment processing failed', 'rox-appointment-booking'));
        }

        return rox_appointment_booking_rest_response(
            data: [
                'transaction_id' => $processor['transaction_id'],
                'status' => $processor['status'],
                'amount' => $processor['amount']
            ],
            message: esc_html__('Payment processed successfully', 'rox-appointment-booking')
        );
    }

    /**
     * Process Stripe payment.
     *
     * @param object $request Payment request object.
     * @return array
     */
    private function processStripePayment(object $request): array
    {
        $service = new StripePaymentService();
        
        if (!$service->isConfigured()) {
            return ['success' => false, 'error' => 'Stripe not configured'];
        }

        $result = $service->createAndConfirmPayment(
            $request->getAmount(),
            $request->getPaymentMethodId(),
            $request->getMetadata(),
            $request->getIdempotencyKey()
        );

        if ($result['success']) {
            return [
                'success' => true,
                'transaction_id' => $result['payment_intent_id'],
                'status' => $result['status'],
                'amount' => $result['amount']
            ];
        }

        return $result;
    }

    /**
     * Process pay later payment.
     *
     * @param object $request Payment request object.
     * @return array
     */
    private function processPayLaterPayment(object $request): array
    {
        // Standard pay later workflow - no Stripe payment needed
        // Generate a transaction ID for pay later
        $transactionId = 'pl_' . wp_generate_uuid4();
        
        return [
            'success' => true,
            'transaction_id' => $transactionId,
            'status' => 'pending',
            'amount' => $request->getAmount()
        ];
    }

    /**
     * Create a validated payment request object.
     *
     * @param array $data Payment request data.
     * @return object
     */
    private function createPaymentRequest(array $data): object
    {
        if (!isset($data['amount']) || !is_numeric($data['amount']) || (float)$data['amount'] <= 0) {
            throw new \InvalidArgumentException('Invalid amount');
        }
        
        // Determine payment type
        $paymentType = strtolower(sanitize_text_field($data['payment_type'] ?? 'credit'));
        if (!in_array($paymentType, ['credit', 'later'])) {
            throw new \InvalidArgumentException('Invalid payment type. Must be "credit" or "later"');
        }

        // payment_method_id required only for card payments
        if ($paymentType === 'credit' && (empty($data['payment_method']) || !preg_match('/^pm_[a-zA-Z0-9]{24,}$/', $data['payment_method']))) {
            throw new \InvalidArgumentException('Invalid payment method');
        }
        
        if (empty($data['email']) || !is_email($data['email'])) {
            throw new \InvalidArgumentException('Invalid email');
        }

        // Validate customer_id is provided and valid
        $customerId = absint($data['customer_id'] ?? 0);
        if ($customerId <= 0) {
            throw new \InvalidArgumentException('customer_id is required');
        }

        // Verify customer exists
        try {
            $customer = CustomerModel::find($customerId);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Failed to verify customer');
        }
        
        if (!$customer) {
            throw new \InvalidArgumentException('Customer not found');
        }

        return new class(
            round((float)($data['amount'] ?? 0), 2),
            strtolower(sanitize_text_field($data['currency'] ?? 'usd')),
            sanitize_text_field($data['payment_method'] ?? ''),
            sanitize_email($data['email'] ?? ''),
            $customerId,
            $data['metadata'] ?? [],
            sanitize_text_field($data['idempotency_key'] ?? wp_generate_uuid4()),
            $paymentType
        ) {
            public function __construct(
                private float $amount,
                private string $currency,
                private string $paymentMethodId,
                private string $email,
                private int $customerId,
                private array $metadata,
                private string $idempotencyKey,
                private string $paymentType
            ) {}
    /**
     * Get payment amount.
     *
     * @return float
     */
            public function getAmount(): float { return $this->amount; }
    /**
     * Get payment currency.
     *
     * @return string
     */
            public function getCurrency(): string { return $this->currency; }
    /**
     * Get payment method ID.
     *
     * @return string
     */
            public function getPaymentMethodId(): string { return $this->paymentMethodId; }
    /**
     * Get customer email.
     *
     * @return string
     */
            public function getEmail(): string { return $this->email; }
    /**
     * Get customer ID.
     *
     * @return int
     */
            public function getCustomerId(): int { return $this->customerId; }
    /**
     * Get payment metadata.
     *
     * @return array
     */
            public function getMetadata(): array { return $this->metadata; }
    /**
     * Get idempotency key.
     *
     * @return string
     */
            public function getIdempotencyKey(): string { return $this->idempotencyKey; }
    /**
     * Get payment type.
     *
     * @return string
     */
            public function getPaymentType(): string { return $this->paymentType; }
        };
    }

    /**
     * Check payment rate limit for the current client.
     *
     * @return bool
     */
    private function checkRateLimit(): bool
    {
        $ip = $this->getClientIp();
        $key = 'payment_rate_limit_' . md5($ip);
        $attempts = (int) get_transient($key);
        
        if ($attempts >= 5) {
            return false;
        }
        
        set_transient($key, $attempts + 1, 300);
        return true;
    }

    /**
     * Check whether the request is HTTPS or in development mode.
     *
     * @return bool
     */
    private function isHttpsOrDev(): bool
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return true;
        }
        return is_ssl();
    }

    /**
     * Verify guest payment security fields.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return bool
     */
    private function verifyGuestSecurity(WP_REST_Request $request): bool
    {
        $honeypot = $request->get_param('website');
        if (!empty($honeypot)) {
            return false;
        }
        return true;
    }

    /**
     * Get the client IP address.
     *
     * @return string
     */
    private function getClientIp(): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])));
            $ip = trim($ips[0]);
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
}
