<?php

namespace RoxAppointmentBooking\Modules\Appointment\Data;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Supports\Abstracts\AbstractModel;

/**
 * Class AppointmentModel
 * 
 * @package RoxAppointmentBooking\Modules\Appointment\Data
 * @description Represents an appointment in the system.
 */
class AppointmentModel extends AbstractModel
{
    /**
     * Appointment table name.
     *
     * @var string
     */
    protected $table = ROX_APPOINTMENT_BOOKING_DB_PREFIX . ROX_APPOINTMENT_BOOKING_PREFIX . '_booking';

    /**
     * Mass-assignable attributes.
     *
     * @var array
     */
    protected $fillable = [
        'location_id',
        'category_id',
        'service_details',
        'service_id',
        'extra_services',
        'agent_id',
        'customer_id',
        'date',
        'start_time',
        'end_time',
        'coupon_id',
        'purchase_details',
        'status',
        'payment_status',
        'total_attendees',
        'internal_notes',
        'send_notification',
        'reminder_notification',
        'created_at',
        'updated_at',
    ];

    /**
     * Attribute cast rules.
     *
     * @var array
     */
    protected $casts = [
        'service_details' => 'json',
        'extra_services' => 'json',
        'purchase_details' => 'json',
        'service_id' => 'int',
        'agent_id' => 'int',
        'customer_id' => 'int',
        'total_attendees' => 'int',
        'location_id' => 'int',
        'category_id' => 'int',
    ];

    /**
     * Get the default values for the model attributes.
     * 
     * @return array
     */
    public static function getDefaults(): array
    {
        return [
            'status' => 'active',
            'payment_status' => 'pending',
            'total_attendees' => 0,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];
    }
}