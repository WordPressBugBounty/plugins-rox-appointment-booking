<?php

namespace RoxAppointmentBooking\Modules\Customer\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Supports\Traits\RoxAppointmentBookingFilter;
use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;
use RoxAppointmentBooking\Modules\Customer\Services\CustomerService;

/**
 * Class GetList
 * 
 * @package RoxAppointmentBooking\Modules\Customer\REST
 * @description Handles retrieving list of customers and single customer via REST API.
 */
class GetCustomer extends AbstractREST
{
    use RoxAppointmentBookingFilter;

    /**
     * Whether this REST endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * REST route for listing and retrieving customers.
     *
     * @var string
     */
    public static string $route = '/customer(?:/(?P<id>\d+))?';

    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/customer/';

    /**
     * Get the HTTP methods allowed for this route.
     * 
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'GET';
    }

    /**
     * Get fields that can be searched by the filter trait.
     *
     * @return array
     */
    protected function getSearchableFields(): array
    {
        return ['id', 'first_name', 'last_name', 'email', 'phone', 'gender', 'internal_notes','dob'];
    }

    /**
     * Check whether the current user can access this endpoint.
     * 
     * @param WP_REST_Request $request REST request instance.
     * @return bool
     */
    public function permissionCheck(WP_REST_Request $request): bool
    {
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return false;
        }

        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return false;
        }

        return true;
    }

    /**
     * Format customer data for REST responses.
     * 
     * @param CustomerModel $customer Customer model instance.
     * @param bool $detailed Whether to include detailed information.
     * @param string $mode Response mode.
     * @return array
     */
    protected function getCustomerData(CustomerModel $customer, bool $detailed = false, string $mode = 'default'): array
    {
        if ($mode === 'list') {
            return [
                'value' => $customer->getID(),
                'label' => $customer->getFullName(),
                'email' => $customer->email,
            ];
        }
        if ($detailed) {
           return [
                'id' => $customer->getID(),
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'full_name' => $customer->getFullName(),
                'email' => $customer->email,
                'thumbnail_id' => $customer->thumbnail_id,
                'phone' => $customer->phone,
                'last_appointment' => CustomerService::getLastAppointmentDate($customer->getID()),
                'gender' => $customer->gender,
                'dob' => $customer->dob ? gmdate('Y-m-d', strtotime($customer->dob)) : null,
                'allow_to_login' => (bool)$customer->allow_to_login,
                'send_notifications' => $customer->send_notifications ? ["1"] : [],
                'internal_notes' => $customer->internal_notes ?? null,
                'total_spent' => CustomerService::getTotalSpent($customer->getID()),
                'due' => CustomerService::getDueAmount($customer->getID()),
           ];
        }

        return [
            'id' => $customer->getID(),
            'customer' => [
                'name' => $customer->getFullName(),
                'email' => $customer->email,
                'thumbnail' => $customer->thumbnail_id ? wp_get_attachment_url($customer->thumbnail_id) : "",
            ],
            'phone' => $customer->phone,
            'last_appointment' => CustomerService::getLastAppointmentDate($customer->getID()),
            'gender' => $customer->gender,
            'dob' => $customer->dob ? gmdate('d/m/Y', strtotime($customer->dob)) : null,
            'total_appointments' => CustomerService::getTotalAppointments($customer->getID()),
            'total_spent' => CustomerService::getTotalSpent($customer->getID()),
            'due' => CustomerService::getDueAmount($customer->getID()),
        ];
    }

    /**
     * Handle the REST API request.
     * 
     * @param WP_REST_Request $request REST request instance.
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = $request->get_param('id');

        // Handle single customer request
        if ($id) {
            $customer = CustomerModel::find($id);
            if (!$customer) {
                return rox_appointment_booking_rest_response(
                    data : null,
                    code : 404,
                    message : esc_html__('Customer not found', 'rox-appointment-booking'),
                    headers : ['status' => 404]
                );
            }

            return rox_appointment_booking_rest_response(
                data : $this->getCustomerData($customer, true),
                message : esc_html__('Customer retrieved successfully', 'rox-appointment-booking')
            );
        }

        // Handle customer list request
        $pagination = $this->getPaginationParams($request);
        $mode = $request->get_param('mode') ?? 'default';
        
        $query = CustomerModel::query();
        $query = $this->applyFilters($request, $query);
        
        $total = $query->count();
        $customers = $query->offset(($pagination['page'] - 1) * $pagination['per_page'])
                         ->limit($pagination['per_page'])
                         ->orderBy('created_at', 'DESC')
                         ->get();
        $data = [];
        
        if ($mode === 'list') {
            foreach ($customers as $customer) {
                $data[] = $this->getCustomerData($customer, false, $mode);
            }
        } else {
            foreach ($customers as $customer) {
                $data[] = $this->getCustomerData($customer);
            }
        }
        
        return rox_appointment_booking_rest_response(
            data : $data,
            message : esc_html__('Customers retrieved successfully', 'rox-appointment-booking'),
            options : $this->buildPaginationMeta($total, $pagination['page'], $pagination['per_page'])
        );
    }
}
