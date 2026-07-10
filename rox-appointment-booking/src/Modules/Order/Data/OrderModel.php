<?php

namespace RoxAppointmentBooking\Modules\Order\Data;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Supports\Abstracts\AbstractModel;

/**
 * Class OrderModel
 * 
 * @package RoxAppointmentBooking\Modules\Order\Data
 * @description Represents an order in the system.
 */
class OrderModel extends AbstractModel
{
    /**
     * Order table name.
     *
     * @var string
     */
    protected $table = ROX_APPOINTMENT_BOOKING_DB_PREFIX . ROX_APPOINTMENT_BOOKING_PREFIX . '_order';

    /**
     * Mass-assignable attributes.
     *
     * @var array
     */
    protected $fillable = [
        'customer_id',
        'booking_ids',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'currency',
        'coupon_id',
        'coupon_code',
        'payment_method',
        'payment_status',
        'payment_transaction_id',
        'order_status',
        'order_date',
        'fulfillment_date',
        'cancellation_date',
        'refund_amount',
        'refund_date',
        'refund_reason',
        'internal_notes',
    ];

    /**
     * Attribute cast rules.
     *
     * @var array
     */
    protected $casts = [
        'customer_id' => 'integer',
        'coupon_id' => 'integer',
        'booking_ids' => 'json',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'order_date' => 'datetime',
        'fulfillment_date' => 'datetime',
        'cancellation_date' => 'datetime',
        'refund_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the order number formatted
     *
     * @return string
     */
    public function getOrderNumber(): string
    {
        return 'ORD-' . str_pad($this->id ?? 0, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get order_number attribute for backward compatibility
     * 
     * @return string
     */
    public function getOrderNumberAttribute(): string
    {
        return $this->getOrderNumber();
    }

    /**
     * Check if the order is paid
     *
     * @return bool
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'completed' || $this->payment_status === 'paid';
    }

    /**
     * Check if the order is pending
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return in_array($this->order_status, ['pending', 'pending_payment', 'on_hold']);
    }

    /**
     * Check if the order is completed
     *
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->order_status === 'completed';
    }

    /**
     * Check if the order is cancelled
     *
     * @return bool
     */
    public function isCancelled(): bool
    {
        return $this->order_status === 'cancelled';
    }

    /**
     * Check if the order is refunded
     *
     * @return bool
     */
    public function isRefunded(): bool
    {
        return !empty($this->refund_amount) && $this->refund_amount > 0;
    }

    /**
     * Get formatted total amount with currency
     *
     * @return string
     */
    public function getFormattedTotal(): string
    {
        $symbol = $this->getCurrencySymbol();
        return $symbol . number_format($this->total_amount, 2);
    }

    /**
     * Get currency symbol
     *
     * @return string
     */
    private function getCurrencySymbol(): string
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CAD' => 'C$',
            'AUD' => 'A$',
        ];

        return $symbols[$this->currency] ?? $this->currency;
    }

    /**
     * Get the booking IDs as an array
     *
     * @return array
     */
    public function getBookingIds(): array
    {
        $bookingIds = [];

        if (is_string($this->booking_ids)) {
            $bookingIds = json_decode($this->booking_ids, true) ?? [];
        } else {
            $bookingIds = (array) ($this->booking_ids ?? []);
        }

        return array_map('intval', $bookingIds);
    }
}
