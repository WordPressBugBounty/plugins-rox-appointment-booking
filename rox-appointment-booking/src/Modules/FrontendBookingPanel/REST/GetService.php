<?php

namespace RoxAppointmentBooking\Modules\FrontendBookingPanel\REST;

use RoxAppointmentBooking\Modules\Category\Data\CategoryModel;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Service\Data\ServiceModel;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceCategoryRelationModel;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceLocationRelationModel;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceAgentRelationModel;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceExtraserviceRelationModel;
/**
 * Class GetService
 *
 * @package RoxAppointmentBooking\Modules\FrontendBookingPanel\REST
 * @description Handles retrieving list of services and single service via REST API.
 */
class GetService extends AbstractREST
{
    /**
     * Whether this REST endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * REST route for listing and retrieving public services.
     *
     * @var string
     */
    public static string $route = '/public/service(?:/(?P<id>\d+))?';

    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/public/service/';

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
     * Format service data for REST responses.
     *
     * @param ServiceModel $service Service model instance.
     * @param bool $detailed Whether to include detailed information.
     * @return array
     */
    protected function getServiceData(ServiceModel $service, bool $detailed = false ): array
    {
        $currency = rox_appointment_booking_payment_settings('payment_currency') ?? 'USD';
        $categoryIds = $this->getCategoryIdsByServiceId($service->getID());
        
        $data = [
            'id' => $service->getID(),
            'name' => $service->title,
            'price' => $service->price,
            'hide_price_booking_panel' => (bool) $service->hide_price_booking_panel,
            'duration' => (int) $service->duration,
            // Agent-optional booking is a Pro feature — flag is false unless Pro is active.
            'allow_without_agent' => defined('ROX_APPOINTMENT_BOOKING_PRO_VERSION') && (bool) $service->allow_without_agent,
            'iconPath' => $service->thumbnail_id ? wp_get_attachment_url($service->thumbnail_id) : '',
            'currency_symbol' => rox_appointment_booking__get_currency_symbol($currency),
            'extraServices' => $this->getExtraServiceIdsByServiceId($service->getID()),
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
                'thumbnail_url' => $service->thumbnail_id ? wp_get_attachment_url($service->thumbnail_id) : '',
                'deposit' => $service->deposit ? ['1'] : [],
                'deposit_type' => $service->deposit_type,
                'deposit_amount' => $service->deposit_amount,
                'weekly_schedule' => $service->weekly_schedule,
                'hide_price_booking_panel' => $service->hide_price_booking_panel ? ['1'] : [],
                'hide_duration_booking_panel' => $service->hide_duration_booking_panel ? ['1'] : [],
                'allow_without_agent' => $service->allow_without_agent ? ['1'] : [],
                'extra_services' => $this->getExtraServiceIdsByServiceId($service->getID()),
                'internal_notes' => $service->internal_notes,
                'created_by' => $service->created_by,
                'updated_by' => $service->updated_by,
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
        $per_page = $request->get_param('per_page') ?? 20;
        $search = $request->get_param('search') ?? '';
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

        $total = $query->count();
        $services = $query->offset(($page - 1) * $per_page)
                          ->limit($per_page)
                          ->orderBy('created_at', 'DESC')
                          ->get();

        $data = [];
        
        foreach ($services as $service) {
            $data[] = $this->getServiceData($service);
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
}

