<?php

namespace RoxAppointmentBooking\Modules\Service\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Service\Data\ServiceModel;
use RoxAppointmentBooking\Modules\Category\Data\CategoryModel;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceCategoryRelationModel;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceLocationRelationModel;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceAgentRelationModel;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceExtraserviceRelationModel;
use RoxAppointmentBooking\Modules\Service\Services\ServiceService;

/**
 * Class SaveService
 *
 * @package RoxAppointmentBooking\Modules\Service\REST
 * @description Handles saving service data via REST API.
 */
class SaveService extends AbstractREST
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
     * Required service fields for create validation.
     *
     * @return array
     */
    private static function getRequiredFields(): array
    {
        return ['title', 'price', 'duration'];
    }

    /**
     * Handle the REST API request.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = $request->get_param('id');
        $params = $request->get_params();

        // Validate required fields for new services only
        if (!$id) {
            $required_fields = self::getRequiredFields();
            foreach ($required_fields as $field) {
                if (empty($params[$field]) && $params[$field] !== '0' && $params[$field] !== 0) {
                    return rox_appointment_booking_rest_response(
                        data : null,
                        code : 400,
                        // translators: %s = field name
                        message : sprintf(esc_html__('%s is required', 'rox-appointment-booking'), esc_html(ucfirst(str_replace('_', ' ', $field)))),
                        headers : ['status' => 400]
                    );
                }
            }
        } else {
            // For updates, only validate if the field is provided and empty
            if (isset($params['title']) && (empty($params['title']) && $params['title'] !== '0' && $params['title'] !== 0)) {
                return rox_appointment_booking_rest_response(
                    data : null,
                    code : 400,
                    message : esc_html__('Title is required', 'rox-appointment-booking'),
                    headers : ['status' => 400]
                );
            }
        }

        // Validate price if provided
        if (isset($params['price']) && (!is_numeric($params['price']) || $params['price'] < 0)) {
            return rox_appointment_booking_rest_response(
                data : null,
                code : 400,
                message : esc_html__('Price must be a valid positive number', 'rox-appointment-booking'),
                headers : ['status' => 400]
            );
        }

        // Validate duration - handle multiple formats including formatted strings
        if (isset($params['duration'])) {
            $duration_value = $params['duration'];
            
            // Handle object format like {value: 30}
            if (is_array($duration_value) && isset($duration_value['value'])) {
                $duration_value = $duration_value['value'];
            }
            
            // Handle formatted duration strings (from getFormattedDuration)
            if (is_string($duration_value)) {
                $duration_value = $this->parseDurationString($duration_value);
            }
            
            // Validate the parsed duration value
            if (!is_numeric($duration_value) || $duration_value <= 0) {
                return rox_appointment_booking_rest_response(
                    data : null,
                    code : 400,
                    message : esc_html__('Duration must be a valid positive number', 'rox-appointment-booking'),
                    headers : ['status' => 400]
                );
            }
            
            // Update params with the numeric value
            $params['duration'] = $duration_value;
        }

        // Validate max_capacity if provided
        if (!empty($params['max_capacity']) && (!is_numeric($params['max_capacity']) || $params['max_capacity'] <= 0)) {
            return rox_appointment_booking_rest_response(
                data : null,
                code : 400,
                message : esc_html__('Max capacity must be a valid positive number', 'rox-appointment-booking'),
                headers : ['status' => 400]
            );
        }

        // Validate deposit amount if deposit is enabled
        if (!empty($params['deposit']) && !empty($params['deposit_amount'])) {
            if (!is_numeric($params['deposit_amount']) || $params['deposit_amount'] < 0) {
                return rox_appointment_booking_rest_response(
                    data : null,
                    code : 400,
                    message : esc_html__('Deposit amount must be a valid positive number', 'rox-appointment-booking'),
                    headers : ['status' => 400]
                );
            }
        }

        // Validate status
        if (!empty($params['status']) && !in_array($params['status'], ['active', 'inactive'])) {
            return rox_appointment_booking_rest_response(
                data : null,
                code : 400,
                message : esc_html__('Status must be either active or inactive', 'rox-appointment-booking'),
                headers : ['status' => 400]
            );
        }

        // Validate deposit type
        if (!empty($params['deposit']) && !empty($params['deposit_type']) && !in_array($params['deposit_type'], ['fixed', 'percent'])) {
            return rox_appointment_booking_rest_response(
                data : null,
                code : 400,
                message : esc_html__('Deposit type must be either fixed or percentage', 'rox-appointment-booking'),
                headers : ['status' => 400]
            );
        }

        // Validate minimum and maximum extra services
        if (!empty($params['minimum_extra_services']) && (!is_numeric($params['minimum_extra_services']) || $params['minimum_extra_services'] < 0)) {
            return rox_appointment_booking_rest_response(
                data : null,
                code : 400,
                message : esc_html__('Minimum extra services must be a valid positive number', 'rox-appointment-booking'),
                headers : ['status' => 400]
            );
        }

        if (!empty($params['maximum_extra_services']) && (!is_numeric($params['maximum_extra_services']) || $params['maximum_extra_services'] < 0)) {
            return rox_appointment_booking_rest_response(
                data : null,
                code : 400,
                message : esc_html__('Maximum extra services must be a valid positive number', 'rox-appointment-booking'),
                headers : ['status' => 400]
            );
        }

        // Check for duplicate title
        $existing = ServiceModel::query()
            ->where('title', $params['title'])
            ->when($id, function($q) use ($id) {
                $q->where('id', '!=', $id);
            })
            ->first();

        if ($existing) {
            return rox_appointment_booking_rest_response(
                data : null,
                code : 400,
                message : esc_html__('A service with this title already exists', 'rox-appointment-booking'),
                headers : ['status' => 400]
            );
        }

        try {
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
            } else {
                $service = new ServiceModel();
            }

            // Set default values if not provided
            if (empty($params['status'])) {
                $params['status'] = 'active';
            }

            // Convert boolean fields
            $boolean_fields = [
                'deposit', 'hide_price_booking_panel', 'hide_duration_booking_panel',
                'only_visible_to_agent', 'allow_without_agent', 'set_service_specific_payment_methods',
                'active_minimum_extra_service', 'active_maximum_extra_service'
            ];

            // Fields living on the Pro-locked Settings tab: a non-Pro save never
            // submits them, so leave the stored value untouched instead of resetting
            // it to false (otherwise a Pro user's setting would be wiped on any edit).
            $preserve_when_absent = ['allow_without_agent', 'hide_price_booking_panel'];

            foreach ($boolean_fields as $field) {
                if (isset($params[$field])) {
                    // Handle checkbox arrays (when multiple checkboxes are selected)
                    if (is_array($params[$field])) {
                        // If checkbox is checked, it will contain the value, otherwise empty array
                        $params[$field] = !empty($params[$field]) && in_array('1', $params[$field]);
                    } else {
                        // Handle single checkbox values
                        $params[$field] = filter_var($params[$field], FILTER_VALIDATE_BOOLEAN);
                    }
                } elseif (in_array($field, $preserve_when_absent, true)) {
                    // Not submitted (Pro-locked tab) → keep the existing DB value.
                    unset($params[$field]);
                } else {
                    // Set default to false for boolean fields that are not present
                    $params[$field] = false;
                }
            }

            // Agent-less concurrent-booking capacity: clamp to a positive integer
            // (min 1) whenever the field is present, so a blank/0 input never stores
            // an invalid capacity.
            if (isset($params['without_agent_capacity'])) {
                $params['without_agent_capacity'] = max(1, intval($params['without_agent_capacity']));
            }

            // Handle weekly_schedule JSON
            if (isset($params['weekly_schedule']) && is_string($params['weekly_schedule'])) {
                $decoded = json_decode($params['weekly_schedule'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $params['weekly_schedule'] = $decoded;
                }
            }

            // Set created_by for new records or updated_by for updates
            $current_user_id = get_current_user_id();
            if (!$id && $current_user_id) {
                $params['created_by'] = $current_user_id;
            } elseif ($id && $current_user_id) {
                $params['updated_by'] = $current_user_id;
            }

            //Validate thumbnail_id if provided
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

            // Map appointment form single IDs to relationship arrays
            if (empty($params['category_ids']) && !empty($params['category_id'])) {
                $params['category_ids'] = $params['category_id'];
            }

            if (empty($params['location']) && !empty($params['location_id'])) {
                $params['location'] = $params['location_id'];
            }

            if (empty($params['agent_ids']) && !empty($params['agent_id'])) {
                $params['agent_ids'] = $params['agent_id'];
            }

            // Remove relationship arrays from params before saving to service table
            $relationship_fields = ['category_ids', 'location', 'agent_ids', 'extra_services'];
            $filtered_params = $params;
            foreach ($relationship_fields as $field) {
                unset($filtered_params[$field]);
            }

            $fillable_data = array_intersect_key($filtered_params, array_flip($service->getFillable()));
            $service->fill($fillable_data);
            $service->save();

            // Handle category relationships
            $this->handleCategoryRelationships($service, $params);

            // Handle location relationships (pro feature)
            if (defined('ROX_APPOINTMENT_BOOKING_PRO_VERSION')) {
                $this->handleLocationRelationships($service, $params);
            }

            // Handle agent relationships
            $this->handleAgentRelationships($service, $params);

            // Handle extra service relationships
            $this->handleExtraServiceRelationships($service, $params);

            return rox_appointment_booking_rest_response(
                data : [
                    'id' => $service->getID(),
                    'title' => $service->title,
                    'description' => $service->description,
                    'duration' => $service->duration,
                    'price' => $service->price,
                    'formatted_price' => $service->getFormattedPrice(),
                    'capacity' => $service->capacity,
                    'max_capacity' => $service->max_capacity,
                    'deposit' => $service->deposit,
                    'deposit_type' => $service->deposit_type,
                    'deposit_amount' => $service->deposit_amount,
                    'formatted_deposit_amount' => $service->getFormattedDepositAmount(),
                    'color' => $service->getColor(),
                    'status' => $service->status,
                    'is_active' => $service->isActive(),
                    'requires_deposit' => $service->requiresDeposit(),
                    'thumbnail_id' => $service->thumbnail_id,
                    'internal_notes' => $service->internal_notes,
                    'categories' => $this->getServiceCategories($service),
                    'locations' => defined('ROX_APPOINTMENT_BOOKING_PRO_VERSION') ? $this->getServiceLocations($service) : [],
                    'agents' => $this->getServiceAgents($service),
                    'extra_services' => $this->getServiceExtraServices($service),
                    'created_by' => $service->created_by,
                    'updated_by' => $service->updated_by,
                    'created_at' => $service->created_at,
                    'updated_at' => $service->updated_at,
                ],
                message : $id 
                    ? esc_html__('Service updated successfully', 'rox-appointment-booking')
                    : esc_html__('Service created successfully', 'rox-appointment-booking'),
                code : $id ? 200 : 201,
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
     * Handle category relationships for the service
     *
     * @param ServiceModel $service
     * @param array $params
     * @return void
     * @throws \Exception
     */
    private function handleCategoryRelationships(ServiceModel $service, array $params): void
    {
        $service_id = $service->getID();
        
        // Get the category data from params
        $category_data = $params['category_ids'] ?? [];

        $category_ids = ServiceService::normalizeIds($category_data);

        // If no categories selected, assign to Uncategorized
        if (empty($category_ids)) {
            $uncategorized = $this->getOrCreateUncategorizedCategory();
            if ($uncategorized) {
                $category_ids = [$uncategorized->getID()];
            }
        }

        // Remove existing relationships for this service
        ServiceCategoryRelationModel::query()
            ->where('service_id', $service_id)
            ->delete();

        // Create new relationships
        if (!empty($category_ids)) {
            foreach ($category_ids as $category_id) {
                try {
                    ServiceCategoryRelationModel::createRelation((int)$category_id, $service_id);
                } catch (\Exception $e) {
                }
            }
        }
    }

    /**
     * Handle location relationships for the service
     *
     * @param ServiceModel $service
     * @param array $params
     * @return void
     * @throws \Exception
     */
    private function handleLocationRelationships(ServiceModel $service, array $params): void
    {
        $service_id = $service->getID();
        
        // Get the location data from params
        $location_data = $params['location'] ?? [];

        $location_ids = ServiceService::normalizeIds($location_data);

        // Remove existing relationships for this service
        ServiceLocationRelationModel::query()
            ->where('service_id', $service_id)
            ->delete();

        // Create new relationships
        if (!empty($location_ids)) {
            foreach ($location_ids as $location_id) {
                try {
                    ServiceLocationRelationModel::createRelation((int)$location_id, $service_id);
                } catch (\Exception $e) {
                }
            }
        }
    }

    /**
     * Get categories for a service
     *
     * @param ServiceModel $service
     * @return array
     */
    private function getServiceCategories(ServiceModel $service): array
    {
        $categories = ServiceCategoryRelationModel::getCategoriesForService($service->getID());
        return $categories->map(function($category) {
            return [
                'id' => $category->id,
                'title' => $category->title
            ];
        })->toArray();
    }

    /**
     * Get locations for a service
     *
     * @param ServiceModel $service
     * @return array
     */
    private function getServiceLocations(ServiceModel $service): array
    {
        $locations = ServiceLocationRelationModel::getLocationsForService($service->getID());
        return $locations->map(function($location) {
            return [
                'id' => $location->id,
                'title' => $location->title
            ];
        })->toArray();
    }

    /**
     * Handle agent relationships for the service
     *
     * @param ServiceModel $service
     * @param array $params
     * @return void
     * @throws \Exception
     */
    private function handleAgentRelationships(ServiceModel $service, array $params): void
    {
        $service_id = $service->getID();
        
        // Get the agent data from params
        $agent_data = $params['agent_ids'] ?? [];

        $agent_ids = ServiceService::normalizeIds($agent_data);

        // Remove existing relationships for this service
        ServiceAgentRelationModel::query()
            ->where('service_id', $service_id)
            ->delete();

        // Create new relationships
        if (!empty($agent_ids)) {
            foreach ($agent_ids as $agent_id) {
                try {
                    ServiceAgentRelationModel::createRelation((int)$agent_id, $service_id);
                } catch (\Exception $e) {
                }
            }
        }
    }

    /**
     * Get agents for a service
     *
     * @param ServiceModel $service
     * @return array
     */
    private function getServiceAgents(ServiceModel $service): array
    {
        $agents = ServiceAgentRelationModel::getAgentsByService($service->getID());
        return $agents->map(function($agent) {
            return [
                'id' => $agent->id,
                'full_name' => $agent->getFullName(),
            ];
        })->toArray();
    }

    /**
     * Handle extra service relationships for the service
     *
     * @param ServiceModel $service
     * @param array $params
     * @return void
     * @throws \Exception
     */
    private function handleExtraServiceRelationships(ServiceModel $service, array $params): void
    {
        $service_id = $service->getID();
        
        // Get the extra services data from params
        $extra_services_data = $params['extra_services'] ?? [];

        $extra_service_ids = ServiceService::normalizeIds($extra_services_data);

        // Remove existing relationships for this service
        ServiceExtraserviceRelationModel::query()
            ->where('service_id', $service_id)
            ->delete();

        // Create new relationships
        if (!empty($extra_service_ids)) {
            foreach ($extra_service_ids as $extra_service_id) {
                try {
                    ServiceExtraserviceRelationModel::createRelation((int)$extra_service_id, $service_id);
                } catch (\Exception $e) {
                }
            }
        }
    }

    /**
     * Get extra services for a service
     *
     * @param ServiceModel $service
     * @return array
     */
    private function getServiceExtraServices(ServiceModel $service): array
    {
        $extraServices = ServiceExtraserviceRelationModel::getExtraServicesForService($service->getID());
        return $extraServices->map(function($extraService) {
            return [
                'id' => $extraService->id,
                'title' => $extraService->title
            ];
        })->toArray();
    }

    /**
     * Get or create the "Uncategorized" category
     *
     * @return CategoryModel|null
     */
    private function getOrCreateUncategorizedCategory(): ?CategoryModel
    {
        // Try to find existing Uncategorized category
        $uncategorized = CategoryModel::query()
            ->where('slug', 'uncategorized')
            ->first();

        // If it doesn't exist, create it
        if (!$uncategorized) {
            $uncategorized = new CategoryModel();
            $uncategorized->fill([
                'title' => 'Uncategorized',
                'slug' => 'uncategorized',
                'description' => esc_html__('Default category for services without a category', 'rox-appointment-booking'),
                'sort_order' => 0,
            ]);
            
            try {
                $uncategorized->save();
            } catch (\Exception $e) {
            }
        }

        return $uncategorized;
    }

    /**
     * Parse duration string and convert it to minutes
     * Handles all formats from getFormattedDuration(): "10m", "1h", "1h 30m", "2d", "2d 4h", "1w", "1w 2d"
     *
     * @param string $duration_string
     * @return int|float Duration in minutes
     */
    private function parseDurationString(string $duration_string): int|float
    {
        // If it's already a number, return it
        if (is_numeric($duration_string)) {
            return (int)$duration_string;
        }

        // Remove any extra spaces and convert to lowercase
        $duration_string = strtolower(trim($duration_string));
        
        // Initialize total minutes
        $total_minutes = 0;
        
        // Define conversion factors to minutes
        $conversions = [
            'w' => 10080,  // 1 week = 10080 minutes
            'd' => 1440,   // 1 day = 1440 minutes  
            'h' => 60,     // 1 hour = 60 minutes
            'm' => 1,      // 1 minute = 1 minute
        ];
        
        // Pattern to match all possible duration formats
        // Matches: number + optional decimal + unit (w, d, h, m, weeks, days, hours, minutes, etc.)
        $pattern = '/(\d+(?:\.\d+)?)\s*([wdhm]|weeks?|days?|hours?|mins?|minutes?)/i';
        
        if (preg_match_all($pattern, $duration_string, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $value = floatval($match[1]);
                $unit = strtolower($match[2]);
                
                // Normalize unit to single character
                if (in_array($unit, ['w', 'week', 'weeks'])) {
                    $unit = 'w';
                } elseif (in_array($unit, ['d', 'day', 'days'])) {
                    $unit = 'd';
                } elseif (in_array($unit, ['h', 'hour', 'hours'])) {
                    $unit = 'h';
                } elseif (in_array($unit, ['m', 'min', 'mins', 'minute', 'minutes'])) {
                    $unit = 'm';
                }
                
                // Convert to minutes and add to total
                if (isset($conversions[$unit])) {
                    $total_minutes += $value * $conversions[$unit];
                }
            }
        } else {
            // If no pattern matched, try to extract just the number
            preg_match('/(\d+(?:\.\d+)?)/', $duration_string, $matches);
            if (!empty($matches[1])) {
                $total_minutes = floatval($matches[1]);
            }
        }
        
        return $total_minutes > 0 ? $total_minutes : 0;
    }
}
