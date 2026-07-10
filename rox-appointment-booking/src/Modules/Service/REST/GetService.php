<?php

namespace RoxAppointmentBooking\Modules\Service\REST;

use RoxAppointmentBooking\Modules\Category\Data\CategoryModel;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Supports\Security;
use RoxAppointmentBooking\Modules\Service\Data\ServiceModel;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceCategoryRelationModel;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceLocationRelationModel;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceAgentRelationModel;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceExtraserviceRelationModel;
use RoxAppointmentBooking\Modules\Service\Services\ServiceService;
/**
 * Class GetService
 *
 * @package RoxAppointmentBooking\Modules\Service\REST
 * @description Handles retrieving list of services and single service via REST API.
 */
class GetService extends AbstractREST
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
    public static string $route = '/service(?:/(?P<id>\d+))?';
    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/service/';

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
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return false;
        }

        if (!is_user_logged_in() || !Security::canAccessPanel()) {
            return false;
        }

        return true;
    }

    /**
     * G Et Se Rv Ic Ed At A.
     *
     * @return array
     */
    protected function getServiceData(ServiceModel $service, bool $detailed = false, string $mode = 'default'): array
    {
        // Get the first category ID (primary category)
        $categoryIds = $this->getCategoryIdsByServiceId($service->getID());

        $data = [
            'id' => $service->getID(),
            'name' => $service->title,
            'title' => $service->title,
            'description' => $service->description,
            'duration' => $service->getFormattedDuration(), // Duration is now stored in Service.php format
            'price' => $service->price,
            'category_ids' => $categoryIds,
            'sort_order' => $service->sort_order,
            'icon' => $service->thumbnail_id ? wp_get_attachment_image_url($service->thumbnail_id, 'medium') : null,
        ];

        if ($detailed) {
            $data = array_merge($data, [
                // Add missing form fields
                'category_ids' => $this->getCategoryIdsByServiceId($service->getID()), // For form editing
                'agent_ids' => $this->getAgentIdsByServiceId($service->getID()),
                'category_names' => $this->getCategoryNamesByServiceId($service->getID()), // All category names
                'location' => $this->getLocationIdsByServiceId($service->getID()),
                'agent' => $this->getAgentIdsByServiceId($service->getID()),
                'capacity' => $service->capacity,
                'max_capacity' => $service->max_capacity,
                'description' => $service->description,
                'thumbnail_id' => $service->thumbnail_id,
                'deposit' => $service->deposit ? ['1'] : [],
                'deposit_type' => $service->deposit_type,
                'deposit_amount' => $service->deposit_amount,
                'weekly_schedule' => $service->weekly_schedule,
                'hide_price_booking_panel' => $service->hide_price_booking_panel ? ['1'] : [],
                'hide_duration_booking_panel' => $service->hide_duration_booking_panel ? ['1'] : [],
                'only_visible_to_agent' => $service->only_visible_to_agent ? ['1'] : [],
                'extra_services' => $this->getExtraServiceIdsByServiceId($service->getID()),
                'internal_notes' => $service->internal_notes,
                'created_by' => $service->created_by,
                'updated_by' => $service->updated_by,
            ]);
        }

        if( $mode === 'list' ) {
            return [
                'value' => $service->getID(),
                'label' => $service->title,
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

        if ($id) {
            $service = ServiceModel::find($id);
            if (!$service) {
                return rox_appointment_booking_rest_response(
                    data : null,
                    code : 404,
                    message : esc_html__('Service not found', 'rox-appointment-booking'),
                    headers : ['status' => 404]
                );
            }

            return rox_appointment_booking_rest_response(
                data : $this->getServiceData($service, true),
                message : esc_html__('Service retrieved successfully', 'rox-appointment-booking')
            );
        }

        $page = $request->get_param('page') ?? 1;
        $per_page = $request->get_param('per_page') ?? 10;
        $search = $request->get_param('search') ?? '';
        $status = $request->get_param('status') ?? '';
        $only_visible = $request->get_param('only_visible') ?? false;
        $mode = $request->get_param('mode') ?? 'default';
        $cat_id = $request->get_param('cat_id') ?? null;
        $agent_id = $request->get_param('agent_id') ?? null;
        $location_id = $request->get_param('location_id') ?? null;

        $query = ServiceModel::query();

        // Filter by category if cat_id is provided
        if (!empty($cat_id)) {
            // Get service IDs that belong to the specified category
            $serviceIds = ServiceCategoryRelationModel::where('category_id', $cat_id)
                ->pluck('service_id')
                ->toArray();
            
            // Filter services by the IDs found in the category relation
            if (!empty($serviceIds)) {
                $query->whereIn('id', $serviceIds);
            } else {
                // If no services found for this category, return empty result
                $query->where('id', 0);
            }
        }

        // Filter by service if location is provided
        if (!empty($location_id)) {
            // Get service IDs that belong to the specified location
            $serviceIds = ServiceLocationRelationModel::where('location_id', $location_id)
                ->pluck('service_id')
                ->toArray();
            
            // Filter services by the IDs found in the location relation
            if (!empty($serviceIds)) {
                $query->whereIn('id', $serviceIds);
            } else {
                // If no services found for this location, return empty result
                $query->where('id', 0);
            }
        }

        // Filter by agent if agent_id is provided
        if (!empty($agent_id)) {
            // Get service IDs that belong to the specified agent
            $serviceIds = ServiceAgentRelationModel::where('agent_id', $agent_id)
                ->pluck('service_id')
                ->toArray();
            
            // Filter services by the IDs found in the agent relation
            if (!empty($serviceIds)) {
                $query->whereIn('id', $serviceIds);
            } else {
                // If no services found for this agent, return empty result
                $query->where('id', 0);
            }
        }

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($status)) {
            $query->where('status', $status);
        }

        if ($only_visible) {
            $query->visible();
        }

        $total = $query->count();
        // List mode feeds select dropdowns (optionsapi): return every matching
        // service so an already-selected value always has a matching option/label.
        // The paginated default mode still drives the table view.
        if ($mode === 'list') {
            $services = $query->orderBy('created_at', 'DESC')->get();
        } else {
            $services = $query->offset(($page - 1) * $per_page)
                              ->limit($per_page)
                              ->orderBy('created_at', 'DESC')
                              ->get();
        }

        $data = [];
        
        // If cat_id, agent_id is provided or mode is list, return value/label format
        if( $mode === 'list' || !empty($cat_id) || !empty($agent_id) || !empty($location_id) ) {
            foreach ($services as $service) {
                $data[] = $this->getServiceData($service, false, 'list');
            }
        } else {
            foreach ($services as $service) {
                $data[] = $this->getServiceData($service);
            }
        }

        $meta = [
            'total' => $total,
            'page' => (int)$page,
            'per_page' => (int)$per_page,
            'total_pages' => ceil($total / $per_page)
        ];

        return rox_appointment_booking_rest_response(
            data : $data,
            message : esc_html__('Services retrieved successfully', 'rox-appointment-booking'),
            options : 
            [
                "meta_data" => $meta,
                'action_items' => [
                    [
                        'key' => 'edit',
                        'label' => esc_html__('Edit', 'rox-appointment-booking'),
                        'icon' => 'edit',
                        'route' => '/services/',
                    ],
                    [
                        'key' => 'delete',
                        'label' => esc_html__('Delete', 'rox-appointment-booking'),
                        'icon' => 'trash',
                    ],
                ],
                'api' => [
                    'delete' => get_rest_url(null, 'rox-appointment-booking/v1/service'),
                ],
            ]
        );
    }

    /**
     * Get category IDs array by service ID for form population
     * 
     * @param int $serviceId Service ID
     * @return array Category IDs
     */
    protected function getCategoryIdsByServiceId($serviceId): array
    {        
        $serviceCategoryRelations = ServiceCategoryRelationModel::where('service_id', $serviceId)->get();
        $categoryIds = [];
        
        foreach ($serviceCategoryRelations as $relation) {
            $categoryIds[] = $relation->category_id;
        }
        
        return $categoryIds;
    }

    /**
     * Get location IDs array by service ID for form population
     * 
     * @param int $serviceId Service ID
     * @return array Location IDs
     */
    protected function getLocationIdsByServiceId($serviceId): array
    {
        $serviceLocationRelations = ServiceLocationRelationModel::where('service_id', $serviceId)->get();
        $locationIds = [];
        
        foreach ($serviceLocationRelations as $relation) {
            $locationIds[] = $relation->location_id;
        }
        
        return $locationIds;
    }

    /**
     * Get agent IDs array by service ID for form population
     * 
     * @param int $serviceId Service ID
     * @return array Agent IDs
     */
    protected function getAgentIdsByServiceId($serviceId): array
    {
        $serviceAgentRelations = ServiceAgentRelationModel::where('service_id', $serviceId)->get();
        $agentIds = [];
        
        foreach ($serviceAgentRelations as $relation) {
            $agentIds[] = $relation->agent_id;
        }
        
        return $agentIds;
    }

    /**
     * Get extra service IDs array by service ID for form population
     * 
     * @param int $serviceId Service ID
     * @return array Extra service IDs
     */
    protected function getExtraServiceIdsByServiceId($serviceId): array
    {
        $serviceExtraServiceRelations = ServiceExtraserviceRelationModel::where('service_id', $serviceId)->get();
        $extraServiceIds = [];
        
        foreach ($serviceExtraServiceRelations as $relation) {
            $extraServiceIds[] = $relation->extra_service_id;
        }
        
        return $extraServiceIds;
    }

    /**
     * Get category name by service ID
     * 
     * @param int $serviceId Service ID
     * @return string Category name (first category if multiple)
     */
    protected function getCategoryNameByServiceId($serviceId)
    {        
        $categoryNames = $this->getCategoryNamesByServiceId($serviceId);
        return !empty($categoryNames) ? $categoryNames[0] : '';
    }
    
    /**
     * Get all category names by service ID
     * 
     * @param int $serviceId Service ID
     * @return array Category names
     */
    protected function getCategoryNamesByServiceId($serviceId): array
    {        
        $serviceCategoryRelations = ServiceCategoryRelationModel::where('service_id', $serviceId)->get();
        $categoryNames = [];
        
        foreach ($serviceCategoryRelations as $relation) {
            $categoryName = $this->getCategoryName($relation->category_id);
            if (!empty($categoryName)) {
                $categoryNames[] = $categoryName;
            }
        }
        
        return $categoryNames;
    }
    
    /**
     * Get category name by category ID
     * 
     * @param int $categoryId Category ID
     * @return string Category name
     */
    protected function getCategoryName($categoryId)
    {        
        $category = CategoryModel::find($categoryId);

        if ($category) {
            return $category->title;
        }
        return '';
    }

    /**
     * Get service data formatted for select input
     * 
     * @param ServiceModel $service Service model instance
     * @return array Formatted service data
     * 
     */
    public function getServiceDataForSelect(ServiceModel $service): array
    {
        $data = [
            'id' => $service->getID(),
            'name' => $service->title,
            'duration' => $service->duration, // Duration is now stored in Service.php format
            'price' => $service->price,
            'category' => $this->getCategoryNameByServiceId($service->getID()),
            'weekly_schedule' => $service->weekly_schedule,
            'has_weekly_schedule' => $service->hasWeeklySchedule(),
            'has_minimum_extra_service_requirement' => $service->hasMinimumExtraServiceRequirement(),
            'has_maximum_extra_service_limit' => $service->hasMaximumExtraServiceLimit(),
            'minimum_extra_services' => $service->minimum_extra_services,
            'maximum_extra_services' => $service->maximum_extra_services,
            'extra_services' => $this->getExtraServiceIdsByServiceId($service->getID()),
            'internal_notes' => $service->internal_notes,
        ];
        return $data;
    }
}

