<?php

namespace RoxAppointmentBooking\Modules\FrontendBookingPanel\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Category\Data\CategoryModel;
use RoxAppointmentBooking\Modules\Category\Services\CategoryService;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceCategoryRelationModel;

/**
 * Class GetCategory
 *
 * @package RoxAppointmentBooking\Modules\FrontendBookingPanel\REST
 * @description Handles retrieving list of categories and single category via REST API.
 */
class GetCategory extends AbstractREST
{
    /**
     * Whether this REST endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * REST route for listing and retrieving public categories.
     *
     * @var string
     */
    public static string $route = '/public/category(?:/(?P<id>\d+))?';

    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/public/category/';

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
     * Get category data formatted for response
     * 
     * @param CategoryModel $category
     * @param bool $detailed Whether to include detailed information
     * @return array
     */
    protected function getCategoryData(CategoryModel $category, bool $detailed = false): array
    {
        $serviceCount = CategoryService::getServicesCountByCatId($category->getID());

        $data = [
            'id' => $category->getID(),
            'name' => $category->title,
            'description' => $category->description,
            'services_count' => $serviceCount,
            'iconPath' => $category->thumbnail_id ? wp_get_attachment_url($category->thumbnail_id) : '',
        ];

        if ($detailed) {
            $data = array_merge($data, [
                'parent_id' => $category->parent_id,
                'service_ids' => $this->getServiceIdsByCategoryId($category->getID()),
                'thumbnail_id' => $category->thumbnail_id,
                'internal_notes' => $category->internal_notes,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
            ]);
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

        $page = $request->get_param('page') ?? 1;
        $per_page = $request->get_param('per_page') ?? 20;
        $search = $request->get_param('search') ?? '';
        $service_id = $request->get_param('service_id') ?? null;

        $query = CategoryModel::query();

        // Filter by service if service_id is provided
        if (!empty($service_id)) {
            // Get category IDs that belong to the specified service
            $categoryIds = ServiceCategoryRelationModel::where('service_id', $service_id)
                ->pluck('category_id')
                ->toArray();
            
            // Filter categories by the IDs found in the service relation
            if (!empty($categoryIds)) {
                $query->whereIn('id', $categoryIds);
            } else {
                // If no categories found for this service, return empty result
                $query->where('id', 0);
            }
        }

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        $total = $query->count();
        $categories = $query->offset(($page - 1) * $per_page)
                          ->limit($per_page)
                          ->orderBy('created_at', 'DESC')
                          ->get();

        $data = [];
        
        foreach ($categories as $category) {
            $data[] = $this->getCategoryData($category);
        }

        $meta = [
            'total' => $total,
            'page' => (int)$page,
            'per_page' => (int)$per_page,
            'total_pages' => ceil($total / $per_page)
        ];

        return rox_appointment_booking_rest_response(
            data: $data,
            message: esc_html__('Categories retrieved successfully', 'rox-appointment-booking'),
            options: [
                "meta_data" => $meta,
            ]
        );
    }

    /**
     * Get service IDs array by category ID
     * 
     * @param int $categoryId Category ID
     * @return array Service IDs
     */
    protected function getServiceIdsByCategoryId($categoryId): array
    {
        $serviceCategoryRelations = ServiceCategoryRelationModel::where('category_id', $categoryId)->get();
        $serviceIds = [];
        
        foreach ($serviceCategoryRelations as $relation) {
            $serviceIds[] = $relation->service_id;
        }
        
        return $serviceIds;
    }
}
