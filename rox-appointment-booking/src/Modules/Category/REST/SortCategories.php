<?php

namespace RoxAppointmentBooking\Modules\Category\REST;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Category\Data\CategoryModel;

/**
 * Class SortCategories
 *
 * @package RoxAppointmentBooking\Modules\Category\REST
 * @description Handles saving category sort order via REST API.
 */
class SortCategories extends AbstractREST
{
    /**
     * Whether the endpoint should be loadable.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for sorting categories.
     *
     * @var string
     */
    public static string $route = '/sort-api/categories';

    /**
     * Get the methods allowed for this route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return ['POST', 'PUT'];
    }

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

            $category_id = absint($item['id']);
            $sort_order = absint($item['sort_order']);

            // Validate ID
            if ($category_id <= 0) {
                // translators: %s = invalid category ID value
                $errors[] = sprintf(esc_html__('Invalid category ID: %s', 'rox-appointment-booking'), esc_html($item['id']));
                continue;
            }

            try {
                $category = CategoryModel::find($category_id);
                
                if (!$category) {
                    // translators: %d = category ID
                    $errors[] = sprintf(esc_html__('Category not found: %d', 'rox-appointment-booking'), $category_id);
                    continue;
                }

                $category->sort_order = $sort_order;
                $category->save();
                
                $updated_count++;

            } catch (\Exception $e) {
                $errors[] = sprintf(
                    // translators: %1$d = category ID, %2$s = error message
                    esc_html__('Failed to update category %1$d: %2$s', 'rox-appointment-booking'),
                    $category_id,
                    esc_html($e->getMessage())
                );
            }
        }

        // Return response based on results
        if ($updated_count === 0 && !empty($errors)) {
            return rox_appointment_booking_rest_response(
                data: ['errors' => $errors],
                code: 500,
                message: esc_html__('Failed to update category sort order', 'rox-appointment-booking')
            );
        }

        $response_data = [];

        if (!empty($errors)) {
            $response_data['errors'] = $errors;
        }

        return rox_appointment_booking_rest_response(
            data: $response_data,
            message: sprintf(
                // translators: %1$d = number of successfully updated categories, %2$d = total categories processed
                esc_html__('Successfully updated %1$d of %2$d categories', 'rox-appointment-booking'),
                $updated_count,
                count($items)
            ),
            code: 200
        );
    }
}
