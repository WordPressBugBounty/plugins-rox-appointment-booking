<?php

namespace RoxAppointmentBooking\Modules\FrontendBookingPanel\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;

/**
 * Class BookingPanelStructure
 *
 * @package RoxAppointmentBooking\Modules\FrontendBookingPanel\REST
 * @description Provides booking engine location data via REST API.
 */
class BookingPanelStructure extends AbstractREST
{
    /**
     * Whether this REST endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * REST route for the booking panel structure.
     *
     * @var string
     */
    public static string $route = 'booking-panel-structure';

    /**
     * Get the HTTP methods allowed for this route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'GET';
    }

    /**
     * Handle the REST API request.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return rox_appointment_booking_rest_response(
            data: $this->getInitialStructures(),
            message: array(
                'success' => array(
                    esc_html__('Booking Panel structure Retrieved Successfully', 'rox-appointment-booking')
                )
            )
        );
    }

    /**
     * Check whether the current user can access this endpoint.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return bool
     */
    public function permissionCheck(WP_REST_Request $request): bool
    {
        return true;
    }

    /**
     * Get initial booking panel structures.
     *
     * @return array
     */
    private function getInitialStructures(): array
    {
        $plugin_url = plugin_dir_url(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . 'rox-appointment-booking/public/';

        $location_module_enable = get_option('rox_appointment_booking_location_settings', [])['location_module_enable'] ?? false;

        // The location step is a Pro feature. Even when the location module is
        // enabled, the booking panel must only start from the location step when
        // Pro is active; otherwise it starts from the category step.
        $location_module_enable = $location_module_enable && rox_appointment_booking_is_pro_user();

        // Even with the module enabled on Pro, the location step is only usable
        // once at least one location exists — otherwise the panel would open on
        // an empty location step with nothing to pick. Start from the category
        // step until a location has been created.
        if ($location_module_enable) {
            $location_module_enable = \RoxAppointmentBookingPro\Modules\Location\Data\LocationModel::count() > 0;
        }

        $integrations_settings = get_option('rox_appointment_booking_integrations_settings', []);

        // The "Sign in with Google" button config comes from a filter that ONLY a
        // Pro version shipping the Google-login backend answers (see the Pro
        // Integrations Provider). Any Pro that predates the feature — or no Pro at
        // all — leaves the filter unanswered, so 'enabled' stays false, the button
        // never renders, and the google-login endpoint is never called. This
        // self-versioning mirrors the `rox_appointment_booking_custom_fields`
        // pattern above and avoids any hardcoded Pro version check.
        $google_login = apply_filters('rox_appointment_booking_google_login_config', [
            'enabled'    => false,
            'clientId'   => '',
            'buttonText' => 'Continue with Google',
        ]);

        return apply_filters('rox_appointment_booking_temp_location_data', [
            "title" => $location_module_enable ? "Location Selection" : "Category Selection",
            "subTitle" => "Select the location where you'd like to book your appointment.",
            "icon" => $plugin_url . "svgs/sidebar_image.svg",
            "location" => $location_module_enable ? true : false,

            // The Mailchimp opt-in checkbox on the Customer Information step only
            // shows when the integration is enabled AND fully configured (API key
            // + audience) — matches the exact gate the Pro sync hook checks before
            // pushing a contact to Mailchimp, so the checkbox never appears without
            // the sync actually being able to run.
            "mailchimpConsentEnabled" => !empty($integrations_settings['mailchimp_enabled'])
                && !empty($integrations_settings['mailchimp_api_key'])
                && !empty($integrations_settings['mailchimp_audience_id']),
            "mailchimpConsentText" => $integrations_settings['mailchimp_consent_text'] ?? 'Subscribe me to updates',

            // "Sign in with Google" button on the Customer Information step. Only
            // true when the Gmail-capable Pro answered the filter above with the
            // integration enabled + a Client ID configured (see $google_login).
            "googleLoginEnabled"    => (bool) $google_login['enabled'],
            "googleClientId"        => (string) $google_login['clientId'],
            "googleLoginButtonText" => (string) $google_login['buttonText'],

            // Pro custom fields shown on the Customer Information step. Empty
            // array unless the Pro plugin answers this filter.
            "customFields" => apply_filters('rox_appointment_booking_custom_fields', []),

            // Built-in field config (enable/required per field). Defaults unless
            // the Pro plugin overrides via the system-fields filter.
            "systemFields" => rox_appointment_booking_system_fields(),

            // API endpoints for the booking panel
            "content" => [
                "locationsApi" =>  esc_url_raw(get_rest_url(null, "rox-appointment-booking/v1/public/location")),
                "categoriesApi" =>  esc_url_raw(get_rest_url(null, "rox-appointment-booking/v1/public/category")),
                "servicesApi" => esc_url_raw(get_rest_url(null, "rox-appointment-booking/v1/public/service")),
                "extraservicesApi" => esc_url_raw(get_rest_url(null, "rox-appointment-booking/v1/public/extra-service")),
                "agentsApi" => esc_url_raw(get_rest_url(null, "rox-appointment-booking/v1/public/agent")),
                "appointmentSchedulesApi" => esc_url_raw(get_rest_url(null, "rox-appointment-booking/v1/public/appointment-schedule")),
                'customerMeApi' => esc_url_raw(rest_url('rox-appointment-booking/v1/public/customer/me')),
            ],
            "store" => [
                'bookingApi' => esc_url_raw(rest_url('rox-appointment-booking/v1/public/booking')),
                'customerLoginApi' => esc_url_raw(rest_url('rox-appointment-booking/v1/public/customer/login')),
                'customerResetPasswordApi' => esc_url_raw(rest_url('rox-appointment-booking/v1/public/customer/reset-password-request')),
                'customerSetPasswordApi' => esc_url_raw(rest_url('rox-appointment-booking/v1/public/customer/reset-password')),
                // Always built, but only ever called when googleLoginEnabled is
                // true — which only the Gmail-capable Pro can make true — so the URL
                // is never hit without the matching Pro endpoint present.
                'googleLoginApi' => esc_url_raw(rest_url('rox-appointment-booking/v1/public/customer/google-login')),
            ]
        ]);
    }
}
