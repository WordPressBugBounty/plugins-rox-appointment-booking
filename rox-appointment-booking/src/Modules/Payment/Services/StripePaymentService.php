<?php

namespace RoxAppointmentBooking\Modules\Payment\Services;

defined('ABSPATH') || exit;

use RoxAppointmentBookingVendors\Stripe\Stripe;
use RoxAppointmentBookingVendors\Stripe\Account;
use RoxAppointmentBookingVendors\Stripe\PaymentIntent;
use RoxAppointmentBookingVendors\Stripe\Exception\ApiErrorException;

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
        $currency = strtolower($settings['stripe_currency'] ?? 'usd');
        if (!in_array($currency, rox_appointment_booking_stripe_supported_currencies())) {
            $currency = 'usd';
        }
        $this->currency = $currency;

        if (!empty($this->secretKey)) {
            Stripe::setApiKey($this->secretKey);
            Stripe::setAppInfo('RoxAppointmentBooking', '1.0.0', home_url());
        }
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

        try {
            $sanitizedMetadata = $this->sanitizeMetadata($metadata);
            $sanitizedMetadata['ip_address'] = $this->getClientIp();
            $userAgent = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? ''));
            $sanitizedMetadata['user_agent'] = $userAgent ? substr($userAgent, 0, 255) : 'Unknown';
            $sanitizedMetadata['timestamp'] = current_time('mysql');

            $paymentIntent = PaymentIntent::create([
                'amount' => $this->convertToSmallestUnit($amount),
                'currency' => $this->currency,
                'payment_method' => $paymentMethodId,
                'confirm' => true,
                'metadata' => $sanitizedMetadata,
                'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
                'description' => sprintf('Booking payment - %s', wp_hash($idempotencyKey)),
            ], ['idempotency_key' => $idempotencyKey]);

            $this->storeIdempotencyKey($idempotencyKey, $paymentIntent->id);

            return [
                'success' => true,
                'payment_intent_id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
                'amount' => $amount,
            ];
        } catch (ApiErrorException $e) {
            return [
                'success' => false,
                'error' => 'Payment processing failed. Please try again.',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'System error occurred'];
        }
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

        try {
            Stripe::setApiKey($secretKey);
            Stripe::setAppInfo('RoxAppointmentBooking', '1.0.0', home_url());

            $account = Account::retrieve($secretKey);
            $settings = rox_appointment_booking_payment_settings() ?? [];
            $settings['stripe_publishable_key'] = $publishableKey;
            $settings['stripe_secret_key'] = $secretKey;
            $settings['stripe_connection_status'] = 'connected';
            $settings['stripe_account_id'] = sanitize_text_field($account->id ?? '');
            $settings['stripe_account_email'] = sanitize_email($account->email ?? '');
            $settings['stripe_account_country'] = sanitize_text_field($account->country ?? '');
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
        } catch (ApiErrorException $e) {
            return [
                'success' => false,
                'error' => 'Stripe keys are invalid or Stripe rejected the connection.',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Unable to connect Stripe. Please try again.'];
        }
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
