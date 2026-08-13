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
        $statusChanged = $oldStatus !== $status;
        $payment->update(['status' => $status]);

        if ($statusChanged) {
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

        // Fired last, so the order and its appointments already carry the new
        // status by the time the placeholders are resolved. Refunds are not
        // handled here — OrderService::processRefund() owns that e-mail.
        if ($statusChanged) {
            self::notifyStatusChange(
                (int) $payment->customer_id,
                (int) $payment->order_id,
                $status,
                [
                    'amount'         => $payment->amount,
                    'transaction_id' => $payment->transaction_id,
                    'payment_method' => $payment->payment_method,
                ]
            );
        }

        return $payment;
    }

    /**
     * Send the customer + admin e-mail for a payment that has just become paid
     * or failed.
     *
     * Public because two other paths change a payment status without going
     * through applyStatus() — the admin appointment form
     * (SaveAppointment::syncRelatedPaymentStatus) and the admin order form
     * (OrderService::syncPaymentStatus) — and they must raise the same e-mail.
     * Callers are responsible for only calling this when the status actually
     * changed; a status that maps to no e-mail (unpaid, refunded, …) is ignored
     * here. Refunds are owned by OrderService::processRefund().
     *
     * @param int $customerId Customer the payment belongs to.
     * @param int $orderId Order the payment belongs to.
     * @param string $status The new payment status.
     * @param array<string, mixed> $payment Optional amount / transaction_id /
     *        payment_method for the placeholders; omitted values fall back to
     *        the order's own.
     * @return void
     */
    public static function notifyStatusChange(int $customerId, int $orderId, string $status, array $payment = []): void
    {
        $events = [
            PaymentModel::STATUS_PAID   => 'payment_received',
            PaymentModel::STATUS_FAILED => 'payment_failed',
        ];

        if (!isset($events[$status])) {
            return;
        }

        do_action(
            'rox_appointment_booking_email_event',
            $events[$status],
            [
                'customer_id' => $customerId,
                'order_id'    => $orderId,
                'payment'     => $payment,
            ]
        );
    }
}
