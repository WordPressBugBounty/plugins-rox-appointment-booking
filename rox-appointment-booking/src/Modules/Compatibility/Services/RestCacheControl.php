<?php

/**
 * Class RestCacheControl
 *
 * @package RoxAppointmentBooking
 * @subpackage Modules\Compatibility\Services
 * @since 1.0.0
 *
 * Keeps this plugin's REST responses out of page caches.
 */

namespace RoxAppointmentBooking\Modules\Compatibility\Services;

use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Server;

if (! defined('ABSPATH')) exit; // Exit if accessed directly


class RestCacheControl
{
    /**
     * Whether the service should be loadable.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * LiteSpeed Cache's main plugin file, relative to the plugins directory.
     */
    private const LITESPEED_PLUGIN = 'litespeed-cache/litespeed-cache.php';

    /**
     * Constructor.
     *
     * The headers below only matter where something is caching the REST API,
     * so they are attached only while LiteSpeed Cache is active.
     */
    public function __construct()
    {
        // `is_plugin_active()` lives in an admin include. wp-settings.php loads
        // that file for every request on current WordPress, but older versions
        // only loaded it inside wp-admin — where calling it unguarded on a
        // frontend or REST request is a fatal error, not a failed check.
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (!is_plugin_active(self::LITESPEED_PLUGIN)) {
            return;
        }

        add_filter('rest_post_dispatch', [$this, 'sendNoCacheHeaders'], 10, 3);
    }

    /**
     * Marks every response on this plugin's namespace as uncacheable.
     *
     * The admin app and the booking panel read their state back over these
     * routes right after writing it. A cache that serves a stale GET makes a
     * successful save look like it never happened: the "saved" notice appears,
     * then the next read hands back the old value and the UI snaps back.
     *
     * WordPress already sends nocache headers for logged-in callers, but
     * LiteSpeed caches the REST API on its own header (`Cache REST API`, on by
     * default) and never sees them, so the headers alone are not enough — its
     * documented opt-out is fired below, and `DONOTCACHEPAGE` covers the other
     * cache plugins that honour it.
     *
     * @param WP_HTTP_Response $response
     * @param WP_REST_Server $server
     * @param WP_REST_Request $request
     * @return WP_HTTP_Response
     */
    public function sendNoCacheHeaders($response, $server, $request)
    {
        if (!($response instanceof WP_HTTP_Response) || !($request instanceof WP_REST_Request)) {
            return $response;
        }

        if (strpos(ltrim($request->get_route(), '/'), ROX_APPOINTMENT_BOOKING_TEXT_DOMAIN . '/') !== 0) {
            return $response;
        }

        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', 'Wed, 11 Jan 1984 05:00:00 GMT');
        $response->header('X-LiteSpeed-Cache-Control', 'no-cache');

        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }

        do_action('litespeed_control_set_nocache', 'rox appointment booking rest response');

        return $response;
    }
}
