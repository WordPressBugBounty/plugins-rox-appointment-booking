<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Order;

defined('ABSPATH') || exit;

use WP_Error;
use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractWriteAbility;

/**
 * Class ChangeOrderStatus
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Order
 * @description Sets an order's status.
 */
class ChangeOrderStatus extends AbstractWriteAbility
{
    protected string $name = 'change-order-status';

    protected string $capability = 'order.edit';

    protected bool $idempotent = true;

    public function label(): string
    {
        return __('Change Order Status', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Sets the status of an order (pending_payment, processing, on_hold, completed, cancelled, refunded, failed). This only changes the label and moves no money; to record a refund use refund-order.';
    }

    protected function properties(): array
    {
        return array_merge($this->idProperty('Order id.'), [
            'order_status' => ['type' => 'string', 'enum' => $this->statuses(), 'description' => 'New order status.'],
        ]);
    }

    protected function required(): array
    {
        return ['id', 'order_status'];
    }

    protected function run(array $input)
    {
        $id     = absint($input['id'] ?? 0);
        $status = (string) ($input['order_status'] ?? '');
        if (!in_array($status, $this->statuses(), true)) {
            return new WP_Error('invalid_status', 'Unknown order status.', ['status' => 400]);
        }

        $result = $this->dispatch('PUT', 'orders/' . $id, ['order_status' => $status]);
        if (is_wp_error($result)) {
            return $result;
        }

        $fresh = $this->dispatch('GET', 'order/' . $id);

        return is_wp_error($fresh) ? $fresh : (array) $fresh['data'];
    }

    /**
     * Order status enum values.
     *
     * @return string[]
     */
    private function statuses(): array
    {
        return array_values(array_filter(array_column(rox_appointment_booking_order_statuses(), 'value')));
    }
}
