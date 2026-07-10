<?php

namespace RoxAppointmentBooking\Modules\Category\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Category\Data\CategoryModel;

/**
 * Class SaveCategory
 *
 * @package RoxAppointmentBooking\Modules\Category\REST
 * @description Handles creating or updating a category via REST API.
 */
class SaveCategory extends AbstractREST
{
    /**
     * Whether the endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for saving categories.
     *
     * @var string
     */
    public static string $route = '/category(?:/(?P<id>\d+))?';
    /**
     * Usable route template for docs.
     *
     * @var string
     */
    public static string $usableRoute = '/category';

    /**
     * Get the methods allowed for this route
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return ['POST', 'PUT'];
    }

    /**
     * Check if the user has permission to access this endpoint
     *
     * @param WP_REST_Request $request
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
     * Handle the REST API request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = $request->get_param('id');
        $params = $request->get_params();

        // Validate required field
        if (empty($params['title'])) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('Title is required', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        // Generate slug from title if not provided
        $slug = !empty($params['slug']) ? sanitize_text_field($params['slug']) : $this->generateSlug($params['title']);

        // Validate slug format
        if (!$this->isValidSlug($slug)) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('Slug can only contain lowercase letters, numbers, and underscores', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        // Check if slug already exists (excluding current category if updating)
        if ($this->slugExists($slug, $id)) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('A category with this slug already exists', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        // Validate thumbnail_id if provided
        if (!empty($params['thumbnail_id'])) {
            if (!is_numeric($params['thumbnail_id']) || $params['thumbnail_id'] <= 0) {
                return rox_appointment_booking_rest_response(
                    data: null,
                    code: 400,
                    message: ['Thumbnail ID must be a valid positive number'],
                    headers: ['status' => 400]
                );
            }

            // Check if attachment exists
            if (!wp_get_attachment_url($params['thumbnail_id'])) {
                return rox_appointment_booking_rest_response(
                    data: null,
                    code: 400,
                    message: ['Thumbnail attachment not found'],
                    headers: ['status' => 400]
                );
            }
        }

        try {
            if ($id) {
                // Update existing category
                $category = CategoryModel::find($id);
                if (!$category) {
                    return rox_appointment_booking_rest_response(
                        data: null,
                        code: 404,
                        message: esc_html__('Category not found', 'rox-appointment-booking'),
                        headers: ['status' => 404]
                    );
                }
            } else {
                // Create new category
                $category = new CategoryModel();
            }

            $category->title = sanitize_text_field($params['title']);
            $category->slug = $slug;
            $category->description = !empty($params['description']) ? sanitize_text_field($params['description']) : null;
            $category->thumbnail_id = !empty($params['thumbnail_id']) ? (int)$params['thumbnail_id'] : null;
            $category->sort_order = !empty($params['sort_order']) ? (int)$params['sort_order'] : 0;
            $category->internal_notes = !empty($params['internal_notes']) ? sanitize_text_field($params['internal_notes']) : null;
            $category->save();

            $message = $id ? esc_html__('Category updated successfully', 'rox-appointment-booking') : esc_html__('Category created successfully', 'rox-appointment-booking');
            $code = $id ? 200 : 201;
            $headers = ['status' => $code];

            return rox_appointment_booking_rest_response(
                data: [
                    'id' => $category->getID(),
                    'title' => $category->title,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'thumbnail_id' => $category->thumbnail_id,
                    'internal_notes' => $category->internal_notes,
                    'sort_order' => $category->sort_order,
                    'created_at' => $category->created_at,
                    'updated_at' => $category->updated_at,
                ],
                message: $message,
                code: $code,
                headers: $headers
            );
        } catch (\Exception $e) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 500,
                message: $e->getMessage(),
                headers: ['status' => 500]
            );
        }
    }

    /**
     * Generate a slug from the given title
     *
     * @param string $title
     * @return string
     */
    private function generateSlug(string $title): string
    {
        // Convert to lowercase
        $slug = strtolower($title);
        // Replace spaces with underscores
        $slug = preg_replace('/[\s]+/', '_', $slug);
        // Remove all characters except lowercase letters, numbers, and underscores
        $slug = preg_replace('/[^a-z0-9_]/', '', $slug);
        // Remove multiple consecutive underscores
        $slug = preg_replace('/_+/', '_', $slug);
        // Trim underscores from start and end
        $slug = trim($slug, '_');
        return $slug;
    }

    /**
     * Validate slug format
     *
     * @param string $slug
     * @return bool
     */
    private function isValidSlug(string $slug): bool
    {
        return (bool) preg_match('/^[a-z0-9_]+$/', $slug);
    }

    /**
     * Check if slug already exists in the database
     *
     * @param string $slug
     * @param int|null $excludeId ID to exclude from check (for updates)
     * @return bool
     */
    private function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = CategoryModel::where('slug', $slug);

        if ($excludeId) {
            $query = $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
