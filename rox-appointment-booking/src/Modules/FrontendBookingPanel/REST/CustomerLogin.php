<?php
namespace RoxAppointmentBooking\Modules\FrontendBookingPanel\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;

/**
 * Class CustomerLogin
 *
 * @package RoxAppointmentBooking\Modules\FrontendBookingPanel\REST
 * @description Handles public customer login requests.
 */
class CustomerLogin extends AbstractREST
{
    /**
     * Whether this REST endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * REST route for customer login.
     *
     * @var string
     */
    public static string $route = '/public/customer/login';

    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/public/customer/login';

    /**
     * Get the HTTP methods allowed for this route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'POST';
    }

    /**
     * Check whether the current user can access this endpoint.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return bool
     */
    public function permissionCheck(WP_REST_Request $request): bool
    {
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
        $params = $request->get_params();
        
        if (empty($params['email']) || empty($params['password'])) {
            $json_params = $request->get_json_params();
            if ($json_params) {
                $params = array_merge($params, $json_params);
            }
        }
        
        if (empty($params['email'])) {
            return new WP_REST_Response([
                'success' => false,
                'code' => 400,
                'message' => 'Email is required',
                'data' => null
            ], 400);
        }
        
        if (empty($params['password'])) {
            return new WP_REST_Response([
                'success' => false,
                'code' => 400,
                'message' => 'Password is required',
                'data' => null
            ], 400);
        }

        if (!is_email($params['email'])) {
            return new WP_REST_Response([
                'success' => false,
                'code' => 400,
                'message' => 'Invalid email address',
                'data' => null
            ], 400);
        }

        try {
            $wp_user = get_user_by('email', $params['email']);
            
            if (!$wp_user || !wp_check_password($params['password'], $wp_user->user_pass, $wp_user->ID)) {
                return new WP_REST_Response([
                    'success' => false,
                    'code' => 401,
                    'message' => 'Invalid email or password',
                    'data' => null
                ], 401);
            }

            $customer = CustomerModel::query()
                ->where('email', $params['email'])
                ->orWhere('wp_user_id', $wp_user->ID)
                ->first();
            
            if (!$customer) {
                return new WP_REST_Response([
                    'success' => false,
                    'code' => 401,
                    'message' => 'Customer account not found',
                    'data' => null
                ], 401);
            }

            return new WP_REST_Response([
                'success' => true,
                'code' => 200,
                'message' => 'Login successful',
                'data' => [
                    'id' => $customer->getID(),
                    'email' => $customer->email,
                    'first_name' => $customer->first_name ?? '',
                    'last_name' => $customer->last_name ?? '',
                    'phone' => $customer->phone ?? null,
                    'wp_user_id' => $customer->wp_user_id,
                    'created_at' => $customer->created_at,
                    'updated_at' => $customer->updated_at
                ]
            ], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
