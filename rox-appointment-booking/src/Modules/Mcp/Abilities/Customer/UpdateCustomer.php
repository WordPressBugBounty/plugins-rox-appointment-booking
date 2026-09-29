<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Customer;

defined('ABSPATH') || exit;

use WP_Error;
use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;

/**
 * Class UpdateCustomer
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Customer
 * @description Changes a customer's details.
 */
class UpdateCustomer extends CreateCustomer
{
    protected string $name = 'update-customer';

    protected string $capability = 'customer.edit';

    protected bool $idempotent = true;

    public function label(): string
    {
        return __('Update Customer', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return "Changes a customer's details. Only the fields you pass change. A linked WordPress account gets the new name and e-mail too. Setting allow_to_login true creates an account and e-mails its login details; false removes the link to the account (the account itself stays).";
    }

    protected function properties(): array
    {
        return array_merge($this->idProperty('Customer id.'), $this->fields());
    }

    protected function required(): array
    {
        return ['id'];
    }

    protected function run(array $input)
    {
        $id       = absint($input['id'] ?? 0);
        $readable = $this->readable('customer/' . $id);
        if (is_wp_error($readable)) {
            return $readable;
        }

        $stored = $this->stored(CustomerModel::class, $id);
        if (!$stored) {
            return new WP_Error('not_found', 'Customer not found.', ['status' => 404]);
        }

        $payload = array_merge($stored, $this->payload($input));
        $payload['allow_to_login'] = array_key_exists('allow_to_login', $input)
            ? rest_sanitize_boolean($input['allow_to_login'])
            : !empty($stored['allow_to_login']);
        unset($payload['wp_user_id']);

        $result = $this->dispatch('PUT', 'customer/' . $id, $payload);
        if (is_wp_error($result)) {
            return $result;
        }

        $fresh = $this->dispatch('GET', 'customer/' . $id);

        return is_wp_error($fresh) ? $fresh : (array) $fresh['data'];
    }
}
