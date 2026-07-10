<?php

namespace RoxAppointmentBooking\Modules\FrontendBookingPanel\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBookingPro\Modules\Location\Data\LocationModel;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceLocationRelationModel;

/**
 * Class GetLocation
 *
 * @package RoxAppointmentBooking\Modules\FrontendBookingPanel\REST
 * @description Handles retrieving list of locations and single location via REST API.
 */
class GetLocation extends AbstractREST
{
    /**
     * Whether this REST endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * REST route for listing and retrieving public locations.
     *
     * @var string
     */
    public static string $route = '/public/location(?:/(?P<id>\d+))?';

    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/public/location/';

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
     * Get location data formatted for response
     * 
     * @param LocationModel $location
     * @param bool $detailed Whether to include detailed information
     * @return array
     */
    protected function getLocationData(LocationModel $location, bool $detailed = false): array
    {
        $data = [
            'id' => $location->getID(),
            'name' => $location->getName(),
            'iconPath' => $location->thumbnail_id ? wp_get_attachment_url($location->thumbnail_id) : '',
            'backgroundColor' => '#FCEFE3',
        ];

        if ($detailed) {
            $data = array_merge($data, [
                'description' => $location->description,
                'thumbnail_id' => $location->thumbnail_id,
                'geo_position' => $location->geo_position,
                'internal_notes' => $location->internal_notes,
                'service_ids' => $this->getServiceIdsByLocationId($location->getID()),
                'created_at' => $location->created_at,
                'updated_at' => $location->updated_at,
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
        if (!rox_appointment_booking_is_pro_user()) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 403,
                message: esc_html__('Location feature requires the Pro plan.', 'rox-appointment-booking'),
                headers: ['status' => 403]
            );
        }

        $id = $request->get_param('id');

        if ($id) {
            $location = LocationModel::find($id);
            if (!$location) {
                return rox_appointment_booking_rest_response(
                    data: null,
                    code: 404,
                    message: esc_html__('Location not found', 'rox-appointment-booking'),
                    headers: ['status' => 404]
                );
            }

            return rox_appointment_booking_rest_response(
                data: $this->getLocationData($location, true),
                message: esc_html__('Location retrieved successfully', 'rox-appointment-booking')
            );
        }

        $page = $request->get_param('page') ?? 1;
        $per_page = $request->get_param('per_page') ?? 20;
        $search = $request->get_param('search') ?? '';
        $service_id = $request->get_param('service_id') ?? null;

        $query = LocationModel::query();

        // Filter by service if service_id is provided
        if (!empty($service_id)) {
            // Get location IDs that belong to the specified service
            $locationIds = ServiceLocationRelationModel::where('service_id', $service_id)
                ->pluck('location_id')
                ->toArray();
            
            // Filter locations by the IDs found in the service relation
            if (!empty($locationIds)) {
                $query->whereIn('id', $locationIds);
            } else {
                // If no locations found for this service, return empty result
                $query->where('id', 0);
            }
        }

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('address', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        $total = $query->count();
        $locations = $query->offset(($page - 1) * $per_page)
                          ->limit($per_page)
                          ->orderBy('created_at', 'DESC')
                          ->get();

        $data = [];
        
        foreach ($locations as $location) {
            $data[] = $this->getLocationData($location);
        }

        $meta = [
            'total' => $total,
            'page' => (int)$page,
            'per_page' => (int)$per_page,
            'total_pages' => ceil($total / $per_page)
        ];

        return rox_appointment_booking_rest_response(
            data: $data,
            message: esc_html__('Locations retrieved successfully', 'rox-appointment-booking'),
            options: [
                "meta_data" => $meta,
            ]
        );
    }

    /**
     * Get service IDs array by location ID
     * 
     * @param int $locationId Location ID
     * @return array Service IDs
     */
    protected function getServiceIdsByLocationId($locationId): array
    {
        $serviceLocationRelations = ServiceLocationRelationModel::where('location_id', $locationId)->get();
        $serviceIds = [];
        
        foreach ($serviceLocationRelations as $relation) {
            $serviceIds[] = $relation->service_id;
        }
        
        return $serviceIds;
    }
}
