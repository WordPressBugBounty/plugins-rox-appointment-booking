<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Order;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractAbility;

/**
 * Class RefundOrder
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Order
 * @description Records a refund on an order.
 */
class RefundOrder extends AbstractAbility
{
    protected string $name = 'refund-order';

    protected string $capability = 'order.refund';

    protected bool $readOnly = false;

    protected bool $destructive = true;

    protected bool $idempotent = false;

    public function label(): string
    {
        return __('Refund Order', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Records a refund on an order, exactly like the dashboard refund: stores the amount and reason, sets the order to "refunded" and e-mails the customer a refund notice. It does NOT send money back through Stripe, PayPal or any other gateway; the refund must also be made in the gateway itself, so tell the user that. The amount (site currency) cannot exceed the order total. Confirm the amount with the user first.';
    }

    protected function properties(): array
    {
        return array_merge($this->idProperty('Order id.'), [
            'amount' => ['type' => 'number', 'exclusiveMinimum' => 0, 'description' => 'Amount refunded, in the site currency.'],
            'reason' => ['type' => 'string', 'description' => 'Why the refund was given.'],
        ]);
    }

    protected function required(): array
    {
        return ['id', 'amount'];
    }

    protected function run(array $input)
    {
        $id     = absint($input['id'] ?? 0);
        $result = $this->dispatch('POST', 'orders/' . $id . '/refund', [
            'amount' => (float) ($input['amount'] ?? 0),
            'reason' => sanitize_text_field((string) ($input['reason'] ?? '')),
        ]);
        if (is_wp_error($result)) {
            return $result;
        }

        $fresh = $this->dispatch('GET', 'order/' . $id);

        return is_wp_error($fresh) ? $fresh : (array) $fresh['data'];
    }
}
