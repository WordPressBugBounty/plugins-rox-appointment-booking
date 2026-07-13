<?php
namespace RoxAppointmentBooking\Modules\FrontendBookingPanel\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;

/**
 * Class ResetPassword
 *
 * @package RoxAppointmentBooking\Modules\FrontendBookingPanel\REST
 * @description Validates a password reset key and sets a new password so the
 * customer can reset directly from the booking panel.
 */
class ResetPassword extends AbstractREST
{
    /**
     * Whether this REST endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * REST route for setting a new password.
     *
     * @var string
     */
    public static string $route = '/public/customer/reset-password';

    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/public/customer/reset-password';

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

        if (empty($params['key']) || empty($params['login']) || empty($params['password'])) {
            $json_params = $request->get_json_params();
            if ($json_params) {
                $params = array_merge($params, $json_params);
            }
        }

        $key = isset($params['key']) ? sanitize_text_field($params['key']) : '';
        $login = isset($params['login']) ? sanitize_text_field($params['login']) : '';
        $password = isset($params['password']) ? (string) $params['password'] : '';

        if (empty($key) || empty($login)) {
            return new WP_REST_Response([
                'success' => false,
                'code' => 400,
                'message' => esc_html__('Invalid password reset link', 'rox-appointment-booking'),
                'data' => null
            ], 400);
        }

        if (empty($password)) {
            return new WP_REST_Response([
                'success' => false,
                'code' => 400,
                'message' => esc_html__('Password is required', 'rox-appointment-booking'),
                'data' => null
            ], 400);
        }

        try {
            $user = check_password_reset_key($key, $login);

            if (is_wp_error($user)) {
                $message = ($user->get_error_code() === 'expired_key')
                    ? esc_html__('This password reset link has expired. Please request a new one.', 'rox-appointment-booking')
                    : esc_html__('This password reset link is invalid. Please request a new one.', 'rox-appointment-booking');

                return new WP_REST_Response([
                    'success' => false,
                    'code' => 400,
                    'message' => $message,
                    'data' => null
                ], 400);
            }

            reset_password($user, $password);

            return new WP_REST_Response([
                'success' => true,
                'code' => 200,
                'message' => esc_html__('Your password has been reset successfully.', 'rox-appointment-booking'),
                'data' => [
                    'email' => $user->user_email,
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
