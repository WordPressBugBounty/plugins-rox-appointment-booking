<?php

namespace RoxAppointmentBooking\Modules\Category\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Category\Data\CategoryModel;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceCategoryRelationModel;

/**
 * Class DeleteCategory
 *
 * @package RoxAppointmentBooking\Modules\Category\REST
 * @description Handles deleting category via REST API.
 */
class DeleteCategory extends AbstractREST
{
    /**
     * Whether the endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for deleting categories.
     *
     * @var string
     */
    public static string $route = '/category(?:/(?P<id>\d+))?';
    /**
     * Usable route template for docs.
     *
     * @var string
     */
    public static string $usableRoute = '/category/{id}';

    /**
     * Get the methods allowed for this route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'DELETE';
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

    /**
     * Handle the REST API request.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $ids = $request->get_param('ids');
        $id = $request->get_param('id');
        $deleted = [];
        $errors = [];
        
        if ($ids && is_array($ids)) {
            foreach ($ids as $singleId) {
                $category = CategoryModel::find($singleId);
                if ($category) {
                    $validation_error = $this->validateCategoryCanBeDeleted($singleId);
                    if ($validation_error) {
                        $errors[] = $validation_error;
                        continue;
                    }
                    
                    try {
                        $category->delete();
                        $deleted[] = $singleId;
                    } catch (\Exception $e) {
                        $errors[] = $e->getMessage();
                    }
                } else {
                    $errors[] = esc_html__('Category not found', 'rox-appointment-booking');
                }
            }
            $status = empty($errors) ? 200 : 500;
            return rox_appointment_booking_rest_response(
                data : ['deleted' => $deleted, 'errors' => $errors],
                code : $status,
                message : empty($errors) ? esc_html__('Categories deleted successfully', 'rox-appointment-booking') : esc_html__('Some categories could not be deleted', 'rox-appointment-booking'),
                headers : ['status' => $status]
            );
        }
        
        // Single delete fallback
        $category = CategoryModel::find($id);
        if (!$category) {
            return rox_appointment_booking_rest_response(
                data : null,
                code : 404,
                message : esc_html__('Category not found', 'rox-appointment-booking'),
                headers : ['status' => 404]
            );
        }
        
        $validation_error = $this->validateCategoryCanBeDeleted($id);
        if ($validation_error) {
            return rox_appointment_booking_rest_response(
                data : null,
                code : 400,
                message : $validation_error,
                headers : ['status' => 400]
            );
        }
        
        try {
            $category->delete();
            return rox_appointment_booking_rest_response(
                data : null,
                message : esc_html__('Category deleted successfully', 'rox-appointment-booking'),
                code: 200
            );
        } catch (\Exception $e) {
            return rox_appointment_booking_rest_response(
                data : null,
                code : 500,
                message : $e->getMessage(),
                headers : ['status' => 500]
            );
        }
    }

    /**
     * Validate if a category can be deleted
     *
     * @param int $id
     * @return string|null Returns error message if cannot be deleted, null if can be deleted
     */
    private function validateCategoryCanBeDeleted(int $id): ?string
    {
        // Check if there are appointments for this category
        $appointments = AppointmentModel::query()
            ->where('category_id', $id)
            ->count();

        if ($appointments > 0) {
            return esc_html__('This category has some appointments scheduled. Please remove them first, it will not be deleted', 'rox-appointment-booking');
        }

        // Check if any services are assigned to this category
        $serviceRelations = ServiceCategoryRelationModel::query()
            ->where('category_id', $id)
            ->count();

        if ($serviceRelations > 0) {
            return esc_html__('This category has some services assigned. Please remove them first, it will not be deleted', 'rox-appointment-booking');
        }

        return null; // Category can be deleted
    }
}
