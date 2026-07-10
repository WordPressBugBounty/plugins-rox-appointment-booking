<?php

namespace RoxAppointmentBooking\Modules\Notification\Data;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Supports\Abstracts\AbstractModel;

/**
 * Class NotificationModel
 * 
 * @package RoxAppointmentBooking\Modules\Notification\Data
 * @description Represents a notification in the system.
 */
class NotificationModel extends AbstractModel
{
    /**
     * Notification table name.
     *
     * @var string
     */
    protected $table = ROX_APPOINTMENT_BOOKING_DB_PREFIX . ROX_APPOINTMENT_BOOKING_PREFIX . '_notification';

    /**
     * Mass-assignable attributes.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'type',
        'is_viewed',
        'view_count',
        'notification_time',
        'action_link',
    ];

    /**
     * Attribute cast rules.
     *
     * @var array
     */
    protected $casts = [
        'user_id' => 'integer',
        'is_viewed' => 'boolean',
        'view_count' => 'integer',
        'notification_time' => 'datetime',
        'action_link' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Mark notification as viewed
     *
     * @return bool
     */
    public function markAsViewed(): bool
    {
        $this->is_viewed = true;
        $this->view_count = ($this->view_count ?? 0) + 1;
        return $this->save();
    }

    /**
     * Check if notification is viewed
     *
     * @return bool
     */
    public function isViewed(): bool
    {
        return (bool) $this->is_viewed;
    }

    /**
     * Get time ago format
     *
     * @return string
     */
    public function getTimeAgo(): string
    {
        if (!$this->notification_time) {
            return '';
        }

        $time = strtotime($this->notification_time);
        $diff = time() - $time;

        if ($diff < 60) {
            // translators: %d = number of seconds
            return sprintf(esc_html__('%d seconds ago', 'rox-appointment-booking'), $diff);
        } elseif ($diff < 3600) {
            // translators: %d = number of minutes
            return sprintf(esc_html__('%d minutes ago', 'rox-appointment-booking'), floor($diff / 60));
        } elseif ($diff < 86400) {
            // translators: %d = number of hours
            return sprintf(esc_html__('%d hours ago', 'rox-appointment-booking'), floor($diff / 3600));
        } elseif ($diff < 604800) {
            // translators: %d = number of days
            return sprintf(esc_html__('%d days ago', 'rox-appointment-booking'), floor($diff / 86400));
        } else {
            return date_i18n(get_option('date_format'), $time);
        }
    }

    /**
     * Scope: Get unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('is_viewed', 0);
    }

    /**
     * Scope: Get notifications by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Get notifications by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Get recent notifications
     */
    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('notification_time', 'DESC')->limit($limit);
    }
}
