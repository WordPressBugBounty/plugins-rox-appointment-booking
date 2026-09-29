<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Customer;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\AbstractWriteAbility;

/**
 * Class CreateCustomer
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Customer
 * @description Adds a customer.
 */
class CreateCustomer extends AbstractWriteAbility
{
    protected string $name = 'create-customer';

    protected string $capability = 'customer.create';

    public function label(): string
    {
        return __('Create Customer', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return 'Adds a customer. The e-mail must not already belong to a customer or agent (check with get-customer-by-email first). With allow_to_login true a WordPress account is created and its login details are e-mailed to the customer; by default no account is made. Returns the new customer.';
    }

    protected function properties(): array
    {
        return $this->fields();
    }

    protected function required(): array
    {
        return ['first_name', 'last_name', 'email'];
    }

    protected function run(array $input)
    {
        $payload = $this->payload($input);
        $payload['allow_to_login'] = rest_sanitize_boolean($input['allow_to_login'] ?? false);

        $result = $this->dispatch('POST', 'customer', $payload);

        return is_wp_error($result) ? $result : (array) $result['data'];
    }

    /**
     * Editable customer fields.
     *
     * @return array
     */
    protected function fields(): array
    {
        return [
            'first_name'     => ['type' => 'string', 'minLength' => 1, 'description' => 'First name.'],
            'last_name'      => ['type' => 'string', 'minLength' => 1, 'description' => 'Last name.'],
            'email'          => ['type' => 'string', 'format' => 'email', 'description' => 'E-mail address.'],
            'phone'          => ['type' => 'string', 'description' => 'Phone number with country code.'],
            'gender'         => ['type' => 'string', 'enum' => ['male', 'female'], 'description' => 'Gender.'],
            'dob'            => ['type' => 'string', 'format' => 'date', 'description' => 'Date of birth (Y-m-d).'],
            'allow_to_login' => ['type' => 'boolean', 'description' => 'Give the customer a WordPress login (e-mails credentials).'],
        ];
    }

    /**
     * Sanitised customer fields present in the input.
     *
     * @param array $input
     * @return array
     */
    protected function payload(array $input): array
    {
        $payload = $this->texts($input, ['first_name', 'last_name', 'phone', 'gender']);

        if (array_key_exists('email', $input)) {
            $payload['email'] = sanitize_email($input['email']);
        }
        if (array_key_exists('dob', $input)) {
            $payload['dob'] = $this->date($input['dob']);
        }

        return $payload;
    }
}
