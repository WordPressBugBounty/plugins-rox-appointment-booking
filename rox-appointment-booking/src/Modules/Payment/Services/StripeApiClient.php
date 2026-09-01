<?php

/**
 * Class StripeApiClient
 *
 * @package RoxAppointmentBooking
 * @subpackage Modules\Payment\Services
 * @since 1.0.0
 *
 * The plugin's only door to the Stripe REST API. It replaces the stripe-php
 * SDK: no Composer package, no scoped vendor namespace, nothing but the
 * WordPress HTTP API.
 *
 * Everything a caller needs to know about a request comes back in one shape,
 * so no caller ever has to catch an exception or read a transport detail:
 *
 *   [
 *     'success' => bool,
 *     'status'  => int,    // HTTP status; 0 when the request never reached Stripe
 *     'data'    => array,  // decoded response body on success
 *     'error'   => [       // present when success is false
 *        'type'         => 'card_error' | 'invalid_request_error' | 'api_error'
 *                        | 'authentication_error' | 'rate_limit_error'
 *                        | 'api_connection_error' | 'invalid_response',
 *        'code'         => string,
 *        'decline_code' => string,
 *        'param'        => string,
 *        'message'      => string,
 *     ],
 *   ]
 *
 * The first five error types are Stripe's own. The last two are ours and cover
 * what an SDK would have raised as a connection exception: `api_connection_error`
 * is a WP_Error from the transport (DNS, TLS, timeout, blocked outbound request)
 * and `invalid_response` is a 2xx that did not carry parseable JSON.
 */

namespace RoxAppointmentBooking\Modules\Payment\Services;

defined('ABSPATH') || exit;

class StripeApiClient
{
    /**
     * Not hooked.
     *
     * @var bool
     */
    public static $loadable = false;

    /**
     * Stripe REST base.
     */
    private const API_BASE = 'https://api.stripe.com/v1';

    /**
     * Pinned Stripe API version.
     */
    private const API_VERSION = '2026-03-25.dahlia';

    /**
     * Reported to Stripe so charges are attributable in the dashboard, exactly as
     * Stripe::setAppInfo() did.
     */
    private const APP_NAME = 'RoxAppointmentBooking';

    /**
     * Seconds before a Stripe call is abandoned.
     */
    private const TIMEOUT = 30;

    /**
     * The secret key this client authenticates with.
     *
     * @var string
     */
    private string $secretKey;

    /**
     * Constructor.
     *
     * @param string $secretKey Stripe secret key (sk_test_… / sk_live_…).
     * @return void
     */
    public function __construct(string $secretKey)
    {
        $this->secretKey = $secretKey;
    }

    /**
     * GET a Stripe resource.
     *
     * @param string $path  Path below /v1, e.g. "/payment_intents/pi_123".
     * @param array  $query Query parameters, encoded in Stripe bracket notation.
     * @return array Normalised response, see the class docblock.
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query, []);
    }

    /**
     * POST to a Stripe resource.
     *
     * @param string $path    Path below /v1, e.g. "/payment_intents".
     * @param array  $params  Body parameters, form-encoded in bracket notation.
     * @param array  $options Optional: ['idempotency_key' => string].
     * @return array Normalised response, see the class docblock.
     */
    public function post(string $path, array $params = [], array $options = []): array
    {
        return $this->request('POST', $path, $params, $options);
    }

    /**
     * Perform an authenticated Stripe request.
     *
     * @param string $method  HTTP method.
     * @param string $path    Path below /v1.
     * @param array  $params  Parameters. Query string for GET, body for POST.
     * @param array  $options Optional: ['idempotency_key' => string].
     * @return array Normalised response, see the class docblock.
     */
    public function request(string $method, string $path, array $params = [], array $options = []): array
    {
        if ($this->secretKey === '') {
            return self::fail('authentication_error', 'Stripe is not configured.');
        }

        $method = strtoupper($method);
        $url = self::API_BASE . '/' . ltrim($path, '/');
        $encoded = self::buildQuery($params);

        $args = [
            'method'      => $method,
            'timeout'     => self::TIMEOUT,
            'redirection' => 0,
            'httpversion' => '1.1',
            // Never weaken this.
            'sslverify'   => true,
            'headers'     => $this->headers($method, $options),
        ];

        if ($method === 'GET') {
            if ($encoded !== '') {
                $url .= '?' . $encoded;
            }
        } else {
            $args['body'] = $encoded;
        }

        // $args carries the secret key in its Authorization header.
        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return self::fail(
                'api_connection_error',
                'Could not reach Stripe: ' . $response->get_error_message()
            );
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            return self::fail(
                'invalid_response',
                'Stripe returned a response that could not be read.',
                $status
            );
        }

        if ($status >= 200 && $status < 300) {
            return [
                'success' => true,
                'status'  => $status,
                'data'    => $decoded,
                'error'   => [],
            ];
        }

        return self::stripeError($decoded, $status);
    }

    /**
     * Encode parameters the way Stripe's API expects them.
     *
     * @param array $params Nested parameter array.
     * @return string URL-encoded body/query string, empty when there is nothing to send.
     */
    public static function buildQuery(array $params): string
    {
        $flat = [];
        self::flatten($params, '', $flat);

        $pairs = [];
        foreach ($flat as $key => $value) {
            $pairs[] = rawurlencode($key) . '=' . rawurlencode($value);
        }

        return implode('&', $pairs);
    }

    /**
     * Flatten a nested array into Stripe's bracket keys.
     *
     * @param array  $params Parameters at this level.
     * @param string $prefix Accumulated key prefix.
     * @param array  $out    Flat key => scalar-string map, by reference.
     * @return void
     */
    private static function flatten(array $params, string $prefix, array &$out): void
    {
        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }

            $name = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';

            if (is_array($value)) {
                // An empty array carries no parameters; sending nothing is right, and sending
                // "key=" would read as an unset.
                if ($value === []) {
                    continue;
                }
                self::flatten($value, $name, $out);
                continue;
            }

            $out[$name] = self::scalar($value);
        }
    }

    /**
     * Render one scalar the way Stripe's form parser reads it.
     *
     * @param mixed $value Scalar value.
     * @return string
     */
    private static function scalar($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_float($value)) {
            // Never hand Stripe scientific notation or a locale decimal comma.
            return rtrim(rtrim(sprintf('%.8F', $value), '0'), '.');
        }

        return (string) $value;
    }

    /**
     * Build the request headers.
     *
     * @param string $method  HTTP method.
     * @param array  $options Optional: ['idempotency_key' => string].
     * @return array
     */
    private function headers(string $method, array $options): array
    {
        $version = (string) apply_filters('rox_appointment_booking_stripe_api_version', self::API_VERSION);
        $appVersion = defined('ROX_APPOINTMENT_BOOKING_VERSION') ? ROX_APPOINTMENT_BOOKING_VERSION : '1.0.0';

        $headers = [
            'Authorization'  => 'Bearer ' . $this->secretKey,
            'Stripe-Version' => $version,
            'Accept'         => 'application/json',
            'User-Agent'     => sprintf('%s/%s (%s)', self::APP_NAME, $appVersion, home_url()),
            // The SDK sent this so Stripe can attribute traffic to a plugin.
            'X-Stripe-Client-User-Agent' => (string) wp_json_encode([
                'application' => [
                    'name'    => self::APP_NAME,
                    'version' => $appVersion,
                    'url'     => home_url(),
                ],
                'lang'         => 'php',
                'lang_version' => PHP_VERSION,
                'publisher'    => 'roxnor',
                'uname'        => 'wordpress',
            ]),
        ];

        if ($method !== 'GET') {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        // Stripe deduplicates a retried POST by this header for 24 hours, which is what stops a
        // double-submitted checkout charging twice.
        $idempotencyKey = (string) ($options['idempotency_key'] ?? '');
        if ($method === 'POST' && $idempotencyKey !== '') {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return $headers;
    }

    /**
     * Normalise Stripe's own error object.
     *
     * @param array $decoded Decoded response body.
     * @param int   $status  HTTP status.
     * @return array
     */
    private static function stripeError(array $decoded, int $status): array
    {
        $error = isset($decoded['error']) && is_array($decoded['error']) ? $decoded['error'] : [];

        return [
            'success' => false,
            'status'  => $status,
            'data'    => [],
            'error'   => [
                'type'         => (string) ($error['type'] ?? 'api_error'),
                'code'         => (string) ($error['code'] ?? ''),
                'decline_code' => (string) ($error['decline_code'] ?? ''),
                'param'        => (string) ($error['param'] ?? ''),
                'message'      => (string) ($error['message'] ?? 'Stripe rejected the request.'),
            ],
        ];
    }

    /**
     * Build a failure that never reached Stripe, or came back unreadable.
     *
     * @param string $type    Our own error type.
     * @param string $message Human-readable reason.
     * @param int    $status  HTTP status, 0 when there was none.
     * @return array
     */
    private static function fail(string $type, string $message, int $status = 0): array
    {
        return [
            'success' => false,
            'status'  => $status,
            'data'    => [],
            'error'   => [
                'type'         => $type,
                'code'         => '',
                'decline_code' => '',
                'param'        => '',
                'message'      => $message,
            ],
        ];
    }
}
