<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Customer;

defined('ABSPATH') || exit;

use WP_Error;

/**
 * Class GetCustomerByEmail
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Customer
 * @description Returns the customer with an exact e-mail address.
 */
class GetCustomerByEmail extends GetCustomer
{
    protected string $name = 'get-customer-by-email';

    public function label(): string
    {
        return __('Get Customer by E-mail', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Returns the full profile of the customer with exactly this e-mail address, or a not-found error. Use it to check whether someone is already a customer before creating one.';
    }

    protected function properties(): array
    {
        return [
            'email' => ['type' => 'string', 'format' => 'email', 'description' => 'Exact e-mail address.'],
        ];
    }

    protected function required(): array
    {
        return ['email'];
    }

    protected function run(array $input)
    {
        $email = sanitize_email($input['email'] ?? '');
        if (!$email) {
            return new WP_Error('invalid_email', 'A valid e-mail address is required.', ['status' => 400]);
        }

        $result = $this->dispatch('GET', 'customer', ['email' => $email, 'per_page' => 20]);
        if (is_wp_error($result)) {
            return $result;
        }

        // The list filter matches with LIKE, so keep only the exact address.
        $match = 0;
        foreach ((array) $result['data'] as $row) {
            if (is_array($row) && strcasecmp((string) ($row['customer']['email'] ?? $row['email'] ?? ''), $email) === 0) {
                $match = (int) ($row['id'] ?? 0);
                break;
            }
        }
        if (!$match) {
            return new WP_Error('not_found', 'No customer has this e-mail address.', ['status' => 404]);
        }

        return parent::run(['id' => $match]);
    }
}
