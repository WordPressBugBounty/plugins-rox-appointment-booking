<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Order;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractAbility;

/**
 * Class ListOrders
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Order
 * @description Lists orders with filters and pagination.
 */
class ListOrders extends AbstractAbility
{
    protected string $name = 'list-orders';

    protected string $capability = 'order.view';

    public function label(): string
    {
        return __('List Orders', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Lists orders (the payment side of one or more bookings), newest first, with order number, customer, total, payment method, payment status and order status. Amounts are in the site currency. Filter by status, customer or creation date range (Y-m-d).';
    }

    protected function properties(): array
    {
        $statuses = array_values(array_filter(array_column(rox_appointment_booking_order_statuses(), 'value')));

        return array_merge([
            'order_status'   => ['type' => 'string', 'enum' => $statuses, 'description' => 'Only orders with this status.'],
            'payment_status' => ['type' => 'string', 'description' => 'Only orders with this payment status, e.g. paid, pending, partially_paid, refunded.'],
            'payment_method' => ['type' => 'string', 'description' => 'Only orders paid this way, e.g. stripe, paypal, pay_later.'],
            'customer_id'    => ['type' => 'integer', 'minimum' => 1, 'description' => 'Only orders of this customer.'],
            'date_from'      => ['type' => 'string', 'format' => 'date', 'description' => 'Created on or after (Y-m-d).'],
            'date_to'        => ['type' => 'string', 'format' => 'date', 'description' => 'Created on or before (Y-m-d).'],
            'search'         => ['type' => 'string', 'description' => 'Match on order id, currency, payment method or status.'],
        ], $this->paginationProperties());
    }

    protected function run(array $input)
    {
        $paging = $this->pageParams($input);
        $params = array_merge($paging, array_filter([
            'order_status'   => sanitize_key($input['order_status'] ?? ''),
            'payment_status' => sanitize_key($input['payment_status'] ?? ''),
            'payment_method' => sanitize_key($input['payment_method'] ?? ''),
            'customer_id'    => absint($input['customer_id'] ?? 0),
            'search'         => sanitize_text_field($input['search'] ?? ''),
        ]));

        $from = $this->date($input['date_from'] ?? '');
        $to   = $this->date($input['date_to'] ?? '');
        if ($from || $to) {
            $params['created_at'] = wp_json_encode([$from ?: '1970-01-01', $to ?: '9999-12-31']);
        }

        $result = $this->dispatch('GET', 'order', $params);

        return is_wp_error($result) ? $result : $this->paged($result, $paging);
    }
}
