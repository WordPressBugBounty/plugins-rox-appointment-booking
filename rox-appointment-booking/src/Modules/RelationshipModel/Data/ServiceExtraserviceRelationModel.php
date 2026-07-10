<?php

namespace RoxAppointmentBooking\Modules\RelationshipModel\Data;

use RoxAppointmentBooking\Supports\Abstracts\AbstractModel;

/**
 * Class ServiceExtraserviceRelationModel
 *
 * @package RoxAppointmentBooking\Modules\RelationshipModel\Data
 * @description Model class for managing service-extra service relations data.
 */
class ServiceExtraserviceRelationModel extends AbstractModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = ROX_APPOINTMENT_BOOKING_DB_PREFIX . ROX_APPOINTMENT_BOOKING_PREFIX . '_service_extra_service';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'extra_service_id',
        'service_id'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'extra_service_id' => 'integer',
        'service_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Resolve the ExtraServiceModel class from pro plugin if active, otherwise null.
     *
     * @return class-string|null
     */
    public static function resolveExtraServiceModelClass(): ?string
    {
        $proClass = '\\RoxAppointmentBookingPro\\Modules\\ExtraService\\Data\\ExtraServiceModel';
        return (defined('ROX_APPOINTMENT_BOOKING_PRO_VERSION') && class_exists($proClass)) ? $proClass : null;
    }

    /**
     * Get the extra service that owns the relation.
     *
     * @return mixed
     */
    public function extraService()
    {
        $extraServiceClass = self::resolveExtraServiceModelClass();
        if (!$extraServiceClass) {
            return null;
        }
        return $this->belongsTo($extraServiceClass, 'extra_service_id');
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
     * Scope a query to only include relations for a specific extra service.
     *
     * @param \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingQueryBuilder $query
     * @param int $extra_service_id
     * @return \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingQueryBuilder
     */
    public function scopeByExtraService($query, $extra_service_id)
    {
        return $query->where('extra_service_id', $extra_service_id);
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
     * Scope a query to include extra service and service details.
     *
     * @param \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingQueryBuilder $query
     * @return \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingQueryBuilder
     */
    public function scopeWithDetails($query)
    {
        return $query->with(['extraService', 'service']);
    }

    /**
     * Convert the model instance to an array with additional computed fields.
     *
     * @return array
     */
    public function toDetailedArray(): array
    {
        $array = $this->toArray();
        
        // Include related extra service data if loaded
        if ($this->relationLoaded('extraService') && $this->extraService) {
            $array['extra_service'] = [
                'id' => $this->extraService->id,
                'title' => $this->extraService->title,
                'description' => $this->extraService->description,
                'price' => $this->extraService->price,
                'duration' => $this->extraService->duration,
                'status' => $this->extraService->status
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
     * Create a new service-extra service relation with validation.
     *
     * @param int $extra_service_id
     * @param int $service_id
     * @return ServiceExtraserviceRelationModel
     * @throws \Exception
     */
    public static function createRelation(int $extra_service_id, int $service_id): self
    {
        // Validate extra service exists
        $extraServiceModelClass = self::resolveExtraServiceModelClass();
        if (!$extraServiceModelClass) {
            throw new \Exception(esc_html__('Extra service feature requires the Pro plugin', 'rox-appointment-booking'));
        }
        $extraService = $extraServiceModelClass::find($extra_service_id);
        if (!$extraService) {
            throw new \Exception(esc_html__('Extra service not found', 'rox-appointment-booking'));
        }

        // Validate service exists
        $service = \RoxAppointmentBooking\Modules\Service\Data\ServiceModel::find($service_id);
        if (!$service) {
            throw new \Exception(esc_html__('Service not found', 'rox-appointment-booking'));
        }

        // Check if relation already exists
        $existing = self::query()
            ->where('extra_service_id', $extra_service_id)
            ->where('service_id', $service_id)
            ->first();

        if ($existing) {
            throw new \Exception(esc_html__('A relation between this extra service and service already exists', 'rox-appointment-booking'));
        }

        $relation = new self();
        $relation->extra_service_id = $extra_service_id;
        $relation->service_id = $service_id;
        $relation->save();

        return $relation;
    }

    /**
     * Check if a relation exists between extra service and service.
     *
     * @param int $extra_service_id
     * @param int $service_id
     * @return bool
     */
    public static function relationExists(int $extra_service_id, int $service_id): bool
    {
        return self::query()
            ->where('extra_service_id', $extra_service_id)
            ->where('service_id', $service_id)
            ->exists();
    }

    /**
     * Get all services for an extra service.
     *
     * @param int $extra_service_id
     * @return \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingCollection
     */
    public static function getServicesForExtraService(int $extra_service_id)
    {
        return self::query()
            ->byExtraService($extra_service_id)
            ->with('service')
            ->get()
            ->pluck('service')
            ->filter();
    }

    /**
     * Get all extra services for a service.
     *
     * @param int $service_id
     * @return \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingCollection
     */
    public static function getExtraServicesForService(int $service_id)
    {
        return self::query()
            ->byService($service_id)
            ->with('extraService')
            ->get()
            ->pluck('extraService')
            ->filter();
    }

    /**
     * Remove relation between extra service and service.
     *
     * @param int $extra_service_id
     * @param int $service_id
     * @return bool
     * @throws \Exception
     */
    public static function removeRelation(int $extra_service_id, int $service_id): bool
    {
        $relation = self::query()
            ->where('extra_service_id', $extra_service_id)
            ->where('service_id', $service_id)
            ->first();

        if (!$relation) {
            throw new \Exception(esc_html__('Relation not found', 'rox-appointment-booking'));
        }

        // Check if there are any active appointments for this extra service-service combination
        $active_appointments = \RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel::query()
            ->whereJsonContains('service_details->id', $service_id)
            ->whereJsonContains('extra_service_details', ['id' => $extra_service_id])
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->count();

        if ($active_appointments > 0) {
            throw new \Exception(sprintf(
                // translators: %d = number of active appointments for the category-service combination
                esc_html__('Cannot remove this relation. There are %d active appointment(s) for this extra service-service combination', 'rox-appointment-booking'),
                absint($active_appointments)
            ));
        }

        return $relation->delete();
    }

    /**
     * Bulk create relations for an extra service.
     *
     * @param int $extra_service_id
     * @param array $service_ids
     * @return array
     * @throws \Exception
     */
    public static function bulkCreateForExtraService(int $extra_service_id, array $service_ids): array
    {
        $relations = [];
        $errors = [];

        foreach ($service_ids as $service_id) {
            try {
                if (!self::relationExists($extra_service_id, $service_id)) {
                    $relations[] = self::createRelation($extra_service_id, $service_id);
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
     * @param array $extra_service_ids
     * @return array
     * @throws \Exception
     */
    public static function bulkCreateForService(int $service_id, array $extra_service_ids): array
    {
        $relations = [];
        $errors = [];

        foreach ($extra_service_ids as $extra_service_id) {
            try {
                if (!self::relationExists($extra_service_id, $service_id)) {
                    $relations[] = self::createRelation($extra_service_id, $service_id);
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'extra_service_id' => $extra_service_id,
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
