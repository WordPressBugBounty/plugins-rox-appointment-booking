<?php

namespace RoxAppointmentBooking\Modules\Order\Services;

use RoxAppointmentBooking\Modules\Order\Data\OrderModel;
use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\Payment\Data\PaymentModel;
use RoxAppointmentBooking\Modules\Notification\Services\NotificationService;

/**
 * Class OrderService
 *
 * @package RoxAppointmentBooking\Modules\Order\Services
 * @description Handles order-related business logic.
 */
class OrderService
{
    /**
     * Whether this service should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * Get orders with filters and pagination.
     *
     * @param array $filters Filter parameters.
     * @param int $page Page number.
     * @param int $per_page Items per page.
     * @return array
     */
    public function getOrders(array $filters = [], int $page = 1, int $per_page = 10): array
    {
        $query = OrderModel::query();

        if (!empty($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('id', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('payment_transaction_id', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('coupon_code', 'LIKE', "%{$filters['search']}%");
            });
        }

        if (!empty($filters['order_status'])) {
            $query->where('order_status', $filters['order_status']);
        }

        if (!empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('order_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('order_date', '<=', $filters['date_to'] . ' 23:59:59');
        }

        $total = $query->count();
        
        $orders = $query->offset(($page - 1) * $per_page)
                       ->limit($per_page)
                       ->orderBy('created_at', 'DESC')
                       ->get();

        return [
            'items' => $orders,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ];
    }

    /**
     * Save an order.
     *
     * @param array $data Order data.
     * @param int|null $id Order ID for updates.
     * @param string $createdAt Created/order date fallback.
     * @return OrderModel
     * @throws \Exception If validation fails.
     */
    public function saveOrder(array $data, ?int $id = null, string $createdAt = ''): OrderModel
    {
        $orderDate = $createdAt ?: gmdate('Y-m-d H:i:s');

        if (!$id) {
            $required = ['customer_id', 'total_amount'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    // translators: %s = field name
                    throw new \Exception(sprintf(esc_html__('%s is required', 'rox-appointment-booking'), esc_html($field)));
                }
            }
        }

        if (!empty($data['customer_id'])) {
            $customer = CustomerModel::find($data['customer_id']);
            if (!$customer) {
                throw new \Exception(esc_html__('Customer not found', 'rox-appointment-booking'));
            }
        }

        if (!empty($data['coupon_id'])) {
            $couponExists = apply_filters('rox_appointment_booking_coupon_exists', false, (int) $data['coupon_id']);
            if (!$couponExists) {
                throw new \Exception(esc_html__('Coupon not found', 'rox-appointment-booking'));
            }
        }

        $order = $id ? OrderModel::find($id) : new OrderModel();
        if ($id && !$order) {
            throw new \Exception(esc_html__('Order not found', 'rox-appointment-booking'));
        }

        $oldOrderStatus = $order->order_status ?? null;

        if (!$id) {
            $paymentSettings = rox_appointment_booking_payment_settings();
            $data = array_merge([
                'currency'         => $paymentSettings['payment_currency'] ?? 'USD',
                'payment_status'   => $data['payment_status'] ?? $paymentSettings['default_payment_status'],
                'order_status'     => $data['order_status'] ?? 'pending_payment',
                'order_date'       => $data['order_date'] ?? $orderDate,
                'subtotal'         => $data['total_amount'] ?? 0,
                'discount_amount'  => 0,
                'tax_amount'       => 0,
                'refund_amount'    => 0,
            ], $data);
        }

        if (isset($data['booking_ids']) && is_array($data['booking_ids'])) {
            $data['booking_ids'] = json_encode($data['booking_ids']);
        }

        $order->fill($data);
        $order->save();

        if ($id && isset($data['payment_status'])) {
            $this->syncPaymentStatus($order, $data['payment_status']);
        }

        if ($id && isset($data['order_status']) && $oldOrderStatus !== $data['order_status']) {
            NotificationService::createOrderStatusNotification($this->buildOrderNotificationData($order));
        }

        return $order;
    }

    /**
     * Sync payment status to related appointments and payments.
     *
     * @param OrderModel $order Order model instance.
     * @param string $paymentStatus Payment status.
     * @param string|null $orderDate Optional order date.
     * @return void
     */
    private function syncPaymentStatus(OrderModel $order, string $paymentStatus, ?string $orderDate = null): void
    {
        // Sync to associated appointments
        $bookingIds = $order->getBookingIds();
        if (!empty($bookingIds)) {
            AppointmentModel::whereIn('id', $bookingIds)
                ->update([
                    'payment_status' => $paymentStatus
                ]);
        }

        // Sync to associated payment record
        PaymentModel::where('order_id', $order->id)
            ->update([
                'status' => $paymentStatus
            ]);
    }

    /**
     * Delete an order and its related appointments and payments.
     *
     * @param int $id Order ID.
     * @return bool
     * @throws \Exception If order or related records cannot be deleted.
     */
    public function deleteOrder(int $id): bool
    {
        $order = OrderModel::find($id);
        if (!$order) {
            throw new \Exception(esc_html__('Order not found', 'rox-appointment-booking'));
        }

        $bookingIds = $order->getBookingIds();
        $deletedAppointments = [];

        if (!empty($bookingIds)) {
            foreach ($bookingIds as $appointmentId) {
                $appointment = AppointmentModel::find($appointmentId);
                if (!$appointment) {
                    foreach ($deletedAppointments as $restored) {
                        $restored->save();
                    }
                    throw new \Exception(esc_html__('Appointment not found during deletion', 'rox-appointment-booking'));
                }
                $deletedAppointments[] = clone $appointment;
                $appointment->delete();
            }
        }

        $paymentDeleted = PaymentModel::where('order_id', $order->id)->delete();

        if (!$paymentDeleted && PaymentModel::where('order_id', $order->id)->exists()) {
            foreach ($deletedAppointments as $restored) {
                $restored->save();
            }
            throw new \Exception(esc_html__('Failed to delete payment records', 'rox-appointment-booking'));
        }

        $result = $order->delete();

        if (!$result) {
            foreach ($deletedAppointments as $restored) {
                $restored->save();
            }
            throw new \Exception(esc_html__('Failed to delete order', 'rox-appointment-booking'));
        }

        return $result;
    }

    /**
     * Update order status.
     *
     * @param int $id Order ID.
     * @param string $status Order status.
     * @return OrderModel
     * @throws \Exception If the order or status is invalid.
     */
    public function updateOrderStatus(int $id, string $status): OrderModel
    {
        $order = OrderModel::find($id);
        if (!$order) {
            throw new \Exception(esc_html__('Order not found', 'rox-appointment-booking'));
        }

        $validStatuses = array_column(rox_appointment_booking_order_statuses(), 'value');
        if (!in_array($status, $validStatuses)) {
            throw new \Exception(esc_html__('Invalid order status', 'rox-appointment-booking'));
        }

        $oldStatus = $order->order_status;

        $order->order_status = $status;
        
        if ($status === 'completed' && empty($order->fulfillment_date)) {
            $order->fulfillment_date = gmdate('Y-m-d H:i:s');
        }
        
        if ($status === 'cancelled' && empty($order->cancellation_date)) {
            $order->cancellation_date = gmdate('Y-m-d H:i:s');
        }

        $order->save();

        if ($oldStatus !== $status) {
            NotificationService::createOrderStatusNotification($this->buildOrderNotificationData($order));
        }

        return $order;
    }

    /**
     * Process an order refund.
     *
     * @param int $id Order ID.
     * @param float $amount Refund amount.
     * @param string $reason Refund reason.
     * @return OrderModel
     * @throws \Exception If refund validation fails.
     */
    public function processRefund(int $id, float $amount, string $reason = ''): OrderModel
    {
        $order = OrderModel::find($id);
        if (!$order) {
            throw new \Exception(esc_html__('Order not found', 'rox-appointment-booking'));
        }

        if ($amount <= 0) {
            throw new \Exception(esc_html__('Refund amount must be greater than 0', 'rox-appointment-booking'));
        }

        if ($amount > $order->total_amount) {
            throw new \Exception(esc_html__('Refund amount cannot exceed order total', 'rox-appointment-booking'));
        }

        $order->refund_amount = $amount;
        $order->refund_date = gmdate('Y-m-d H:i:s');
        $order->refund_reason = $reason;
        $order->order_status = 'refunded';
        
        $order->save();

        NotificationService::createOrderStatusNotification($this->buildOrderNotificationData($order));

        return $order;
    }

    /**
     * Build notification data array from an OrderModel instance.
     *
     * @param OrderModel $order
     * @return array
     */
    private function buildOrderNotificationData(OrderModel $order): array
    {
        $customerName = '';
        if (!empty($order->customer_id)) {
            $customer = CustomerModel::find($order->customer_id);
            if ($customer) {
                $customerName = $customer->full_name ?? '';
            }
        }

        return [
            'admin_user_id' => get_current_user_id(),
            'order_id'      => $order->id ?? '',
            'order_status'  => $order->order_status ?? '',
            'customer_name' => $customerName,
            'total_amount'  => $order->total_amount ?? 0,
        ];
    }

    /**
     * Get order statistics.
     *
     * @param array $filters Filter parameters.
     * @return array
     */
    public function getOrderStats(array $filters = []): array
    {
        $query = OrderModel::query();

        if (!empty($filters['date_from'])) {
            $query->where('order_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('order_date', '<=', $filters['date_to'] . ' 23:59:59');
        }

        $totalOrders = (clone $query)->count();
        $totalRevenue = (clone $query)->sum('total_amount');
        $pendingOrders = (clone $query)->where('order_status', 'pending_payment')->count();
        $completedOrders = (clone $query)->where('order_status', 'completed')->count();
        $cancelledOrders = (clone $query)->where('order_status', 'cancelled')->count();
        $refundedAmount = (clone $query)->sum('refund_amount');

        return [
            'total_orders' => $totalOrders,
            'total_revenue' => (float) $totalRevenue,
            'pending_orders' => $pendingOrders,
            'completed_orders' => $completedOrders,
            'cancelled_orders' => $cancelledOrders,
            'refunded_amount' => (float) $refundedAmount,
            'average_order_value' => $totalOrders > 0 ? (float) $totalRevenue / $totalOrders : 0,
        ];
    }

    /**
     * Get order by ID.
     *
     * @param int $id Order ID.
     * @return OrderModel|null
     */
    public function getOrder(int $id): ?OrderModel
    {
        return OrderModel::find($id);
    }
}
