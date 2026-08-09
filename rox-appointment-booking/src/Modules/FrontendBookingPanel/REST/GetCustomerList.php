<?php

namespace RoxAppointmentBooking\Modules\FrontendBookingPanel\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;
use RoxAppointmentBooking\Supports\Security;

/**
 * Class GetCustomerList
 *
 * @package RoxAppointmentBooking\Modules\FrontendBookingPanel\REST
 * @description Customer options for the admin booking form's Customer select,
 * carrying each customer's contact fields (name, email, phone).
 *
 * Originally opened up to agents as well, so an agent's booking could be routed
 * through the public booking endpoint (which identifies the customer by email).
 * Agents can no longer create bookings, so that reason is gone — and this returns
 * the ENTIRE customer list with email + phone, which a read-only agent has no
 * business enumerating. Back to booking managers only.
 *
 * Note the misleading `/public/` prefix: despite the route it is NOT public and
 * never was — it always required a logged-in user.
 */
class GetCustomerList extends AbstractREST
{
    /**
     * Whether this REST endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * REST route for the agent-facing customer options list.
     *
     * @var string
     */
    public static string $route = '/public/customer-list';

    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/public/customer-list';

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
     * Only logged-in admins and agents may list customers. Unlike the sibling
     * `/public/*` panel endpoints this is NOT open — it exposes customer contact
     * data, so it is restricted to the booking staff who need it.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return bool
     */
    public function permissionCheck(WP_REST_Request $request): bool
    {
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return false;
        }

        if (!is_user_logged_in()) {
            return false;
        }

        return Security::canManageBookings();
    }

    /**
     * Handle the REST API request.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $customers = CustomerModel::query()
            ->orderBy('created_at', 'DESC')
            ->get();

        $data = [];
        foreach ($customers as $customer) {
            $data[] = [
                'value' => $customer->getID(),
                'label' => $customer->getFullName(),
                'email' => $customer->email,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'phone' => $customer->phone,
            ];
        }

        return rox_appointment_booking_rest_response(
            data: $data,
            message: ['Data Retrieved Successfully']
        );
    }
}
