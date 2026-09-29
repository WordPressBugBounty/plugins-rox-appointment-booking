<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Customer;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractAbility;

/**
 * Class GetCustomer
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Customer
 * @description Returns one customer's profile.
 */
class GetCustomer extends AbstractAbility
{
    protected string $name = 'get-customer';

    protected string $capability = 'customer.view';

    public function label(): string
    {
        return __('Get Customer', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Returns one customer profile: name, e-mail, phone, gender, date of birth, whether they may log in, last appointment date, total spent and amount due. Use search-customers or get-customer-by-email to find the id.';
    }

    protected function properties(): array
    {
        return $this->idProperty('Customer id.');
    }

    protected function required(): array
    {
        return ['id'];
    }

    protected function run(array $input)
    {
        $result = $this->dispatch('GET', 'customer/' . absint($input['id'] ?? 0));

        return is_wp_error($result) ? $result : (array) $result['data'];
    }
}
