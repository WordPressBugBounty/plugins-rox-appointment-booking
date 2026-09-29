<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Customer;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractDeleteAbility;

/**
 * Class DeleteCustomer
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Customer
 * @description Permanently deletes a customer.
 */
class DeleteCustomer extends AbstractDeleteAbility
{
    protected string $name = 'delete-customer';

    protected string $capability = 'customer.delete';

    protected string $route = 'customer';

    public function label(): string
    {
        return __('Delete Customer', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Permanently deletes a customer. Refused while the customer still has appointments. This cannot be undone, so confirm with the user first.';
    }
}
