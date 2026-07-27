<?php

namespace RoxAppointmentBooking\Modules\Payment\Services;

use RoxAppointmentBooking\Modules\Payment\Data\PaymentModel;
use RoxAppointmentBooking\Modules\Order\Data\OrderModel;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\Notification\Services\NotificationService;
use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;

defined('ABSPATH') || exit;

/**
 * Class PaymentStatusSyncService
 *
 * @package RoxAppointmentBooking\Modules\Payment
 * @description Applies a status to a Payment and cascades it to the related Order and Appointments.
 */
class PaymentStatusSyncService
{
    /**
     * Update a payment's status and cascade the change to its order and appointments.
     *
     * @param int $paymentId Payment ID.
     * @param string $status New status.
     * @return PaymentModel|null The updated payment, or null if not found.
     */
    public static function applyStatus(int $paymentId, string $status): ?PaymentModel
    {
        $payment = PaymentModel::find($paymentId);

        if (!$payment) {
            return null;
        }

        $oldStatus = $payment->status;
        $payment->update(['status' => $status]);

        if ($oldStatus !== $status) {
            $customerName = __('Customer', 'rox-appointment-booking');
            if ($payment->customer_id) {
                $customer = CustomerModel::find($payment->customer_id);
                if ($customer && (!empty($customer->full_name) || !empty($customer->first_name))) {
                    $customerName = $customer->full_name ?? trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
                }
            }
            NotificationService::createPaymentNotification([
                'admin_user_id' => get_current_user_id() ?: null,
                'customer_name' => $customerName,
                'amount' => $payment->amount,
                'payment_id' => $payment->id,
                'status' => $status
            ]);
        }

        if ($payment->order_id) {
            $order = OrderModel::find($payment->order_id);
            if ($order) {
                $order->update(['payment_status' => $status]);

                $bookingIds = $order->getBookingIds();
                if (!empty($bookingIds)) {
                    AppointmentModel::whereIn('id', $bookingIds)
                        ->update(['payment_status' => $status]);
                }
            }
        }

        return $payment;
    }
}
