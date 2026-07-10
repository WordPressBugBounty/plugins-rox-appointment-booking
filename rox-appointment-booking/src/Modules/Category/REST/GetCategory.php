<?php

namespace RoxAppointmentBooking\Modules\Category\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Category\Data\CategoryModel;
use RoxAppointmentBooking\Modules\Category\Services\CategoryService;

/**
 * Class GetCategory
 * 
 * @package RoxAppointmentBooking\Modules\Category\REST
 * @description Handles retrieving list of categories and single category via REST API.
 */
class GetCategory extends AbstractREST
{
    /**
     * Whether the endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for listing categories.
     *
     * @var string
     */
    public static string $route = '/category(?:/(?P<id>\d+))?';
    /**
     * Usable route template for docs.
     *
     * @var string
     */
    public static string $usableRoute = '/category/';

    /**
     * Get the methods allowed for this route
     * 
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'GET';
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
     * Get single category data
     * 
     * @param CategoryModel $category
     * @param bool $detailed Whether to include detailed information
     * @return array
     */
    protected function getCategoryData(CategoryModel $category, bool $detailed = false, string $mode = 'default'): array
    {
        $serviceCount = CategoryService::getServicesCountByCatId($category->getID());

        $data = [
            'id' => $category->getID(),
            'name' => $category->title,
            'description' => $category->description,
            'services_count' => $serviceCount . ' ' . _n('service', 'services', $serviceCount, 'rox-appointment-booking'),
            'sort_order' => $category->sort_order,
            'icon' => $category->thumbnail_id ? wp_get_attachment_image_url($category->thumbnail_id, 'medium') : null,
        ];

        if ($detailed) {
            $data['internal_notes'] = $category->internal_notes;
            $data['title'] = $category->title;
            $data['slug'] = $category->slug;
            $data['description'] = $category->description;
            $data['thumbnail_id'] = $category->thumbnail_id ?? null;
        }

        if( $mode === 'list' ) {
            return [
                'value' => $category->getID(),
                'label' => $category->title,
            ];
        }

        return $data;
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

        // Handle single category request
        if ($id) {
            $category = CategoryModel::find($id);
            if (!$category) {
                return rox_appointment_booking_rest_response(
                    data: null,
                    code: 404,
                    message: esc_html__('Category not found', 'rox-appointment-booking'),
                    headers: ['status' => 404]
                );
            }

            return rox_appointment_booking_rest_response(
                data: $this->getCategoryData($category, true),
                message: esc_html__('Category retrieved successfully', 'rox-appointment-booking')
            );
        }

        // Handle category list request
        $page = $request->get_param('page') ?? 1;
        $per_page = $request->get_param('per_page') ?? 10;
        $search = $request->get_param('search') ?? '';
        $mode = $request->get_param('mode') ?? 'default';

        $query = CategoryModel::query();

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // List mode feeds select dropdowns (optionsapi): return every category so
        // an already-selected (older) value always has a matching option/label.
        // The paginated default mode still drives the table view.
        if ($mode === 'list') {
            $categories = $query->orderBy('created_at', 'DESC')->get();
        } else {
            $categories = $query->offset(($page - 1) * $per_page)
                             ->limit($per_page)
                             ->orderBy('created_at', 'DESC')
                             ->get();
        }

        $data = [];

        if( $mode === 'list' ) {
            foreach ($categories as $category) {
                $data[] = $this->getCategoryData($category, false, $mode);
            }
        } else {
            foreach ($categories as $category) {
                $data[] = $this->getCategoryData($category);
            }
        }

        return rox_appointment_booking_rest_response(
            data: $data,
            message: array(
                'success' => array(
                    esc_html__('Categories Retrieved Successfully', 'rox-appointment-booking')
                )
                ),
            options: [
                'action_items' => [
                    [
                        'key' => 'edit',
                        'label' => esc_html__('Edit', 'rox-appointment-booking'),
                        'icon' => 'edit',
                        'route' => '/services/category/',
                    ],
                    [
                        'key' => 'delete',
                        'label' => esc_html__('Delete', 'rox-appointment-booking'),
                        'icon' => 'trash',
                    ],
                ],
                'api' => [
                    'delete' => get_rest_url(null, 'rox-appointment-booking/v1/category'),
                ],
            ],
        );
    }
}
