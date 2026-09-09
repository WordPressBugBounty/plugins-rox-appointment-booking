<?php

namespace RoxAppointmentBooking\Modules\Multilingual\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Multilingual\Services\MultilingualService;
use RoxAppointmentBooking\Modules\Multilingual\Services\StringBackfillService;
use RoxAppointmentBooking\Modules\Multilingual\Services\JsStringBridge;

/**
 * Class RescanStrings
 *
 * @package RoxAppointmentBooking\Modules\Multilingual\REST
 * @description Re-registers every existing record's source strings with the
 *              active multilingual plugin. Needed whenever WPML is installed
 *              after the plugin, or after a bulk import that wrote rows without
 *              going through the Save* endpoints.
 */
class RescanStrings extends AbstractREST
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
    public static string $route = '/multilingual/rescan-strings';

    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/multilingual/rescan-strings';

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
     * `manage_options` on purpose, not Security::canManageBookings(): the latter
     * also admits the manager role and agents, and this rewrites the source
     * strings for every language on the site.
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
        if (!MultilingualService::instance()->isActive()) {
            return rox_appointment_booking_rest_response(
                data : null,
                code : 409,
                message : esc_html__('No supported multilingual plugin is active. Activate WPML and WPML String Translation first.', 'rox-appointment-booking'),
                headers : ['status' => 409]
            );
        }

        // The scan walks every record of every entity; a double-clicked button
        // must not start a second pass on top of the first.
        if (!StringBackfillService::acquireLock()) {
            return rox_appointment_booking_rest_response(
                data : null,
                code : 409,
                message : esc_html__('A scan is already running. Try again in a moment.', 'rox-appointment-booking'),
                headers : ['status' => 409]
            );
        }

        try {
            $result = StringBackfillService::run();
        } finally {
            // Released even if the scan throws, or the button stays dead for
            // the full transient lifetime.
            StringBackfillService::releaseLock();
        }

        update_option(StringBackfillService::DONE_OPTION, time());

        // Also re-register the JS/React UI strings and drop any cached
        // translations JSON, so a just-translated label shows up immediately
        // instead of waiting out JsStringBridge's cache. Time-bounded inside
        // registerAll(); whatever it does not reach is picked up by the
        // background batches on the next few admin page loads.
        //
        // Guarded because the record scan above has already succeeded and
        // been recorded: a failure in this second, additive step must not
        // turn the whole button into an error.
        $uiStringCount = 0;

        try {
            $uiStringCount = JsStringBridge::registerAll();
            JsStringBridge::flushCache();
        } catch (\Throwable $e) {
            JsStringBridge::resetProgress();
        }

        return rox_appointment_booking_rest_response(
            data : [
                'records'   => (int) $result['records'],
                'entities'  => array_map('intval', $result['entities']),
                'skipped'   => array_values(array_map('sanitize_key', $result['skipped'])),
                'uiStrings' => $uiStringCount,
            ],
            message : sprintf(
                // translators: %1$d = number of records scanned, %2$d = number of UI strings registered
                esc_html__('%1$d records and %2$d interface strings registered for translation.', 'rox-appointment-booking'),
                (int) $result['records'],
                $uiStringCount
            )
        );
    }
}
