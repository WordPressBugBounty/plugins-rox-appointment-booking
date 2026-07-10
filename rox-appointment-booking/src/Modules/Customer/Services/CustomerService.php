<?php

namespace RoxAppointmentBooking\Modules\Customer\Services;

use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;

/**
 * Class CustomerService
 * 
 * @package RoxAppointmentBooking\Modules\Customer\Services
 * @description Handles customer-related business logic.
 */
class CustomerService
{
    /**
     * Get the total amount spent by a customer.
     *
     * @param int $customerId Customer ID.
     * @return float Total spent.
     */
    public static function getTotalSpent(int $customerId): float
    {
        // Assuming there is an OrderModel with 'customer_id' and 'total' fields
        if (!class_exists('RoxAppointmentBooking\\Modules\\Order\\Data\\OrderModel')) {
            return 0.0;
        }
        $orderModelClass = 'RoxAppointmentBooking\\Modules\\Order\\Data\\OrderModel';
        $orders = $orderModelClass::query()->where('customer_id', $customerId)->get();
        $total = 0.0;
        foreach ($orders as $order) {
            $total += (float)($order->subtotal ?? 0);
        }
        return $total;
    }

    /**
     * Whether this service should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * Get all customers with optional filtering.
     * 
     * @param array $filters Filter parameters.
     * @param int $page Page number.
     * @param int $per_page Items per page.
     * @return array Array containing customers and pagination info.
     */
    public function getCustomers(array $filters = [], int $page = 1, int $per_page = 10): array
    {
        $query = CustomerModel::query();

        // Apply filters
        if (!empty($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('full_name', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('email', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('phone', 'LIKE', "%{$filters['search']}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $total = $query->count();
        
        $customers = $query->offset(($page - 1) * $per_page)
                         ->limit($per_page)
                         ->orderBy('created_at', 'DESC')
                         ->get();

        return [
            'items' => $customers,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ];
    }

    /**
     * Create or update a customer.
     * 
     * @param array $data Customer data.
     * @param int|null $id Customer ID for updates.
     * @return CustomerModel
     * @throws \Exception If validation fails.
     */
    public function saveCustomer(array $data, ?int $id = null): CustomerModel
    {
        // Validate required fields
        $required = ['first_name', 'last_name', 'email'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                // translators: %s = field name
                throw new \Exception(sprintf(esc_html__('%s is required', 'rox-appointment-booking'), esc_html($field)));
            }
        }

        // Validate email format
        if (!is_email($data['email'])) {
            throw new \Exception(esc_html__('Invalid email address', 'rox-appointment-booking'));
        }

        // Check for duplicate email
        $existing = CustomerModel::query()
            ->where('email', $data['email'])
            ->when($id, function($q) use ($id) {
                $q->where('id', '!=', $id);
            })
            ->first();

        if ($existing) {
            throw new \Exception(esc_html__('A customer with this email already exists', 'rox-appointment-booking'));
        }

        // Get or create customer
        $customer = $id ? CustomerModel::find($id) : new CustomerModel();
        if ($id && !$customer) {
            throw new \Exception(esc_html__('Customer not found', 'rox-appointment-booking'));
        }

        // Fill and save data
        $customer->fill($data);
        $customer->save();

        return $customer;
    }

    /**
     * Delete a customer.
     * 
     * @param int $id Customer ID.
     * @return bool
     * @throws \Exception If customer not found or deletion fails.
     */
    public function deleteCustomer(int $id): bool
    {
        $customer = CustomerModel::find($id);
        if (!$customer) {
            throw new \Exception(esc_html__('Customer not found', 'rox-appointment-booking'));
        }

        // TODO: Check for existing appointments before deleting
        return $customer->delete();
    }

    /**
     * Get customer by ID.
     * 
     * @param int $id Customer ID.
     * @return CustomerModel|null
     */
    public function getCustomer(int $id): ?CustomerModel
    {
        return CustomerModel::find($id);
    }

    /**
     * Check whether a customer has any appointments.
     * 
     * @param int $customerId Customer ID.
     * @return bool True if customer has appointments, false otherwise.
     */
    public static function hasAppointments( int $customerId ): bool
    {
        return AppointmentModel::query()->where('customer_id', $customerId)->exists();
    }

    /**
     * Get the last appointment date for a customer.
     * 
     * @param int $customerId Customer ID.
     * @return string|null Last appointment date in 'd/m/Y' format or null if none.
     */
    public static function getLastAppointmentDate( int $customerId )
    {
        $appointment = AppointmentModel::query()->where('customer_id', $customerId)->orderBy('start_time', 'DESC')->first();
        return $appointment ? gmdate('d/m/Y', strtotime($appointment->start_time)) : null;
    }

    /**
     * Get the total number of appointments for a customer.
     * 
     * @param int $customerId Customer ID.
     * @return int Total number of appointments.
     */
    public static function getTotalAppointments( int $customerId ): int
    {
        return AppointmentModel::query()->where('customer_id', $customerId)->count();
    }

    /**
     * Get the due amount for a customer.
     *
     * @param int $customerId Customer ID.
     * @return float Due amount.
     */
    public static function getDueAmount(int $customerId): float
    {
        if (!class_exists('RoxAppointmentBooking\\Modules\\Order\\Data\\OrderModel')) {
            return 0.0;
        }
        $orderModelClass = 'RoxAppointmentBooking\\Modules\\Order\\Data\\OrderModel';
        $orders = $orderModelClass::query()->where('customer_id', $customerId)->get();
        $due = 0.0;
        foreach ($orders as $order) {
            if ($order->payment_status !== 'completed' && $order->payment_status !== 'paid') {
                $due += (float)($order->total_amount ?? 0);
            }
        }
        return $due;
    }
}
