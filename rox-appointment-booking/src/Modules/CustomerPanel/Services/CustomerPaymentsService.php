<?php

namespace RoxAppointmentBooking\Modules\CustomerPanel\Services;

use RoxAppointmentBooking\Modules\Service\Data\ServiceModel;
use RoxAppointmentBooking\Modules\Order\Data\OrderModel;
use RoxAppointmentBooking\Modules\Payment\Data\PaymentModel;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Assembles the logged-in customer's payments into the exact shape the Customer
 * Panel's Payment History view (PaymentsView / PaymentStatCards / PaymentRow)
 * already consumes — replacing the Phase B mock data. Everything is scoped to a
 * single customer id (resolved server-side by the caller); this service never
 * reads a client-supplied id.
 */
class CustomerPaymentsService
{
    public static $loadable = true;

    /** @var string Currency symbol for price formatting. */
    private string $currency;

    public function __construct()
    {
        $code = rox_appointment_booking_payment_settings('stripe_currency', 'USD');
        $this->currency = function_exists('rox_appointment_booking__get_currency_symbol')
            ? rox_appointment_booking__get_currency_symbol($code)
            : '$';
    }

    /**
     * Payment History payload for a customer:
     * { stats:[{label,value,color?}], transactions:[{id,status,title,meta,amount,sign,reference|payable}] }
     *
     * @param int $customerId
     * @return array
     */
    public function getForCustomer(int $customerId): array
    {
        $payments = PaymentModel::query()
            ->where('customer_id', $customerId)
            ->orderBy('payment_time', 'DESC')
            ->get()
            ->toArray();

        $totalPaid = 0.0;
        $outstanding = 0.0;
        $refunded = 0.0;
        $transactions = [];

        foreach ($payments as $payment) {
            $status = strtolower($payment['status'] ?? 'unpaid');
            $amount = (float) ($payment['amount'] ?? 0);

            if ($status === 'paid') {
                $totalPaid += $amount;
            } elseif ($status === 'refunded') {
                $refunded += $amount;
            } elseif ($status === 'unpaid') {
                $outstanding += $amount;
            }

            $transactions[] = $this->mapPayment($payment, $status, $amount);
        }

        return [
            'stats' => [
                ['label' => esc_html__('Total Paid', 'rox-appointment-booking'), 'value' => $this->money($totalPaid)],
                ['label' => esc_html__('Outstanding', 'rox-appointment-booking'), 'value' => $this->money($outstanding), 'color' => 'var(--red-text)'],
                ['label' => esc_html__('Refunded', 'rox-appointment-booking'), 'value' => $this->money($refunded), 'color' => 'var(--teal-text)'],
            ],
            'transactions' => $transactions,
        ];
    }

    /**
     * Map one payment row to a transaction object.
     *
     * @param array  $payment
     * @param string $status  Lowercased payment status.
     * @param float  $amount
     * @return array
     */
    private function mapPayment(array $payment, string $status, float $amount): array
    {
        $order = $this->getOrder($payment['order_id'] ?? null);
        $title = $this->transactionTitle($order, $status);
        $method = $this->methodLabel($payment['payment_method'] ?? '', $status);
        $when = !empty($payment['payment_time']) ? $payment['payment_time'] : ($payment['created_at'] ?? '');
        $dateLabel = $when ? gmdate('F d, Y', strtotime($when)) : '';

        [$uiStatus, $sign, $payable] = $this->statusMeta($status);

        return array_filter([
            'id' => 't' . (int) ($payment['id'] ?? 0),
            // Order id the Pay Now action settles (only meaningful for a payable row).
            'order_id' => $payable ? (int) ($payment['order_id'] ?? 0) : null,
            'status' => $uiStatus,
            'title' => $title,
            'meta' => trim($method . ($dateLabel ? ' · ' . $dateLabel : '')),
            'amount' => $this->money($amount),
            'sign' => $sign,
            'reference' => $payable ? null : (string) ($payment['transaction_id'] ?? ''),
            'payable' => $payable ? true : null,
        ], static fn ($value) => $value !== null);
    }

    /**
     * "{Service} — refunded/Pending" style title, derived from the order's first
     * booking. Falls back to a generic label when the service can't be resolved.
     *
     * @param OrderModel|null $order
     * @param string          $status
     * @return string
     */
    private function transactionTitle(?OrderModel $order, string $status): string
    {
        $serviceTitle = esc_html__('Booking Payment', 'rox-appointment-booking');

        if ($order) {
            $bookingIds = $order->getBookingIds();
            $appointment = !empty($bookingIds) ? AppointmentModel::find((int) $bookingIds[0]) : null;
            if ($appointment && !empty($appointment->service_id)) {
                $service = ServiceModel::find((int) $appointment->service_id);
                if ($service && !empty($service->title)) {
                    $serviceTitle = $service->title;
                }
            }
        }

        if ($status === 'refunded') {
            return $serviceTitle . ' — ' . esc_html__('refunded', 'rox-appointment-booking');
        }
        if ($status === 'unpaid') {
            return $serviceTitle . ' — ' . esc_html__('Pending', 'rox-appointment-booking');
        }

        return $serviceTitle;
    }

    /**
     * @return array{0:string,1:string,2:bool} [uiStatus, sign, payable]
     */
    private function statusMeta(string $status): array
    {
        switch ($status) {
            case 'paid':     return ['paid', '+', false];
            case 'refunded': return ['refunded', '-', false];
            case 'failed':   return ['failed', '', false];
            default:         return ['pending', '', true]; // unpaid
        }
    }

    /**
     * Humanized payment-method label. For an unpaid row the method is not settled
     * yet, so show an "awaiting payment" note (matches the mockup pending row).
     *
     * @param string $method
     * @param string $status
     * @return string
     */
    private function methodLabel(string $method, string $status): string
    {
        if ($status === 'unpaid') {
            return esc_html__('Awaiting payment', 'rox-appointment-booking');
        }
        if ($status === 'refunded') {
            return esc_html__('Refund to original method', 'rox-appointment-booking');
        }

        switch (strtolower($method)) {
            case 'credit':
            case 'card':
            case 'stripe':   return esc_html__('Credit Card', 'rox-appointment-booking');
            case 'cash':     return esc_html__('Cash', 'rox-appointment-booking');
            case 'later':
            case 'pay_later': return esc_html__('Pay Later', 'rox-appointment-booking');
            default:         return $method !== '' ? ucfirst($method) : esc_html__('Payment', 'rox-appointment-booking');
        }
    }

    private function money(float $amount): string
    {
        return $this->currency . number_format($amount, 2);
    }

    private function getOrder($orderId): ?OrderModel
    {
        if (empty($orderId)) {
            return null;
        }
        return OrderModel::find((int) $orderId);
    }
}
