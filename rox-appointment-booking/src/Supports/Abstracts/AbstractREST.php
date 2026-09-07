<?php

/**
 * Abstract REST API Class  Booking Engine
 *
 * This class provides base functionality for implementing REST API endpoints
 * with proper routing, request handling, and permission checks.
 *
 * @package RoxAppointmentBooking
 * @subpackage Supports\Abstracts
 * @since 1.0.0
 */

namespace RoxAppointmentBooking\Supports\Abstracts;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Security;

abstract class AbstractREST
{
    /**
     * REST API Version
     */
    protected static string $version = 'v1';

    /**
     * REST API Namespace
     */
    protected static string $namespace = ROX_APPOINTMENT_BOOKING_TEXT_DOMAIN;

    /**
     * REST API Route
     */
    public static string $route = '';

    /**
     * REST API Route for frontend access
     */
    public static string $usableRoute = '';

    /**
     * Initialize the REST API
     */
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Get the full REST API route
     */
    public static function getFullRoute(): string
    {
        // return full route including namespace, version, and specific route and WordPress URL
        return sprintf(
            '%s/%s/%s%s',
            rest_url(),
            static::$namespace,
            static::$version,
            (static::$usableRoute
                ? static::$usableRoute
                : static::$route)
        );
    }

    /**
     * Register REST API routes
     */
    public function registerRoutes(): void
    {
        register_rest_route(
            static::$namespace . '/' . static::$version,
            static::$route,
            [
                'methods'             => $this->getMethods(),
                'callback'            => [$this, 'secureHandleRequest'],
                'permission_callback' => [$this, 'permissionCheck'],
                'args' => []
            ]
        );
    }

    /**
     * Secure wrapper for handling requests
     */
    public function secureHandleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        // Check HTTPS in production
        // if (!is_ssl() && defined('WP_DEBUG') && !WP_DEBUG) {
        //     return new WP_Error(
        //         'ssl_required',
        //         esc_html__('HTTPS is required for this endpoint', 'rox-appointment-booking'),
        //         ['status' => 403]
        //     );
        // }

        // Rate limiting. Logged-out callers all report user id 0, so keying on
        // that alone drops every anonymous visitor into one shared bucket — a
        // single caller could then lock the public endpoints for the whole
        // site. Bucket them by client IP instead.
        $user_id = get_current_user_id();
        $rate_limit_identity = $user_id > 0 ? (string) $user_id : 'ip_' . self::getRateLimitClientIp();
        $rate_limit_key = $rate_limit_identity . '_' . static::$route;
        if (!Security::rateLimitCheck($rate_limit_key, 100, 60)) {
            return new WP_Error(
                'rate_limit_exceeded',
                esc_html__('Too many requests. Please try again later.', 'rox-appointment-booking'),
                ['status' => 429]
            );
        }

        // Language for this request. The booking panel and customer panel are
        // JS apps calling /wp-json directly, so the page's language is not
        // implied by the REST URL on every WPML URL format — they pass it as
        // `lang` and it is honoured here, for every endpoint at once.
        //
        // `lang` arrives on unauthenticated public requests and is handed to
        // WPML, so it is whitelisted against the site's active languages rather
        // than merely escaped; anything unknown falls back to the default.
        // The switch is always unwound, including when handleRequest() throws:
        // a leaked switch would corrupt everything rendered later in the same
        // PHP process, e-mails included.
        $lang = $request->get_param('lang');

        if ($lang === null || !function_exists('rox_appointment_booking_with_language')) {
            return $this->handleRequest($request);
        }

        return rox_appointment_booking_with_language(
            (string) $lang,
            fn() => $this->handleRequest($request)
        );
    }

    /**
     * Get the client IP address, used to bucket anonymous callers for rate
     * limiting.
     *
     * Deliberately not named getClientIp(): PaymentProcess (a subclass) already
     * declares a private non-static getClientIp(), and a same-named private
     * static here would differ in staticness across the hierarchy.
     *
     * @return string
     */
    private static function getRateLimitClientIp(): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])));
            $ip = trim($ips[0]);
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    /**
     * Handle the REST API request
     */
    abstract public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error;

    /**
     * Check if the user has permission to access the endpoint
     */
    abstract public function permissionCheck(WP_REST_Request $request): bool;

    /**
     * Get allowed HTTP methods
     */
    abstract protected function getMethods(): string|array;
}
