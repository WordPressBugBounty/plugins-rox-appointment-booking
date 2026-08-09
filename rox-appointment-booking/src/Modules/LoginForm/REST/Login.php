<?php
namespace RoxAppointmentBooking\Modules\LoginForm\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;
use RoxAppointmentBooking\Modules\Agent\Data\AgentModel;

/**
 * Class Login
 *
 * @package RoxAppointmentBooking\Modules\LoginForm\REST
 * @description Role-agnostic public login for the standalone login form
 * (`[rox_appointment_login]` shortcode, block, Elementor widget). Authenticates a
 * WordPress account by email + password and then accepts it if it maps to either
 * a customer record or a login-enabled agent record. The booking panel keeps its
 * own customer-only `/public/customer/login` endpoint untouched.
 */
class Login extends AbstractREST
{
    /**
     * Whether this REST endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * REST route for the standalone login form.
     *
     * @var string
     */
    public static string $route = '/public/login';

    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/public/login';

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

            // An email belongs to at most one of the two tables (SaveAgent forbids
            // reusing a customer email and vice versa), so a customer match wins
            // and the agent lookup only runs when there is no customer.
            $customer = CustomerModel::query()
                ->where('email', $params['email'])
                ->orWhere('wp_user_id', $wp_user->ID)
                ->first();

            if ($customer) {
                return $this->loginAndRespond($wp_user, $this->customerData($customer));
            }

            $agent = AgentModel::query()
                ->where('email', $params['email'])
                ->orWhere('wp_user_id', $wp_user->ID)
                ->first();

            // Only a login-enabled agent may sign in here; disabling login unlinks
            // the WordPress user, but the flag is enforced explicitly regardless.
            if ($agent && $agent->canLogin()) {
                return $this->loginAndRespond($wp_user, $this->agentData($agent));
            }

            return new WP_REST_Response([
                'success' => false,
                'code' => 401,
                'message' => 'Account not found',
                'data' => null
            ], 401);
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Establish a real WordPress session for the authenticated account and return
     * the success response. The auth cookie is emitted in this REST response and
     * stored by the browser, so the login carries over to wp-admin and the rest of
     * the site.
     *
     * @param \WP_User $wp_user WordPress user account.
     * @param array    $data    Role-shaped payload to return under `data`.
     * @return WP_REST_Response
     */
    private function loginAndRespond(\WP_User $wp_user, array $data): WP_REST_Response
    {
        wp_set_current_user($wp_user->ID);
        wp_set_auth_cookie($wp_user->ID, true);
        do_action('wp_login', $wp_user->user_login, $wp_user);

        // Fresh logout URL for the now-logged-in user so the form's Logout link
        // ends the WP session in one click.
        $data['logout_url'] = html_entity_decode(wp_logout_url());

        return new WP_REST_Response([
            'success' => true,
            'code' => 200,
            'message' => 'Login successful',
            'data' => $data
        ], 200);
    }

    /**
     * Build the response payload for a customer login. Mirrors the shape returned
     * by the booking panel's `/public/customer/login` so existing consumers stay
     * compatible.
     *
     * @param CustomerModel $customer Matched customer record.
     * @return array
     */
    private function customerData(CustomerModel $customer): array
    {
        return [
            'role' => 'customer',
            'id' => $customer->getID(),
            'email' => $customer->email,
            'first_name' => $customer->first_name ?? '',
            'last_name' => $customer->last_name ?? '',
            'phone' => $customer->phone ?? null,
            'wp_user_id' => $customer->wp_user_id,
            'created_at' => $customer->created_at,
            'updated_at' => $customer->updated_at,
        ];
    }

    /**
     * Build the response payload for an agent login.
     *
     * @param AgentModel $agent Matched agent record.
     * @return array
     */
    private function agentData(AgentModel $agent): array
    {
        return [
            'role' => 'agent',
            'id' => $agent->getID(),
            'email' => $agent->email,
            'first_name' => $agent->first_name ?? '',
            'last_name' => $agent->last_name ?? '',
            'phone' => $agent->phone ?? null,
            'wp_user_id' => $agent->wp_user_id,
            'created_at' => $agent->created_at,
            'updated_at' => $agent->updated_at,
            // Land the agent on the booking dashboard's appointment view. The
            // REST login sets the auth cookie directly, so the wp-login.php
            // `login_redirect` filter (UserManagement\LoginManagement) never
            // fires — the redirect has to travel back in the response instead.
            'redirect_url' => admin_url('admin.php?page=rox-appointment-booking-dashboard#/appointment'),
        ];
    }
}
