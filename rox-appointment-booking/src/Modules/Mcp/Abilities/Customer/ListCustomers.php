<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Customer;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractAbility;

/**
 * Class ListCustomers
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Customer
 * @description Lists customers with pagination.
 */
class ListCustomers extends AbstractAbility
{
    protected string $name = 'list-customers';

    protected string $capability = 'customer.view';

    public function label(): string
    {
        return __('List Customers', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Lists customers, newest first, with phone, last appointment date, number of appointments, total spent and amount due. Optional search matches name, e-mail or phone. Call get-customer for the full profile.';
    }

    protected function properties(): array
    {
        return array_merge([
            'search' => ['type' => 'string', 'description' => 'Match on name, e-mail or phone.'],
        ], $this->paginationProperties());
    }

    protected function run(array $input)
    {
        $paging = $this->pageParams($input);
        $params = $paging;

        if (!empty($input['search'])) {
            $params['search'] = sanitize_text_field($input['search']);
        }

        $result = $this->dispatch('GET', 'customer', $params);

        return is_wp_error($result) ? $result : $this->paged($result, $paging);
    }
}
