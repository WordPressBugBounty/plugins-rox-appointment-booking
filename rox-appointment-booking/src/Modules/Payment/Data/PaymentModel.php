<?php

namespace RoxAppointmentBooking\Modules\Payment\Data;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Supports\Abstracts\AbstractModel;


/**
 * Class PaymentModel
 *
 * @package RoxAppointmentBooking\Modules\Payment
 * @description Represents PaymentModel data in the system.
 */
class PaymentModel extends AbstractModel
{
    /**
     * Payment table name.
     *
     * @var string
     */
    protected $table = ROX_APPOINTMENT_BOOKING_DB_PREFIX . ROX_APPOINTMENT_BOOKING_PREFIX . '_payment';

    /**
     * Mass-assignable attributes.
     *
     * @var array
     */
    protected $fillable = [
        'customer_id',
        'order_id',
        'amount',
        'status',
        'payment_method',
        'transaction_id',
        'payment_time',
        'internal_notes',
    ];

    /**
     * Attribute cast rules.
     *
     * @var array
     */
    protected $casts = [
        'customer_id' => 'integer',
        'amount' => 'decimal:2',
        'payment_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    const STATUS_UNPAID = 'unpaid';
    const STATUS_PAID = 'paid';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';

    /**
     * Check whether the payment is completed.
     *
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Check whether the payment is pending.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_UNPAID;
    }

    /**
     * Check whether the payment is refunded.
     *
     * @return bool
     */
    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    /**
     * Get the customer related to this payment.
     *
     * @return mixed
     */
    public function customer()
    {
        return $this->belongsTo(
            \RoxAppointmentBooking\Modules\Customer\Data\CustomerModel::class,
            'customer_id',
            'id'
        );
    }
}
