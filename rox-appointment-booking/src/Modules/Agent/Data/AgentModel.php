<?php

namespace RoxAppointmentBooking\Modules\Agent\Data;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Supports\Abstracts\AbstractModel;

/**
 * Class AgentModel
 *
 * @package RoxAppointmentBooking\Modules\Agent\Data
 * @description Represents an agent in the system.
 */
class AgentModel extends AbstractModel
{
    /**
     * Agent table name.
     *
     * @var string
     */
    protected $table = ROX_APPOINTMENT_BOOKING_DB_PREFIX . ROX_APPOINTMENT_BOOKING_PREFIX . '_agent';

    /**
     * Mass-assignable attributes.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'title',
        'email',
        'phone',
        'allow_to_login',
        'wp_user_id',
        'thumbnail_id',
        'location_id',
        'weekly_schedule',
        'holiday',
        'special_days',
        'internal_notes',
        'experience_years',
        'certifications',
        'linkedin',
        'twitter',
        'bio',
    ];

	/**
	 * Attribute cast rules.
	 *
	 * @var array
	 */
    protected $casts = [
        'allow_to_login' => 'boolean',
        'thumbnail_id' => 'integer',
        'location_id' => 'integer',
        'rating' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Check if agent can login
     *
     * @return bool
     */
    public function canLogin(): bool
    {
        return !empty($this->allow_to_login);
    }

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