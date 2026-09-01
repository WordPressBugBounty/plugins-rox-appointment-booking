<?php

namespace RoxAppointmentBooking\Modules\Payment\Services;

defined('ABSPATH') || exit;

/**
 * Class StripePaymentService
 *
 * @package RoxAppointmentBooking\Modules\Payment
 * @description Handles StripePaymentService functionality.
 */
class StripePaymentService
{
    /**
     * Whether this class should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;
    private const MAX_AMOUNT = 999999.99;
    private const MIN_AMOUNT = 0.50;
    private const IDEMPOTENCY_EXPIRY = 86400;
    private const ZERO_DECIMAL_CURRENCIES = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];
    private const THREE_DECIMAL_CURRENCIES = ['bhd', 'jod', 'kwd', 'omr', 'tnd'];

    /**
     * Metadata keys that tie a PaymentIntent back to something in this database.
     */
    private const RELATIONSHIP_KEYS = ['booking_id', 'order_id', 'customer_id'];

    private string $secretKey;
    private string $publishableKey;
    private string $connectionStatus;
    private string $currency;

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $settings = rox_appointment_booking_payment_settings() ?? [];
        $this->secretKey = $settings['stripe_secret_key'] ?? '';
        $this->publishableKey = $settings['stripe_publishable_key'] ?? '';
        $this->connectionStatus = $settings['stripe_connection_status'] ?? '';
        $currency = strtolower($settings['payment_currency'] ?? 'usd');
        if (!in_array($currency, rox_appointment_booking_stripe_supported_currencies())) {
            $currency = 'usd';
        }
        $this->currency = $currency;
    }

    /**
     * The API client for the stored secret key.
     *
     * @return StripeApiClient
     */
    private function client(): StripeApiClient
    {
        return new StripeApiClient($this->secretKey);
    }

    /**
     * Create and confirm a Stripe payment intent.
     *
     * @param float $amount Payment amount.
     * @param string $paymentMethodId Stripe payment method ID.
     * @param array $metadata Payment metadata.
     * @param string $idempotencyKey Optional idempotency key.
     * @return array
     */
    public function createAndConfirmPayment(float $amount, string $paymentMethodId, array $metadata = [], string $idempotencyKey = ''): array
    {
        if (!$this->validateAmount($amount)) {
            return ['success' => false, 'error' => 'Invalid amount'];
        }

        if (!$this->validatePaymentMethodId($paymentMethodId)) {
            return ['success' => false, 'error' => 'Invalid payment method'];
        }

        if (empty($idempotencyKey)) {
            $idempotencyKey = $this->generateIdempotencyKey($amount, $paymentMethodId);
        }

        if ($this->isDuplicateTransaction($idempotencyKey)) {
            return ['success' => false, 'error' => 'Duplicate transaction detected'];
        }

        $sanitizedMetadata = $this->sanitizeMetadata($metadata);
        $relationship = array_intersect_key($sanitizedMetadata, array_flip(self::RELATIONSHIP_KEYS));

        $sanitizedMetadata['ip_address'] = $this->getClientIp();
        $userAgent = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $sanitizedMetadata['user_agent'] = $userAgent ? substr($userAgent, 0, 255) : 'Unknown';
        $sanitizedMetadata['timestamp'] = current_time('mysql');

        $minorAmount = $this->convertToSmallestUnit($amount);

        $response = $this->client()->post(
            '/payment_intents',
            [
                'amount' => $minorAmount,
                'currency' => $this->currency,
                'payment_method' => $paymentMethodId,
                'confirm' => true,
                'metadata' => $sanitizedMetadata,
                'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
                'description' => sprintf('Booking payment - %s', wp_hash($idempotencyKey)),
            ],
            ['idempotency_key' => $idempotencyKey]
        );

        if (!$response['success']) {
            $this->logApiError($response['error'], $amount, $paymentMethodId);

            return [
                'success' => false,
                'error' => $this->buildCustomerFacingError($response['error']),
            ];
        }

        $paymentIntent = $response['data'];

        // Confirm that what Stripe created is what was asked for.
        $mismatch = $this->intentMismatch($paymentIntent, $minorAmount, $relationship);
        if ($mismatch !== '') {
            $this->logMismatch($mismatch, (string) ($paymentIntent['id'] ?? ''));

            return ['success' => false, 'error' => 'Payment could not be verified. Please contact us before trying again.'];
        }

        $this->storeIdempotencyKey($idempotencyKey, (string) $paymentIntent['id']);

        return [
            'success' => true,
            'payment_intent_id' => (string) $paymentIntent['id'],
            'status' => (string) ($paymentIntent['status'] ?? ''),
            'amount' => $amount,
        ];
    }

    /**
     * Retrieve a payment intent.
     *
     * @param string $paymentIntentId Stripe payment intent ID.
     * @return array ['success' => bool, 'payment_intent' => array, 'error' => string]
     */
    public function retrievePaymentIntent(string $paymentIntentId): array
    {
        if (!$this->validatePaymentIntentId($paymentIntentId)) {
            return ['success' => false, 'payment_intent' => [], 'error' => 'Invalid payment reference'];
        }

        $response = $this->client()->get('/payment_intents/' . rawurlencode($paymentIntentId));

        if (!$response['success']) {
            $this->logApiError($response['error'], 0.0, '');

            return [
                'success' => false,
                'payment_intent' => [],
                'error' => $this->buildCustomerFacingError($response['error']),
            ];
        }

        return ['success' => true, 'payment_intent' => $response['data'], 'error' => ''];
    }

    /**
     * Verify server-side that a payment intent really settled, for the expected amount, in the
     * expected currency, against the expected booking or order.
     *
     * @param string $paymentIntentId Stripe payment intent ID.
     * @param float  $expectedAmount  Amount in major units, as this site stored it.
     * @param array  $expectedMetadata Relationship keys, e.g. ['booking_id' => 42].
     * @return array ['success' => bool, 'status' => string, 'payment_intent' => array, 'error' => string]
     */
    public function verifyPaymentIntent(string $paymentIntentId, float $expectedAmount, array $expectedMetadata = []): array
    {
        $retrieved = $this->retrievePaymentIntent($paymentIntentId);

        if (!$retrieved['success']) {
            return ['success' => false, 'status' => '', 'payment_intent' => [], 'error' => $retrieved['error']];
        }

        $paymentIntent = $retrieved['payment_intent'];
        $status = (string) ($paymentIntent['status'] ?? '');

        $mismatch = $this->intentMismatch(
            $paymentIntent,
            $this->convertToSmallestUnit($expectedAmount),
            $this->sanitizeMetadata($expectedMetadata)
        );

        if ($mismatch !== '') {
            $this->logMismatch($mismatch, $paymentIntentId);

            return ['success' => false, 'status' => $status, 'payment_intent' => $paymentIntent, 'error' => 'Payment could not be verified.'];
        }

        if ($status !== 'succeeded') {
            return ['success' => false, 'status' => $status, 'payment_intent' => $paymentIntent, 'error' => 'Payment has not completed.'];
        }

        return ['success' => true, 'status' => $status, 'payment_intent' => $paymentIntent, 'error' => ''];
    }

    /**
     * Refund a payment, in full or in part.
     *
     * @param string $paymentIntentId Stripe payment intent ID.
     * @param float|null $amount Amount in major units; null refunds the full charge.
     * @param string $reason One of duplicate|fraudulent|requested_by_customer.
     * @return array ['success' => bool, 'refund_id' => string, 'status' => string, 'error' => string]
     */
    public function createRefund(string $paymentIntentId, ?float $amount = null, string $reason = ''): array
    {
        if (!$this->validatePaymentIntentId($paymentIntentId)) {
            return ['success' => false, 'refund_id' => '', 'status' => '', 'error' => 'Invalid payment reference'];
        }

        $params = ['payment_intent' => $paymentIntentId];

        if ($amount !== null) {
            if ($amount <= 0 || $amount > self::MAX_AMOUNT) {
                return ['success' => false, 'refund_id' => '', 'status' => '', 'error' => 'Invalid refund amount'];
            }
            $params['amount'] = $this->convertToSmallestUnit($amount);
        }

        if (in_array($reason, ['duplicate', 'fraudulent', 'requested_by_customer'], true)) {
            $params['reason'] = $reason;
        }

        // Keyed on the intent and the amount so a retried refund cannot double-refund.
        $response = $this->client()->post('/refunds', $params, [
            'idempotency_key' => wp_hash('refund_' . $paymentIntentId . '_' . ($params['amount'] ?? 'full')),
        ]);

        if (!$response['success']) {
            $this->logApiError($response['error'], (float) ($amount ?? 0), '');

            return [
                'success' => false,
                'refund_id' => '',
                'status' => '',
                'error' => $this->buildCustomerFacingError($response['error']),
            ];
        }

        return [
            'success' => true,
            'refund_id' => (string) ($response['data']['id'] ?? ''),
            'status' => (string) ($response['data']['status'] ?? ''),
            'error' => '',
        ];
    }

    /**
     * Compare a Stripe payment intent against what this site expected.
     *
     * @param array $paymentIntent Decoded payment intent.
     * @param int   $expectedMinor Expected amount in the smallest currency unit.
     * @param array $expectedMetadata Relationship metadata that must match.
     * @return string Empty when everything matches, otherwise the reason.
     */
    private function intentMismatch(array $paymentIntent, int $expectedMinor, array $expectedMetadata): string
    {
        $id = (string) ($paymentIntent['id'] ?? '');
        if (!$this->validatePaymentIntentId($id)) {
            return 'missing or malformed payment intent id';
        }

        if ((int) ($paymentIntent['amount'] ?? -1) !== $expectedMinor) {
            return sprintf('amount mismatch: stripe=%d expected=%d', (int) ($paymentIntent['amount'] ?? -1), $expectedMinor);
        }

        $currency = strtolower((string) ($paymentIntent['currency'] ?? ''));
        if ($currency !== $this->currency) {
            return sprintf('currency mismatch: stripe=%s expected=%s', $currency, $this->currency);
        }

        $stripeMetadata = isset($paymentIntent['metadata']) && is_array($paymentIntent['metadata'])
            ? $paymentIntent['metadata']
            : [];

        foreach (self::RELATIONSHIP_KEYS as $key) {
            if (!isset($expectedMetadata[$key])) {
                continue;
            }

            if ((string) ($stripeMetadata[$key] ?? '') !== (string) $expectedMetadata[$key]) {
                return sprintf('%s mismatch: stripe=%s expected=%s', $key, (string) ($stripeMetadata[$key] ?? ''), (string) $expectedMetadata[$key]);
            }
        }

        return '';
    }

    /**
     * Log a verification mismatch.
     *
     * @param string $reason Mismatch reason.
     * @param string $paymentIntentId Stripe payment intent ID.
     * @return void
     */
    private function logMismatch(string $reason, string $paymentIntentId): void
    {
        error_log(sprintf('[ROX STRIPE] payment verification failed | intent=%s | %s', $paymentIntentId, $reason));
    }

    /**
     * Log the underlying Stripe failure so the generic customer-facing message can still be
     * traced back to a real cause.
     *
     * @param array $error Normalised error from StripeApiClient.
     * @param float $amount Payment amount.
     * @param string $paymentMethodId Stripe payment method ID.
     * @return void
     */
    private function logApiError(array $error, float $amount, string $paymentMethodId): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        error_log(sprintf(
            '[ROX STRIPE] %s | type=%s code=%s decline_code=%s param=%s | currency=%s amount_minor=%d pm=%s',
            $error['message'] ?? '',
            $error['type'] ?? '',
            $error['code'] ?? '',
            $error['decline_code'] ?? '',
            $error['param'] ?? '',
            $this->currency,
            $this->convertToSmallestUnit($amount),
            $paymentMethodId
        ));
    }

    /**
     * Build a customer-facing message from a Stripe API error.
     *
     * @param array $error Normalised error from StripeApiClient.
     * @return string
     */
    private function buildCustomerFacingError(array $error): string
    {
        $type = (string) ($error['type'] ?? '');
        $code = (string) ($error['code'] ?? '');

        if ($type === 'card_error') {
            $message = sanitize_text_field((string) ($error['message'] ?? ''));
            return $message !== '' ? $message : 'Your card was declined. Please try another card.';
        }

        if ($code === 'amount_too_small') {
            return sprintf('The amount is below the minimum payment Stripe accepts in %s.', strtoupper($this->currency));
        }

        if ($code === 'amount_too_large') {
            return sprintf('The amount is above the maximum payment Stripe accepts in %s.', strtoupper($this->currency));
        }

        if ($type === 'api_connection_error') {
            return 'Could not reach the payment provider. Please try again in a moment.';
        }

        return 'Payment processing failed. Please try again.';
    }

    /**
     * Connect Stripe account using provided API keys.
     *
     * @param string $publishableKey Stripe publishable key.
     * @param string $secretKey Stripe secret key.
     * @return array
     */
    public function connect(string $publishableKey, string $secretKey): array
    {
        $publishableKey = sanitize_text_field(wp_unslash($publishableKey));
        $secretKey = sanitize_text_field(wp_unslash($secretKey));

        $validationError = $this->validateStripeKeys($publishableKey, $secretKey);
        if ($validationError !== '') {
            return ['success' => false, 'error' => $validationError];
        }

        // The submitted key, not the stored one: this call is what proves the key works before
        // anything is saved.
        $response = (new StripeApiClient($secretKey))->get('/account');

        if (!$response['success']) {
            if (($response['error']['type'] ?? '') === 'api_connection_error') {
                return ['success' => false, 'error' => 'Unable to reach Stripe. Please try again.'];
            }

            return [
                'success' => false,
                'error' => 'Stripe keys are invalid or Stripe rejected the connection.',
            ];
        }

        $account = $response['data'];

        $settings = rox_appointment_booking_payment_settings() ?? [];
        $settings['stripe_publishable_key'] = $publishableKey;
        $settings['stripe_secret_key'] = $secretKey;
        $settings['stripe_connection_status'] = 'connected';
        $settings['stripe_account_id'] = sanitize_text_field((string) ($account['id'] ?? ''));
        $settings['stripe_account_email'] = sanitize_email((string) ($account['email'] ?? ''));
        $settings['stripe_account_country'] = sanitize_text_field((string) ($account['country'] ?? ''));
        $settings['stripe_mode'] = str_starts_with($secretKey, 'sk_live_') ? 'live' : 'test';
        $settings['stripe_connected_at'] = current_time('mysql');
        $settings['stripe_payment_gateway_enable'] = true;

        update_option('rox_appointment_booking_payments_settings', $settings);

        return [
            'success' => true,
            'status' => 'connected',
            'account_id' => $settings['stripe_account_id'],
            'email' => $settings['stripe_account_email'],
            'country' => $settings['stripe_account_country'],
            'mode' => $settings['stripe_mode'],
        ];
    }

    /**
     * Disconnect Stripe account and clear related settings.
     *
     * @return array
     */
    public function disconnect(): array
    {
        $settings = rox_appointment_booking_payment_settings() ?? [];
        $settings['stripe_connection_status'] = 'disconnected';
        $settings['stripe_account_id'] = '';
        $settings['stripe_account_email'] = '';
        $settings['stripe_account_country'] = '';
        $settings['stripe_mode'] = '';
        $settings['stripe_connected_at'] = '';

        update_option('rox_appointment_booking_payments_settings', $settings);

        return [
            'success' => true,
            'status' => 'disconnected',
        ];
    }

    /**
     * Get current Stripe connection status and account details.
     *
     * @return array
     */
    public function getConnectionStatus(): array
    {
        $settings = rox_appointment_booking_payment_settings() ?? [];
        $status = sanitize_text_field($settings['stripe_connection_status'] ?? 'disconnected');

        if (empty($settings['stripe_secret_key']) || empty($settings['stripe_publishable_key'])) {
            $status = 'disconnected';
        }

        return [
            'success' => true,
            'status' => $status === 'connected' ? 'connected' : 'disconnected',
            'account_id' => sanitize_text_field($settings['stripe_account_id'] ?? ''),
            'email' => sanitize_email($settings['stripe_account_email'] ?? ''),
            'country' => sanitize_text_field($settings['stripe_account_country'] ?? ''),
            'mode' => sanitize_text_field($settings['stripe_mode'] ?? ''),
            'connected_at' => sanitize_text_field($settings['stripe_connected_at'] ?? ''),
        ];
    }

    /**
     * Convert amount to the smallest currency unit.
     *
     * @param float $amount Payment amount.
     * @return int
     */
    private function convertToSmallestUnit(float $amount): int
    {
        if (in_array($this->currency, self::ZERO_DECIMAL_CURRENCIES, true)) {
            return (int) round($amount);
        }

        if (in_array($this->currency, self::THREE_DECIMAL_CURRENCIES, true)) {
            return (int) round($amount * 100) * 10;
        }

        return (int) round($amount * 100);
    }

    /**
     * Check whether Stripe is configured.
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->secretKey) && !empty($this->publishableKey) && $this->connectionStatus === 'connected';
    }

    /**
     * Validate payment amount.
     *
     * @param float $amount Payment amount.
     * @return bool
     */
    private function validateAmount(float $amount): bool
    {
        return $amount >= self::MIN_AMOUNT && $amount <= self::MAX_AMOUNT;
    }

    /**
     * Validate Stripe payment method ID.
     *
     * @param string $id Stripe payment method ID.
     * @return bool
     */
    private function validatePaymentMethodId(string $id): bool
    {
        return preg_match('/^pm_[a-zA-Z0-9]{24,}$/', $id) === 1;
    }

    /**
     * Validate a Stripe payment intent ID.
     *
     * @param string $id Stripe payment intent ID.
     * @return bool
     */
    private function validatePaymentIntentId(string $id): bool
    {
        return preg_match('/^pi_[a-zA-Z0-9_]{10,}$/', $id) === 1;
    }

    /**
     * Validate Stripe API keys.
     *
     * @param string $publishableKey Stripe publishable key.
     * @param string $secretKey Stripe secret key.
     * @return string Empty if valid, error message if invalid.
     */
    private function validateStripeKeys(string $publishableKey, string $secretKey): string
    {
        if ($publishableKey === '' || $secretKey === '') {
            return 'Stripe publishable key and secret key are required.';
        }

        if (preg_match('/^pk_(test|live)_[A-Za-z0-9_]+$/', $publishableKey) !== 1) {
            return 'Invalid Stripe publishable key.';
        }

        if (preg_match('/^sk_(test|live)_[A-Za-z0-9_]+$/', $secretKey) !== 1) {
            return 'Invalid Stripe secret key.';
        }

        $publishableMode = str_starts_with($publishableKey, 'pk_live_') ? 'live' : 'test';
        $secretMode = str_starts_with($secretKey, 'sk_live_') ? 'live' : 'test';

        if ($publishableMode !== $secretMode) {
            return 'Stripe publishable key and secret key must both be test keys or both be live keys.';
        }

        return '';
    }

    /**
     * Sanitize payment metadata.
     *
     * @param array $metadata Payment metadata.
     * @return array
     */
    private function sanitizeMetadata(array $metadata): array
    {
        if (!is_array($metadata)) {
            return [];
        }
        $sanitized = [];
        foreach ($metadata as $key => $value) {
            $sanitizedKey = sanitize_key($key);
            $sanitizedValue = is_string($value) ? sanitize_text_field($value) : $value;
            if (strlen($sanitizedKey) <= 40 && strlen((string)$sanitizedValue) <= 500) {
                $sanitized[$sanitizedKey] = $sanitizedValue;
            }
        }
        return array_slice($sanitized, 0, 50);
    }

    /**
     * Generate an idempotency key.
     *
     * @param float $amount Payment amount.
     * @param string $paymentMethodId Stripe payment method ID.
     * @return string
     */
    private function generateIdempotencyKey(float $amount, string $paymentMethodId): string
    {
        return wp_hash(sprintf('%s_%s_%s_%s', $amount, $paymentMethodId, get_current_user_id(), time()));
    }

    /**
     * Check whether the idempotency key was already used.
     *
     * @param string $idempotencyKey Idempotency key.
     * @return bool
     */
    private function isDuplicateTransaction(string $idempotencyKey): bool
    {
        $cached = get_transient('rox_appointment_booking_idempotency_' . md5($idempotencyKey));
        return $cached !== false;
    }

    /**
     * Store an idempotency key for duplicate transaction checks.
     *
     * @param string $idempotencyKey Idempotency key.
     * @param string $transactionId Stripe transaction ID.
     * @return void
     */
    private function storeIdempotencyKey(string $idempotencyKey, string $transactionId): void
    {
        set_transient('rox_appointment_booking_idempotency_' . md5($idempotencyKey), $transactionId, self::IDEMPOTENCY_EXPIRY);
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
