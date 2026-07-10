<?php

namespace RoxAppointmentBooking\Modules\Service\Data;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Supports\Abstracts\AbstractModel;

/**
 * Class ServiceModel
 *
 * @package RoxAppointmentBooking\Modules\Service\Data
 * @description Represents a service in the system.
 */
class ServiceModel extends AbstractModel
{
    /**
     * Service table name.
     *
     * @var string
     */
    protected $table = ROX_APPOINTMENT_BOOKING_DB_PREFIX . ROX_APPOINTMENT_BOOKING_PREFIX . '_service';

    /**
     * Mass-assignable attributes.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'description',
        'duration',
        'price',
        'capacity',
        'max_capacity',
        'deposit',
        'deposit_type',
        'deposit_amount',
        'weekly_schedule',
        'color',
        'sort_order',
        'hide_price_booking_panel',
        'hide_duration_booking_panel',
        'only_visible_to_agent',
        'thumbnail_id',
        'status',
        'internal_notes',
        'created_by',
        'updated_by',
    ];

    /**
     * Attribute cast rules.
     *
     * @var array
     */
    protected $casts = [
        'deposit' => 'boolean',
        'hide_price_booking_panel' => 'boolean',
        'hide_duration_booking_panel' => 'boolean',
        'only_visible_to_agent' => 'boolean',
        'thumbnail_id' => 'integer',
        'sort_order' => 'integer',
        'price' => 'float',
        'deposit_amount' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'weekly_schedule' => 'json',
    ];

    /**
     * Get formatted price with currency
     *
     * @param string $currency
     * @return string
     */
    public function getFormattedPrice(string $currency = '$'): string
    {
        return $currency . number_format($this->price, 2);
    }

    /**
     * Get formatted deposit amount with currency
     *
     * @param string $currency
     * @return string
     */
    public function getFormattedDepositAmount(string $currency = '$'): string
    {
        if (!$this->deposit || !$this->deposit_amount) {
            return '';
        }
        return $currency . number_format($this->deposit_amount, 2);
    }

    /**
     * Check if service is active
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if service requires deposit
     *
     * @return bool
     */
    public function requiresDeposit(): bool
    {
        return !empty($this->deposit);
    }

    /**
     * Scope to get only active services
     *
     * @param $query
     * @return mixed
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get only visible services (not only_visible_to_agent)
     *
     * @param $query
     * @return mixed
     */
    public function scopeVisible($query)
    {
        return $query->where('only_visible_to_agent', 0);
    }

    /**
     * Check if service has weekly schedule
     *
     * @return bool
     */
    public function hasWeeklySchedule(): bool
    {
        return !empty($this->weekly_schedule);
    }

    /**
     * Get payment methods as array
     *
     * @return array
     */
    public function getPaymentMethodsArray(): array
    {
        if (empty($this->payment_methods)) {
            return [];
        }
        return explode(',', $this->payment_methods);
    }

    /**
     * Check if service has minimum extra service requirement
     *
     * @return bool
     */
    public function hasMinimumExtraServiceRequirement(): bool
    {
        return !empty($this->active_minimum_extra_service) && !empty($this->minimum_extra_services);
    }

    /**
     * Check if service has maximum extra service limit
     *
     * @return bool
     */
    public function hasMaximumExtraServiceLimit(): bool
    {
        return !empty($this->active_maximum_extra_service) && !empty($this->maximum_extra_services);
    }

    /**
     * Get service color or default
     *
     * @param string $default
     * @return string
     */
    public function getColor(string $default = '#007cba'): string
    {
        return !empty($this->color) ? $this->color : $default;
    }

    /**
     * Check if price should be hidden in booking panel
     *
     * @return bool
     */
    public function shouldHidePriceInBookingPanel(): bool
    {
        return !empty($this->hide_price_booking_panel);
    }

    /**
     * Check if duration should be hidden in booking panel
     *
     * @return bool
     */
    public function shouldHideDurationInBookingPanel(): bool
    {
        return !empty($this->hide_duration_booking_panel);
    }

    /**
     * Get formatted duration string matching Service.php getDurationOptions() format
     *
     * @return string
     */
    public function getFormattedDuration(): string
    {
        if (empty($this->duration)) {
            return '';
        }

        $minutes = (int)$this->duration;
        
        if ($minutes <= 0) {
            return '0m';
        }

        // Handle weeks (10080 minutes = 1 week)
        if ($minutes >= 10080) {
            $weeks = floor($minutes / 10080);
            $remainingMinutes = $minutes % 10080;
            
            if ($remainingMinutes == 0) {
                return $weeks . 'w';
            }
            
            // If there are remaining days
            if ($remainingMinutes >= 1440) {
                $days = floor($remainingMinutes / 1440);
                $remaining = $remainingMinutes % 1440;
                if ($remaining == 0) {
                    return $weeks . 'w ' . $days . 'd';
                }
                // For complex combinations, fall back to days calculation
                $totalDays = floor($minutes / 1440);
                return $totalDays . 'd';
            }
            
            // For other combinations, fall back to days
            $totalDays = floor($minutes / 1440);
            return $totalDays . 'd';
        }
        
        // Handle days (1440 minutes = 1 day)
        if ($minutes >= 1440) {
            $days = floor($minutes / 1440);
            $remainingMinutes = $minutes % 1440;
            
            if ($remainingMinutes == 0) {
                return $days . 'd';
            }
            
            // For days with remaining time, we typically just show total days
            // unless it's a clean hour boundary
            if ($remainingMinutes >= 60 && $remainingMinutes % 60 == 0) {
                $hours = $remainingMinutes / 60;
                return $days . 'd ' . $hours . 'h';
            }
            
            // Otherwise just show total days (rounded)
            return $days . 'd';
        }
        
        // Handle hours (60 minutes and above, less than a day)
        if ($minutes >= 60) {
            $hours = floor($minutes / 60);
            $remainingMinutes = $minutes % 60;
            
            if ($remainingMinutes == 0) {
                return $hours . 'h';
            }
            
            return $hours . 'h ' . $remainingMinutes . 'm';
        }
        
        // Handle minutes only (less than 60 minutes)
        return $minutes . 'm';
    }
}
