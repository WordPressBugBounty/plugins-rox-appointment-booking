<?php

namespace RoxAppointmentBooking\Modules\RelationshipModel\Data;

use RoxAppointmentBooking\Supports\Abstracts\AbstractModel;

/**
 * Class ServiceLocationRelationModel
 *
 * @package RoxAppointmentBooking\Modules\RelationshipModel\Data
 * @description Model class for managing service-location relations data.
 */
class ServiceLocationRelationModel extends AbstractModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = ROX_APPOINTMENT_BOOKING_DB_PREFIX . ROX_APPOINTMENT_BOOKING_PREFIX . '_location_service';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'location_id',
        'service_id'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'location_id' => 'integer',
        'service_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Resolve the LocationModel class from pro plugin if active, otherwise null.
     *
     * @return class-string|null
     */
    public static function resolveLocationModelClass(): ?string
    {

        $proClass = '\\RoxAppointmentBookingPro\\Modules\\Location\\Data\\LocationModel';
        return (defined('ROX_APPOINTMENT_BOOKING_PRO_VERSION') && class_exists($proClass)) ? $proClass : null;
    }

    /**
     * Get the location that owns the relation.
     *
     * @return mixed
     */
    public function location()
    {
        $locationClass = self::resolveLocationModelClass();
        if (!$locationClass) {
            return null;
        }
        return $this->belongsTo($locationClass, 'location_id');
    }

    /**
     * Get the service that owns the relation.
     *
     * @return mixed
     */
    public function service()
    {
        return $this->belongsTo(
            \RoxAppointmentBooking\Modules\Service\Data\ServiceModel::class,
            'service_id'
        );
    }

    /**
     * Scope a query to only include relations for a specific location.
     *
     * @param \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingQueryBuilder $query
     * @param int $location_id
     * @return \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingQueryBuilder
     */
    public function scopeByLocation($query, $location_id)
    {
        return $query->where('location_id', $location_id);
    }

    /**
     * Scope a query to only include relations for a specific service.
     *
     * @param \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingQueryBuilder $query
     * @param int $service_id
     * @return \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingQueryBuilder
     */
    public function scopeByService($query, $service_id)
    {
        return $query->where('service_id', $service_id);
    }

    /**
     * Scope a query to include location and service details.
     *
     * @param \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingQueryBuilder $query
     * @return \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingQueryBuilder
     */
    public function scopeWithDetails($query)
    {
        return $query->with(['location', 'service']);
    }

    /**
     * Convert the model instance to an array with additional computed fields.
     *
     * @return array
     */
    public function toDetailedArray(): array
    {
        $array = $this->toArray();
        
        // Include related location data if loaded
        if ($this->relationLoaded('location') && $this->location) {
            $array['location'] = [
                'id' => $this->location->id,
                'title' => $this->location->title,
                'address' => $this->location->address,
                'city' => $this->location->city,
                'phone' => $this->location->phone,
                'formatted_address' => $this->location->address . ', ' . $this->location->city
            ];
        }

        // Include related service data if loaded
        if ($this->relationLoaded('service') && $this->service) {
            $array['service'] = [
                'id' => $this->service->id,
                'title' => $this->service->title,
                'description' => $this->service->description,
                'price' => $this->service->price,
                'duration' => $this->service->duration,
                'status' => $this->service->status
            ];
        }

        return $array;
    }

    /**
     * Create a new service-location relation with validation.
     *
     * @param int $location_id
     * @param int $service_id
     * @return ServiceLocationRelationModel
     * @throws \Exception
     */
    public static function createRelation(int $location_id, int $service_id): self
    {
        // Validate location exists (only when pro LocationModel is available)
        $locationClass = self::resolveLocationModelClass();
        if ($locationClass) {
            $location = $locationClass::find($location_id);
            if (!$location) {
                throw new \Exception(esc_html__('Location not found', 'rox-appointment-booking'));
            }
        }

        // Validate service exists
        $service = \RoxAppointmentBooking\Modules\Service\Data\ServiceModel::find($service_id);
        if (!$service) {
            throw new \Exception(esc_html__('Service not found', 'rox-appointment-booking'));
        }

        // Check if relation already exists
        $existing = self::query()
            ->where('location_id', $location_id)
            ->where('service_id', $service_id)
            ->first();

        if ($existing) {
            throw new \Exception(esc_html__('A relation between this location and service already exists', 'rox-appointment-booking'));
        }

        $relation = new self();
        $relation->location_id = $location_id;
        $relation->service_id = $service_id;
        $relation->save();

        return $relation;
    }

    /**
     * Check if a relation exists between location and service.
     *
     * @param int $location_id
     * @param int $service_id
     * @return bool
     */
    public static function relationExists(int $location_id, int $service_id): bool
    {
        return self::query()
            ->where('location_id', $location_id)
            ->where('service_id', $service_id)
            ->exists();
    }

    /**
     * Get all services for a location.
     *
     * @param int $location_id
     * @return \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingCollection
     */
    public static function getServicesForLocation(int $location_id)
    {
        return self::query()
            ->byLocation($location_id)
            ->with('service')
            ->get()
            ->pluck('service')
            ->filter();
    }

    /**
     * Get all locations for a service.
     *
     * @param int $service_id
     * @return \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingCollection
     */
    public static function getLocationsForService(int $service_id)
    {
        return self::query()
            ->byService($service_id)
            ->with('location')
            ->get()
            ->pluck('location')
            ->filter();
    }

    /**
     * Remove relation between location and service.
     *
     * @param int $location_id
     * @param int $service_id
     * @return bool
     * @throws \Exception
     */
    public static function removeRelation(int $location_id, int $service_id): bool
    {
        $relation = self::query()
            ->where('location_id', $location_id)
            ->where('service_id', $service_id)
            ->first();

        if (!$relation) {
            throw new \Exception(esc_html__('Relation not found', 'rox-appointment-booking'));
        }

        // Check if there are any active appointments for this location-service combination
        $active_appointments = \RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel::query()
            ->where('location_id', $location_id)
            ->whereJsonContains('service_details->id', $service_id)
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->count();

        if ($active_appointments > 0) {
            throw new \Exception(sprintf(
                // translators: %d = number of active appointments for the location-service combination
                esc_html__('Cannot remove this relation. There are %d active appointment(s) for this location-service combination', 'rox-appointment-booking'),
                absint($active_appointments)
            ));
        }

        return $relation->delete();
    }

    /**
     * Bulk create relations for a location.
     *
     * @param int $location_id
     * @param array $service_ids
     * @return array
     * @throws \Exception
     */
    public static function bulkCreateForLocation(int $location_id, array $service_ids): array
    {
        $relations = [];
        $errors = [];

        foreach ($service_ids as $service_id) {
            try {
                if (!self::relationExists($location_id, $service_id)) {
                    $relations[] = self::createRelation($location_id, $service_id);
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'service_id' => $service_id,
                    'error' => $e->getMessage()
                ];
            }
        }

        if (!empty($errors)) {
            throw new \Exception('Some relations failed: ' . json_encode($errors));
        }

        return $relations;
    }

    /**
     * Bulk create relations for a service.
     *
     * @param int $service_id
     * @param array $location_ids
     * @return array
     * @throws \Exception
     */
    public static function bulkCreateForService(int $service_id, array $location_ids): array
    {
        $relations = [];
        $errors = [];

        foreach ($location_ids as $location_id) {
            try {
                if (!self::relationExists($location_id, $service_id)) {
                    $relations[] = self::createRelation($location_id, $service_id);
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'location_id' => $location_id,
                    'error' => $e->getMessage()
                ];
            }
        }

        if (!empty($errors)) {
            throw new \Exception('Some relations failed: ' . json_encode($errors));
        }

        return $relations;
    }
}
