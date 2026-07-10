<?php

namespace RoxAppointmentBooking\Modules\Service\REST;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Service\Data\ServiceModel;

/**
 * Class SortServices
 *
 * @package RoxAppointmentBooking\Modules\Service\REST
 * @description Handles saving service sort order via REST API.
 */
class SortServices extends AbstractREST
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
    public static string $route = '/sort-api/services';

    /**
     * Get the HTTP methods allowed for this route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return ['POST', 'PUT'];
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
        $params = $request->get_params();
        
        // Validate that items array exists
        if (!isset($params['items']) || !is_array($params['items'])) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('Items array is required', 'rox-appointment-booking')
            );
        }

        $items = $params['items'];

        // Validate items array is not empty
        if (empty($items)) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('Items array cannot be empty', 'rox-appointment-booking')
            );
        }

        $updated_count = 0;
        $errors = [];

        foreach ($items as $item) {
            // Validate each item has required fields
            if (!isset($item['id']) || !isset($item['sort_order'])) {
                $errors[] = esc_html__('Each item must have id and sort_order', 'rox-appointment-booking');
                continue;
            }

            $service_id = absint($item['id']);
            $sort_order = absint($item['sort_order']);

            // Validate ID
            if ($service_id <= 0) {
                // translators: %s = invalid service ID value
                $errors[] = sprintf(esc_html__('Invalid service ID: %s', 'rox-appointment-booking'), esc_html($item['id']));
                continue;
            }

            try {
                $service = ServiceModel::find($service_id);
                
                if (!$service) {
                    // translators: %d = service ID
                    $errors[] = sprintf(esc_html__('Service not found: %d', 'rox-appointment-booking'), $service_id);
                    continue;
                }

                $service->sort_order = $sort_order;
                $service->save();
                
                $updated_count++;

            } catch (\Exception $e) {
                $errors[] = sprintf(
                    // translators: %1$d = service ID, %2$s = error message
                    esc_html__('Failed to update service %1$d: %2$s', 'rox-appointment-booking'),
                    $service_id,
                    esc_html($e->getMessage())
                );
            }
        }

        // Return response based on results
        if ($updated_count === 0 && !empty($errors)) {
            return rox_appointment_booking_rest_response(
                data: ['errors' => $errors],
                code: 500,
                message: esc_html__('Failed to update service sort order', 'rox-appointment-booking')
            );
        }

        $response_data = [];

        if (!empty($errors)) {
            $response_data['errors'] = $errors;
        }

        return rox_appointment_booking_rest_response(
            data: $response_data,
            message: sprintf(
                // translators: %1$d = count of successfully updated services, %2$d = total count of items
                esc_html__('Successfully updated %1$d of %2$d services', 'rox-appointment-booking'),
                $updated_count,
                count($items)
            ),
            code: 200
        );
    }
}
