<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities\Customer;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\Abilities\Appointment\ListAppointments;

/**
 * Class GetCustomerAppointments
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities\Customer
 * @description Lists one customer's appointments.
 */
class GetCustomerAppointments extends ListAppointments
{
    protected string $name = 'get-customer-appointments';

    protected string $capability = 'customer.view';

    public function label(): string
    {
        return __('Get Customer Appointments', 'rox-appointment-booking');
    }

    public function description(): string
    {
        return "Lists one customer's appointments, newest booked first, with the same filters and summary fields as list-appointments.";
    }

    protected function required(): array
    {
        return ['customer_id'];
    }
}
