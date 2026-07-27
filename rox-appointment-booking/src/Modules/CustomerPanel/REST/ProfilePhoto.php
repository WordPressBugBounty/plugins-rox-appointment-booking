<?php

namespace RoxAppointmentBooking\Modules\CustomerPanel\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\CustomerPanel\Services\CustomerPanelService;
use RoxAppointmentBooking\Modules\CustomerPanel\Services\CustomerProfileService;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * POST /customer-panel/profile/photo — upload the logged-in customer's own
 * profile photo (the Profile view's "Change Photo" button). The image is stored
 * in the media library and set as the customer row's thumbnail_id; the response
 * is the same profile payload the Profile endpoint returns, so the view can
 * refresh in place.
 */
class ProfilePhoto extends AbstractREST
{
    public static $loadable = true;

    public static string $route = '/customer-panel/profile/photo';

    public static string $usableRoute = '/customer-panel/profile/photo';

    /** Multipart field name the panel uploads under. */
    private const FIELD = 'photo';

    protected function getMethods(): string|array
    {
        return ['POST'];
    }

    /**
     * Logged-in customer users only, with a valid REST nonce.
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public function permissionCheck(WP_REST_Request $request): bool
    {
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return false;
        }

        return CustomerPanelService::isCurrentUserCustomer();
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $files = $request->get_file_params();
        $file = $files[self::FIELD] ?? [];

        if (! is_array($file)) {
            $file = [];
        }

        $result = (new CustomerProfileService())->saveProfilePhoto(self::FIELD, $file);

        if (is_wp_error($result)) {
            return $result;
        }

        return rox_appointment_booking_rest_response(
            data: $result,
            message: esc_html__('Profile photo updated successfully', 'rox-appointment-booking')
        );
    }
}
