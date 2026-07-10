<?php

namespace RoxAppointmentBooking\Modules\RelationshipModel\Data;

use RoxAppointmentBooking\Supports\Abstracts\AbstractModel;

/**
 * Class ServiceCategoryRelationModel
 *
 * @package RoxAppointmentBooking\Modules\RelationshipModel\Data
 * @description Model class for managing service-category relations data.
 */
class ServiceCategoryRelationModel extends AbstractModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = ROX_APPOINTMENT_BOOKING_DB_PREFIX . ROX_APPOINTMENT_BOOKING_PREFIX . '_service_category';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'category_id',
        'service_id'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'category_id' => 'integer',
        'service_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the category that owns the relation.
     *
     * @return mixed
     */
    public function category()
    {
        return $this->belongsTo(
            \RoxAppointmentBooking\Modules\Category\Data\CategoryModel::class,
            'category_id'
        );
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
     * Scope a query to only include relations for a specific category.
     *
     * @param \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingQueryBuilder $query
     * @param int $category_id
     * @return \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingQueryBuilder
     */
    public function scopeByCategory($query, $category_id)
    {
        return $query->where('category_id', $category_id);
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
     * Scope a query to include category and service details.
     *
     * @param \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingQueryBuilder $query
     * @return \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingQueryBuilder
     */
    public function scopeWithDetails($query)
    {
        return $query->with(['category', 'service']);
    }

    /**
     * Convert the model instance to an array with additional computed fields.
     *
     * @return array
     */
    public function toDetailedArray(): array
    {
        $array = $this->toArray();
        
        // Include related category data if loaded
        if ($this->relationLoaded('category') && $this->category) {
            $array['category'] = [
                'id' => $this->category->id,
                'title' => $this->category->title,
                'description' => $this->category->description,
                'thumbnail_id' => $this->category->thumbnail_id,
                'notes' => $this->category->internal_notes
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
     * Create a new service-category relation with validation.
     *
     * @param int $category_id
     * @param int $service_id
     * @return ServiceCategoryRelationModel
     * @throws \Exception
     */
    public static function createRelation(int $category_id, int $service_id): self
    {
        // Validate category exists
        $category = \RoxAppointmentBooking\Modules\Category\Data\CategoryModel::find($category_id);
        if (!$category) {
            throw new \Exception(esc_html__('Category not found', 'rox-appointment-booking'));
        }

        // Validate service exists
        $service = \RoxAppointmentBooking\Modules\Service\Data\ServiceModel::find($service_id);
        if (!$service) {
            throw new \Exception(esc_html__('Service not found', 'rox-appointment-booking'));
        }

        // Check if relation already exists
        $existing = self::query()
            ->where('category_id', $category_id)
            ->where('service_id', $service_id)
            ->first();

        if ($existing) {
            throw new \Exception(esc_html__('A relation between this category and service already exists', 'rox-appointment-booking'));
        }

        $relation = new self();
        $relation->category_id = $category_id;
        $relation->service_id = $service_id;
        $relation->save();

        return $relation;
    }

    /**
     * Check if a relation exists between category and service.
     *
     * @param int $category_id
     * @param int $service_id
     * @return bool
     */
    public static function relationExists(int $category_id, int $service_id): bool
    {
        return self::query()
            ->where('category_id', $category_id)
            ->where('service_id', $service_id)
            ->exists();
    }

    /**
     * Get all services for a category.
     *
     * @param int $category_id
     * @return \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingCollection
     */
    public static function getServicesForCategory(int $category_id)
    {
        return self::query()
            ->byCategory($category_id)
            ->with('service')
            ->get()
            ->pluck('service')
            ->filter();
    }

    /**
     * Get all categories for a service.
     *
     * @param int $service_id
     * @return \RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingCollection
     */
    public static function getCategoriesForService(int $service_id)
    {
        return self::query()
            ->byService($service_id)
            ->with('category')
            ->get()
            ->pluck('category')
            ->filter();
    }

    /**
     * Remove relation between category and service.
     *
     * @param int $category_id
     * @param int $service_id
     * @return bool
     * @throws \Exception
     */
    public static function removeRelation(int $category_id, int $service_id): bool
    {
        $relation = self::query()
            ->where('category_id', $category_id)
            ->where('service_id', $service_id)
            ->first();

        if (!$relation) {
            throw new \Exception(esc_html__('Relation not found', 'rox-appointment-booking'));
        }

        // Check if there are any active appointments for this category-service combination
        $active_appointments = \RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel::query()
            ->where('category_id', $category_id)
            ->whereJsonContains('service_details->id', $service_id)
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->count();

        if ($active_appointments > 0) {
            throw new \Exception(sprintf(
                // translators: %d = number of active appointments for the category-service combination
                esc_html__('Cannot remove this relation. There are %d active appointment(s) for this category-service combination', 'rox-appointment-booking'),
                absint($active_appointments)
            ));
        }

        return $relation->delete();
    }

    /**
     * Bulk create relations for a category.
     *
     * @param int $category_id
     * @param array $service_ids
     * @return array
     * @throws \Exception
     */
    public static function bulkCreateForCategory(int $category_id, array $service_ids): array
    {
        $relations = [];
        $errors = [];

        foreach ($service_ids as $service_id) {
            try {
                if (!self::relationExists($category_id, $service_id)) {
                    $relations[] = self::createRelation($category_id, $service_id);
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
     * @param array $category_ids
     * @return array
     * @throws \Exception
     */
    public static function bulkCreateForService(int $service_id, array $category_ids): array
    {
        $relations = [];
        $errors = [];

        foreach ($category_ids as $category_id) {
            try {
                if (!self::relationExists($category_id, $service_id)) {
                    $relations[] = self::createRelation($category_id, $service_id);
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'category_id' => $category_id,
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
