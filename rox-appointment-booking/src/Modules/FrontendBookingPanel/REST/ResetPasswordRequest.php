<?php
namespace RoxAppointmentBooking\Modules\FrontendBookingPanel\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;

/**
 * Class ResetPasswordRequest
 *
 * @package RoxAppointmentBooking\Modules\FrontendBookingPanel\REST
 * @description Handles public customer "forgot password" requests by emailing a
 * password reset link to the account owner.
 */
class ResetPasswordRequest extends AbstractREST
{
    /**
     * Whether this REST endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * REST route for the reset-password request.
     *
     * @var string
     */
    public static string $route = '/public/customer/reset-password-request';

    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/public/customer/reset-password-request';

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

        if (empty($params['email'])) {
            $json_params = $request->get_json_params();
            if ($json_params) {
                $params = array_merge($params, $json_params);
            }
        }

        if (empty($params['email'])) {
            return new WP_REST_Response([
                'success' => false,
                'code' => 400,
                'message' => esc_html__('Email is required', 'rox-appointment-booking'),
                'data' => null
            ], 400);
        }

        $email = sanitize_email($params['email']);

        if (!is_email($email)) {
            return new WP_REST_Response([
                'success' => false,
                'code' => 400,
                'message' => esc_html__('Invalid email address', 'rox-appointment-booking'),
                'data' => null
            ], 400);
        }

        try {
            $wp_user = get_user_by('email', $email);

            if (!$wp_user) {
                return new WP_REST_Response([
                    'success' => false,
                    'code' => 404,
                    'message' => esc_html__('No account found with this email address', 'rox-appointment-booking'),
                    'data' => null
                ], 404);
            }

            $reset_key = get_password_reset_key($wp_user);
            if (is_wp_error($reset_key)) {
                return new WP_REST_Response([
                    'success' => false,
                    'code' => 500,
                    'message' => $reset_key->get_error_message(),
                    'data' => null
                ], 500);
            }

            $reset_page_url = $this->resolveResetPageUrl($params['reset_page_url'] ?? '');

            $this->sendResetEmail($wp_user, $reset_key, $reset_page_url);

            return new WP_REST_Response([
                'success' => true,
                'code' => 200,
                'message' => esc_html__('We have sent a password reset link to your email address.', 'rox-appointment-booking'),
                'data' => null
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

    /**
     * Resolve the booking-panel page URL the reset link should point back to.
     *
     * Only same-site URLs are accepted (prevents the reset link from being
     * pointed at an external host). Falls back to the site home URL.
     *
     * @param string $reset_page_url URL supplied by the booking panel.
     * @return string
     */
    private function resolveResetPageUrl(string $reset_page_url): string
    {
        $reset_page_url = esc_url_raw(trim($reset_page_url));

        if (empty($reset_page_url)) {
            return home_url('/');
        }

        $requestHost = wp_parse_url($reset_page_url, PHP_URL_HOST);
        $siteHost = wp_parse_url(home_url('/'), PHP_URL_HOST);

        if (empty($requestHost) || strtolower($requestHost) !== strtolower((string) $siteHost)) {
            return home_url('/');
        }

        return $reset_page_url;
    }

    /**
     * Send the password reset link email to the account owner.
     *
     * @param \WP_User $wp_user        WordPress user account.
     * @param string   $reset_key      Password reset key generated by WordPress.
     * @param string   $reset_page_url Booking-panel page URL to link back to.
     * @return void
     */
    private function sendResetEmail(\WP_User $wp_user, string $reset_key, string $reset_page_url): void
    {
        // add_query_arg() URL-encodes the values itself, so pass them raw to
        // avoid double-encoding.
        $reset_url = add_query_arg(
            [
                'rox_reset_key' => $reset_key,
                'rox_reset_login' => $wp_user->user_login,
            ],
            $reset_page_url
        );

        $emailSettings = get_option('rox_appointment_booking_email_settings', []);
        if (empty($emailSettings)) {
            $emailSettings = get_option('rox_appointment_booking_notification_settings', []);
        }
        $senderEmail = sanitize_email($emailSettings['sender_email'] ?? '');
        $senderName = sanitize_text_field($emailSettings['sender_name'] ?? '');
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        if (!empty($senderEmail)) {
            $headers[] = empty($senderName)
                ? sprintf('From: %1$s', $senderEmail)
                : sprintf('From: %1$s <%2$s>', $senderName, $senderEmail);
        }

        $display_name = $wp_user->display_name ? $wp_user->display_name : $wp_user->user_login;

        $email_body = sprintf(
            '<p>%1$s <strong>%2$s</strong>,</p>' .
            '<p>%3$s</p>' .
            '<p><a href="%4$s">%5$s</a></p>' .
            '<p>%6$s</p>',
            esc_html__('Hello', 'rox-appointment-booking'),
            esc_html($display_name),
            esc_html__('We received a request to reset the password for your account.', 'rox-appointment-booking'),
            esc_url($reset_url),
            esc_html__('Reset your password', 'rox-appointment-booking'),
            esc_html__('If you did not request a password reset, you can safely ignore this email.', 'rox-appointment-booking')
        );

        wp_mail(
            $wp_user->user_email,
            esc_html__('Password Reset Request', 'rox-appointment-booking'),
            $email_body,
            $headers
        );
    }
}
