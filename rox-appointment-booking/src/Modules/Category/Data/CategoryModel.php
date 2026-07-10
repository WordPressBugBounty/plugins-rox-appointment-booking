<?php

namespace RoxAppointmentBooking\Modules\Category\Data;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Supports\Abstracts\AbstractModel;

/**
 * Class CategoryModel
 * 
 * @package RoxAppointmentBooking\Modules\Category\Data
 * @description Represents a category in the system.
 */
class CategoryModel extends AbstractModel
{
    /**
     * Category table name.
     *
     * @var string
     */
    protected $table = ROX_APPOINTMENT_BOOKING_DB_PREFIX . ROX_APPOINTMENT_BOOKING_PREFIX . '_category';

    /**
     * Mass-assignable attributes.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'slug',
        'description',
        'thumbnail_id',
        'internal_notes',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    /**
     * Attribute cast rules.
     *
     * @var array
     */
    protected $casts = [
        'thumbnail_id' => 'integer',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the default values for the model attributes.
     * 
     * @return array
     */
    public static function getDefaults(): array
    {
        return [
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];
    }
}
