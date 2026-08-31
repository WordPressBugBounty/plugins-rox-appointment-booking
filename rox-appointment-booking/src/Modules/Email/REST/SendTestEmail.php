<?php

namespace RoxAppointmentBooking\Modules\Email\REST;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Email\Services\EmailService;
use RoxAppointmentBooking\Modules\Email\Services\EmailTemplateRegistry;

/**
 * Class SendTestEmail
 *
 * @package RoxAppointmentBooking\Modules\Email\REST
 * @description Sends one template to a chosen address with sample data, so an
 *              admin can preview a template without staging a real booking.
 */
class SendTestEmail extends AbstractREST
{
    /**
     * Whether this class should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for this endpoint.
     *
     * @var string
     */
    public static string $route = '/email-settings/test';
    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/email-settings/test';

    /**
     * Get the methods allowed for this route
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'POST';
    }

    /**
     * Check if the user has permission to access this endpoint
     *
     * @param WP_REST_Request $request
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
     * Handle the REST API request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $templateKey = sanitize_text_field((string) $request->get_param('template_key'));
        [$event, $recipient] = EmailTemplateRegistry::splitKey($templateKey);

        if (!EmailTemplateRegistry::template($event, $recipient)) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('Unknown e-mail template', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        // A test send force-enables the template, so a Pro-only e-mail has to be
        // refused here as well as in EmailService::isEnabled().
        if (EmailTemplateRegistry::isProLocked($event)) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 403,
                message: esc_html__('This e-mail is a paid feature. Upgrade to send it.', 'rox-appointment-booking'),
                headers: ['status' => 403]
            );
        }

        $to = sanitize_email((string) $request->get_param('to'));
        if ($to === '' || !is_email($to)) {
            $to = (string) wp_get_current_user()->user_email;
        }

        if ($to === '' || !is_email($to)) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('A valid recipient e-mail address is required', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        $sent = $this->sendWithSampleData($event, $recipient, $to);

        if (!$sent) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 500,
                message: esc_html__('The test e-mail could not be sent. Check the site\'s mail configuration.', 'rox-appointment-booking'),
                headers: ['status' => 500]
            );
        }

        return rox_appointment_booking_rest_response(
            data: ['to' => $to, 'template_key' => $templateKey],
            code: 200,
            // translators: %s = recipient e-mail address
            message: sprintf(esc_html__('Test e-mail sent to %s', 'rox-appointment-booking'), esc_html($to))
        );
    }

    /**
     * Send the template with sample placeholder values, overriding the enable
     * switch and the recipient for the duration of this one send.
     *
     * @param string $event Event key.
     * @param string $recipient Recipient type.
     * @param string $to Address to send to.
     * @return bool
     */
    private function sendWithSampleData(string $event, string $recipient, string $to): bool
    {
        // A test send must work even for a template the admin has switched off —
        // they are previewing it, not triggering it.
        $forceEnabled = '__return_true';
        $forceTo      = static function () use ($to) {
            return [$to];
        };
        $fillSample   = static function (array $map) {
            return array_merge($map, self::sampleValues());
        };

        add_filter('rox_appointment_booking_email_enabled', $forceEnabled, 99);
        add_filter('rox_appointment_booking_email_recipients', $forceTo, 99);
        add_filter('rox_appointment_booking_email_placeholders', $fillSample, 99);

        $sent = EmailService::send($event, $recipient, []);

        remove_filter('rox_appointment_booking_email_placeholders', $fillSample, 99);
        remove_filter('rox_appointment_booking_email_recipients', $forceTo, 99);
        remove_filter('rox_appointment_booking_email_enabled', $forceEnabled, 99);

        return $sent;
    }

    /**
     * Readable stand-in values for every placeholder, so a test e-mail reads
     * like a real one instead of a page of blanks.
     *
     * @return array<string, string>
     */
    private static function sampleValues(): array
    {
        $cell = 'padding: 8px; border: 1px solid #ddd;';

        return [
            '{customer_name}'          => 'Jane Sample',
            '{customer_first_name}'    => 'Jane',
            '{customer_last_name}'     => 'Sample',
            '{customer_email}'         => 'jane@example.com',
            '{customer_phone}'         => '+1 555 0100',
            '{agent_name}'             => 'Alex Agent',
            '{agent_email}'            => 'alex@example.com',
            '{agent_phone}'            => '+1 555 0199',
            '{service_name}'           => esc_html__('Sample Service', 'rox-appointment-booking'),
            '{category_name}'          => esc_html__('Sample Category', 'rox-appointment-booking'),
            '{location_name}'          => esc_html__('Sample Location', 'rox-appointment-booking'),
            '{appointment_date}'       => date_i18n((string) get_option('date_format'), strtotime('+1 day')),
            '{appointment_start_time}' => '10:00 AM',
            '{appointment_end_time}'   => '11:00 AM',
            '{appointment_status}'     => esc_html__('Active', 'rox-appointment-booking'),
            '{appointment_id}'         => '101',
            '{appointment_count}'      => '1',
            '{internal_notes}'         => '',
            '{meet_link}'              => '',
            // A test e-mail has no real appointment, so there is no meeting to
            // link to — the block renders as nothing, exactly as it does on a
            // booking without a video call.
            '{meet_link_block}'        => '',
            '{order_number}'           => 'SAMPLE-1001',
            '{order_status}'           => esc_html__('Processing', 'rox-appointment-booking'),
            '{order_total}'            => rox_appointment_booking_format_currency(100),
            '{amount_paid}'            => rox_appointment_booking_format_currency(100),
            '{amount_due}'             => rox_appointment_booking_format_currency(0),
            '{deposit_amount}'         => rox_appointment_booking_format_currency(0),
            '{payment_method}'         => 'stripe',
            '{payment_status}'         => esc_html__('Paid', 'rox-appointment-booking'),
            '{transaction_id}'         => 'sample_txn_1001',
            '{refund_amount}'          => rox_appointment_booking_format_currency(25),
            '{refund_reason}'          => esc_html__('Sample reason', 'rox-appointment-booking'),
            '{old_date}'               => date_i18n((string) get_option('date_format'), strtotime('+1 day')),
            '{old_time}'               => '10:00 AM',
            '{new_date}'               => date_i18n((string) get_option('date_format'), strtotime('+2 days')),
            '{new_time}'               => '02:00 PM',
            '{username}'               => 'jane.sample',
            '{set_password_url}'       => wp_login_url(),
            '{appointments_table}'     => '<table style="border-collapse: collapse; width: 100%;">' .
                '<tr style="background-color: #f5f5f5;">' .
                '<th style="' . $cell . ' text-align: left;">' . esc_html__('Service', 'rox-appointment-booking') . '</th>' .
                '<th style="' . $cell . ' text-align: left;">' . esc_html__('Date', 'rox-appointment-booking') . '</th>' .
                '<th style="' . $cell . ' text-align: left;">' . esc_html__('Time', 'rox-appointment-booking') . '</th>' .
                '</tr><tr>' .
                '<td style="' . $cell . '">' . esc_html__('Sample Service', 'rox-appointment-booking') . '</td>' .
                '<td style="' . $cell . '">' . esc_html(date_i18n((string) get_option('date_format'), strtotime('+1 day'))) . '</td>' .
                '<td style="' . $cell . '">10:00 AM - 11:00 AM</td>' .
                '</tr></table>',
            '{payment_details_table}'  => '<table style="border-collapse: collapse; width: 100%;">' .
                '<tr><td style="' . $cell . '"><strong>' . esc_html__('Transaction ID:', 'rox-appointment-booking') . '</strong></td><td style="' . $cell . '">sample_txn_1001</td></tr>' .
                '<tr><td style="' . $cell . '"><strong>' . esc_html__('Amount Paid:', 'rox-appointment-booking') . '</strong></td><td style="' . $cell . '">' . esc_html(rox_appointment_booking_format_currency(100)) . '</td></tr>' .
                '</table>',
            '{custom_fields_table}'    => '',
        ];
    }
}
