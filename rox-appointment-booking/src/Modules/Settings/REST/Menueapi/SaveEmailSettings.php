<?php

namespace RoxAppointmentBooking\Modules\Settings\REST\Menueapi;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Email\Services\EmailService;
use RoxAppointmentBooking\Modules\Email\Services\EmailTemplateRegistry;

/**
 * Class SaveEmailSettings
 *
 * @package RoxAppointmentBooking\Modules\Settings\REST\Menueapi
 * @description Handles saving e-mail settings data via REST API.
 */
class SaveEmailSettings extends AbstractREST
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
    public static string $route = '/email-settings/save';
    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/email-settings/save';

    /**
     * Sender / global keys stored in the e-mail settings option. Anything else
     * in the payload (templates, REST route args) is handled separately or
     * dropped.
     *
     * @var string[]
     */
    private const SETTING_KEYS = [
        'sender_email',
        'sender_name',
        'admin_recipients',
        'reminder_hours_before_customer',
        'reminder_hours_before_agent',
    ];

    /**
     * Get the methods allowed for this route
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return ['POST', 'PUT'];
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
        $params = $request->get_params();

        if (empty($params)) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('E-mail settings data is required', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        try {
            $settings = $this->saveSettings($params);
            $templates = $this->saveTemplates($params);

            return rox_appointment_booking_rest_response(
                data: array_merge($settings, ['templates' => $templates]),
                code: 200,
                message: esc_html__('E-mail settings saved successfully', 'rox-appointment-booking')
            );

        } catch (\Exception $e) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 500,
                message: [
                    'error' => [
                        // translators: %s = error message
                        sprintf(esc_html__('Error saving e-mail settings: %s', 'rox-appointment-booking'), esc_html($e->getMessage()))
                    ]
                ],
                headers: ['status' => 500]
            );
        }
    }

    /**
     * Persist the sender / global keys, merging over what is already stored so a
     * partial payload never wipes a key it did not send.
     *
     * @param array $params Request parameters.
     * @return array<string, mixed> The stored settings.
     */
    private function saveSettings(array $params): array
    {
        $stored = get_option('rox_appointment_booking_email_settings', []);
        if (!is_array($stored)) {
            $stored = [];
        }

        // The two lead times belong to the Pro-only Booking Reminder event; an
        // unlicensed site must not be able to write them, however the payload
        // was built.
        $reminderLocked = EmailTemplateRegistry::isProLocked('booking_reminder');

        foreach (self::SETTING_KEYS as $key) {
            if (!array_key_exists($key, $params)) {
                continue;
            }

            if ($reminderLocked && str_starts_with($key, 'reminder_hours_before')) {
                continue;
            }

            $stored[$key] = $this->sanitizeSetting($key, $params[$key]);
        }

        update_option('rox_appointment_booking_email_settings', $stored);

        return $stored;
    }

    /**
     * Sanitize one sender / global setting.
     *
     * @param string $key Setting key.
     * @param mixed $value Submitted value.
     * @return mixed
     */
    private function sanitizeSetting(string $key, $value)
    {
        switch ($key) {
            case 'sender_email':
                return sanitize_email((string) $value);

            case 'admin_recipients':
                return $this->sanitizeRecipientList((string) $value);

            case 'reminder_hours_before_customer':
            case 'reminder_hours_before_agent':
                // A reminder is only useful somewhere between an hour and a week
                // ahead; anything outside that is a typo.
                return max(1, min(168, absint($value)));

            default:
                return sanitize_text_field((string) $value);
        }
    }

    /**
     * Sanitize the comma-separated admin recipient list, dropping anything that
     * is not a valid address.
     *
     * @param string $value Submitted list.
     * @return string
     */
    private function sanitizeRecipientList(string $value): string
    {
        $clean = [];

        foreach (explode(',', $value) as $candidate) {
            $email = sanitize_email(trim($candidate));

            if ($email !== '' && is_email($email)) {
                $clean[] = $email;
            }
        }

        return implode(', ', array_unique($clean));
    }

    /**
     * Persist template overrides.
     *
     * Only values that actually differ from the shipped default are stored, so
     * the option stays small and future improvements to a default body reach
     * sites that never customised it. A template reverts to its default simply
     * by being submitted with the default text.
     *
     * @param array $params Request parameters.
     * @return array<string, array<string, mixed>> The stored overrides.
     */
    private function saveTemplates(array $params): array
    {
        $stored = get_option(EmailService::TEMPLATES_OPTION, []);
        if (!is_array($stored)) {
            $stored = [];
        }

        if (!array_key_exists('templates', $params)) {
            return $stored;
        }

        $submitted = is_array($params['templates']) ? $params['templates'] : [];

        foreach ($submitted as $key => $values) {
            if (!is_array($values)) {
                continue;
            }

            [$event, $recipient] = EmailTemplateRegistry::splitKey((string) $key);
            $default = EmailTemplateRegistry::template($event, $recipient);

            // Unknown template key — never store it.
            if (!$default) {
                continue;
            }

            // Pro-only event without a licence — never store it either.
            if (EmailTemplateRegistry::isProLocked($event)) {
                continue;
            }

            $override = $this->buildOverride($values, $default);

            if (empty($override)) {
                unset($stored[$key]);
                continue;
            }

            $stored[$key] = $override;
        }

        update_option(EmailService::TEMPLATES_OPTION, $stored);

        return $stored;
    }

    /**
     * Reduce one submitted template to the parts that differ from its default.
     *
     * @param array $values Submitted enabled / subject / body.
     * @param array $default The registry default.
     * @return array<string, mixed> Empty when nothing differs.
     */
    private function buildOverride(array $values, array $default): array
    {
        $override = [];

        // A locked template can never be switched off, so its enabled flag is
        // never stored.
        if (!$default['locked'] && array_key_exists('enabled', $values)) {
            $enabled = filter_var($values['enabled'], FILTER_VALIDATE_BOOLEAN);

            if ($enabled !== $default['enabled']) {
                $override['enabled'] = $enabled;
            }
        }

        if (array_key_exists('subject', $values)) {
            $subject = wp_unslash((string) $values['subject']);

            if (trim($subject) !== '' && $subject !== $default['subject']) {
                $override['subject'] = sanitize_text_field($subject);
            }
        }

        if (array_key_exists('body', $values)) {
            $body = wp_unslash((string) $values['body']);

            if (trim($body) !== '' && $body !== $default['body']) {
                $override['body'] = wp_kses_post($body);
            }
        }

        return $override;
    }
}
