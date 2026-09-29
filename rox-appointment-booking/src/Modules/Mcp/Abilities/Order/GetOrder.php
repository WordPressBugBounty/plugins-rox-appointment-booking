<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Order;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractAbility;

/**
 * Class GetOrder
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Order
 * @description Returns one order in full.
 */
class GetOrder extends AbstractAbility
{
    protected string $name = 'get-order';

    protected string $capability = 'order.view';

    public function label(): string
    {
        return __('Get Order', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Returns one order in full: customer, the appointments it covers, line items, subtotal, discount and coupon, tax, deposit, total, amount paid and due, payment method, payment status, order status and transactions. Amounts are in the site currency.';
    }

    protected function properties(): array
    {
        return $this->idProperty('Order id.');
    }

    protected function required(): array
    {
        return ['id'];
    }

    protected function run(array $input)
    {
        $result = $this->dispatch('GET', 'order/' . absint($input['id'] ?? 0));

        return is_wp_error($result) ? $result : (array) $result['data'];
    }
}
