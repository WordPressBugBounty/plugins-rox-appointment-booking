<?php

namespace RoxAppointmentBooking\Modules\Customer\Data;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Supports\Abstracts\AbstractModel;

/**
 * Class CustomerModel
 * 
 * @package RoxAppointmentBooking\Modules\Customer\Data
 * @description Represents a customer in the system.
 */
class CustomerModel extends AbstractModel
{
    /**
     * Customer table name.
     *
     * @var string
     */
    protected $table = ROX_APPOINTMENT_BOOKING_DB_PREFIX . ROX_APPOINTMENT_BOOKING_PREFIX . '_customer';

    /**
     * Mass-assignable attributes.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'thumbnail_id',
        'gender',
        'dob',
        'allow_to_login',
        'wp_user_id',
        'send_notifications',
        'internal_notes',
    ];

    /**
     * Attribute cast rules.
     *
     * @var array
     */
    protected $casts = [
        'wp_user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'dob' => 'date',
        'allow_to_login' => 'boolean',
        'send_notifications' => 'boolean',
    ];

    /**
     * Get full name by combining first and last name
     *
     * @return string
     */
    public function getFullName(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    /**
     * Get full_name attribute for backward compatibility
     * 
     * @return string
     */
    public function getFullNameAttribute(): string
    {
        return $this->getFullName();
    }
}
