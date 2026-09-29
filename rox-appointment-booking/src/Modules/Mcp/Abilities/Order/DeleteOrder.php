<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Order;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractDeleteAbility;

/**
 * Class DeleteOrder
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Order
 * @description Permanently deletes an order with its appointments and payments.
 */
class DeleteOrder extends AbstractDeleteAbility
{
    protected string $name = 'delete-order';

    protected string $capability = 'order.delete';

    protected string $route = 'order';

    public function label(): string
    {
        return __('Delete Order', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Permanently deletes an order together with every appointment it covers and its payment records. This is the only way to remove an appointment entirely; to keep the history, use cancel-appointment instead. No money is refunded and no e-mail is sent. This cannot be undone, so confirm with the user first and tell them which appointments go with it (see get-order).';
    }
}
