<?php

namespace RoxAppointmentBooking\Modules\Customer\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;

/**
 * Class DeleteCustomer
 *
 * @package RoxAppointmentBooking\Modules\Customer\REST
 * @description Handles deleting customer via REST API.
 */
class DeleteCustomer extends AbstractREST
{
    /**
     * Whether this REST endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * REST route for deleting customers.
     *
     * @var string
     */
    public static string $route = '/customer(?:/(?P<id>\d+))?';

    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/customer/{id}';

    /**
     * Get the HTTP methods allowed for this route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'DELETE';
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
     * Handle the REST API request.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $ids = $request->get_param('ids');
        $id = $request->get_param('id');
        $deleted = [];
        $errors = [];
        
        if ($ids && is_array($ids)) {
            foreach ($ids as $singleId) {
                $customer = CustomerModel::find($singleId);
                if ($customer) {
                    $validation_error = $this->validateCustomerCanBeDeleted($singleId);
                    if ($validation_error) {
                        $errors[] = $validation_error;
                        continue;
                    }
                    
                    try {
                        $customer->delete();
                        $deleted[] = $singleId;
                    } catch (\Exception $e) {
                        $errors[] = $e->getMessage();
                    }
                } else {
                    $errors[] = esc_html__('Customer not found', 'rox-appointment-booking');
                }
            }
            $status = empty($errors) ? 200 : 500;
            return rox_appointment_booking_rest_response(
                data : ['deleted' => $deleted, 'errors' => $errors],
                code : $status,
                message : empty($errors) ? esc_html__('Customers deleted successfully', 'rox-appointment-booking') : esc_html__('Some customers could not be deleted', 'rox-appointment-booking'),
                headers : ['status' => $status]
            );
        }
        
        // Single delete fallback
        $customer = CustomerModel::find($id);
        if (!$customer) {
            return rox_appointment_booking_rest_response(
                data : null,
                code : 404,
                message : esc_html__('Customer not found', 'rox-appointment-booking'),
                headers : ['status' => 404]
            );
        }
        
        $validation_error = $this->validateCustomerCanBeDeleted($id);
        if ($validation_error) {
            return rox_appointment_booking_rest_response(
                data : null,
                code : 400,
                message : $validation_error,
                headers : ['status' => 400]
            );
        }
        
        try {
            $customer->delete();
            return rox_appointment_booking_rest_response(
                data : null,
                message : esc_html__('Customer deleted successfully', 'rox-appointment-booking'),
                code: 200
            );
        } catch (\Exception $e) {
            return rox_appointment_booking_rest_response(
                data : null,
                code : 500,
                message : $e->getMessage(),
                headers : ['status' => 500]
            );
        }
    }

    /**
     * Validate whether a customer can be deleted.
     *
     * @param int $id Customer ID.
     * @return string|null Error message when deletion is blocked, otherwise null.
     */
    private function validateCustomerCanBeDeleted(int $id): ?string
    {
        // Check if customer has any appointments
        $appointments = AppointmentModel::query()
            ->where('customer_id', $id)
            ->get();

        if (count($appointments) > 0) {
            return esc_html__('This customer has some appointments scheduled. Please remove them first', 'rox-appointment-booking');
        }

        return null; // Customer can be deleted
    }
}
