<?php

namespace RoxAppointmentBooking\Modules\Settings\REST\Menueapi;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Email\Services\EmailService;
use RoxAppointmentBooking\Modules\Email\Services\EmailTemplateRegistry;

/**
 * Class GetEmailSettings
 *
 * @package RoxAppointmentBooking\Modules\Settings\REST\Menueapi
 * @description Retrieves e-mail settings data via REST API.
 */
class GetEmailSettings extends AbstractREST
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
    public static string $route = '/email-settings/get';
    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/email-settings/get';

    /**
     * Get the methods allowed for this route
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'GET';
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
        try {
            $email_settings = get_option('rox_appointment_booking_email_settings', []);
            if (empty($email_settings)) {
                $legacy_settings = get_option('rox_appointment_booking_notification_settings', []);
                if (!empty($legacy_settings)) {
                    $email_settings = $legacy_settings;
                    update_option('rox_appointment_booking_email_settings', $legacy_settings);
                }
            }

            if (!is_array($email_settings)) {
                $email_settings = [];
            }

            // Defaults for the keys added by the notification system, so the
            // form binds to real values on a site that has never saved them.
            $email_settings['admin_recipients'] = (string) ($email_settings['admin_recipients'] ?? '');

            // Customer and agent each have their own lead time. Whether either
            // reminder goes out at all is the Booking Reminder template's own
            // per-recipient enable flag, not a setting. `reminder_hours_before`
            // is the single value earlier builds stored; it seeds both.
            $legacyHours = (int) ($email_settings['reminder_hours_before'] ?? 24);
            $email_settings['reminder_hours_before_customer'] = (int) ($email_settings['reminder_hours_before_customer'] ?? $legacyHours);
            $email_settings['reminder_hours_before_agent']    = (int) ($email_settings['reminder_hours_before_agent'] ?? $legacyHours);

            $email_settings['templates'] = $this->templates();
            $email_settings['catalogue'] = $this->catalogue();

            return rox_appointment_booking_rest_response(
                data: $email_settings,
                code: 200,
                message: [
                    'success' => [
                        esc_html__('E-mail settings retrieved successfully', 'rox-appointment-booking')
                    ]
                ]
            );

        } catch (\Exception $e) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 500,
                message: [
                    'error' => [
                        // translators: %s = error message
                        sprintf(esc_html__('Error retrieving e-mail settings: %s', 'rox-appointment-booking'), esc_html($e->getMessage()))
                    ]
                ],
                headers: ['status' => 500]
            );
        }
    }

    /**
     * Every template's effective values — the registry default with any stored
     * admin override applied — plus the untouched default, so the settings UI
     * can offer "Reset to default" without a second request.
     *
     * @return array<string, array<string, mixed>>
     */
    private function templates(): array
    {
        $stored = get_option(EmailService::TEMPLATES_OPTION, []);
        if (!is_array($stored)) {
            $stored = [];
        }

        $templates = [];

        foreach (EmailTemplateRegistry::keys() as $key) {
            [$event, $recipient] = EmailTemplateRegistry::splitKey($key);
            $default  = EmailTemplateRegistry::template($event, $recipient);
            $override = isset($stored[$key]) && is_array($stored[$key]) ? $stored[$key] : [];

            $enabled = $default['locked']
                || (array_key_exists('enabled', $override)
                    ? filter_var($override['enabled'], FILTER_VALIDATE_BOOLEAN)
                    : $default['enabled']);

            // An unlicensed Pro e-mail reads as off, so the UI never shows a
            // switch on for something that cannot send.
            if (EmailTemplateRegistry::isProLocked($event)) {
                $enabled = false;
            }

            $templates[$key] = [
                'enabled'         => $enabled,
                'subject'         => (string) ($override['subject'] ?? $default['subject']),
                'body'            => (string) ($override['body'] ?? $default['body']),
                'default_subject' => $default['subject'],
                'default_body'    => $default['body'],
                'locked'          => $default['locked'],
                'customized'      => !empty($override),
            ];
        }

        return $templates;
    }

    /**
     * The event catalogue — labels, descriptions and the placeholder tokens each
     * event understands, so the UI holds no hardcoded copy of any of it.
     *
     * @return array<int, array<string, mixed>>
     */
    private function catalogue(): array
    {
        $recipientLabels = [
            EmailTemplateRegistry::RECIPIENT_CUSTOMER => esc_html__('Customer', 'rox-appointment-booking'),
            EmailTemplateRegistry::RECIPIENT_AGENT    => esc_html__('Agent', 'rox-appointment-booking'),
            EmailTemplateRegistry::RECIPIENT_ADMIN    => esc_html__('Admin', 'rox-appointment-booking'),
        ];

        $catalogue = [];

        foreach (EmailTemplateRegistry::events() as $eventKey => $event) {
            $recipients = [];

            foreach (array_keys($event['recipients']) as $recipientType) {
                $recipients[] = [
                    'type'         => $recipientType,
                    'label'        => $recipientLabels[$recipientType] ?? ucfirst($recipientType),
                    'template_key' => EmailTemplateRegistry::makeKey($eventKey, $recipientType),
                    'locked'       => EmailTemplateRegistry::isLocked($eventKey, $recipientType),
                ];
            }

            $catalogue[] = [
                'key'          => $eventKey,
                'label'        => $event['label'] ?? $eventKey,
                'description'  => $event['description'] ?? '',
                // Pro-only events stay listed so the UI can show the upsell.
                'pro'          => !empty($event['pro']),
                'placeholders' => $event['placeholders'] ?? [],
                'recipients'   => $recipients,
            ];
        }

        return $catalogue;
    }
}
