<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Customer;

defined('ABSPATH') || exit;

/**
 * Class SearchCustomers
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Customer
 * @description Finds customers by name, e-mail or phone.
 */
class SearchCustomers extends ListCustomers
{
    protected string $name = 'search-customers';

    public function label(): string
    {
        return __('Search Customers', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Finds customers whose name, e-mail or phone contains the query. Use it to resolve a customer the user mentions by name into a customer id before other calls.';
    }

    protected function properties(): array
    {
        return array_merge([
            'query' => ['type' => 'string', 'minLength' => 1, 'description' => 'Text to look for.'],
        ], $this->paginationProperties());
    }

    protected function required(): array
    {
        return ['query'];
    }

    protected function run(array $input)
    {
        $input['search'] = $input['query'] ?? '';

        return parent::run($input);
    }
}
