<?php

namespace RoxAppointmentBooking\Modules\Email\Services;

defined('ABSPATH') || exit;

/**
 * Class EmailService
 *
 * @package RoxAppointmentBooking\Modules\Email\Services
 * @description The single send path for every e-mail the plugin produces, and the
 *              only wp_mail() call. Callers fire the
 *              `rox_appointment_booking_email_event` action instead.
 */
class EmailService
{
    /**
     * The option holding admin template overrides.
     */
    public const TEMPLATES_OPTION = 'rox_appointment_booking_email_templates';

    /**
     * Placeholders whose values are pre-built trusted HTML and are therefore
     * substituted raw. Everything else is escaped.
     *
     * @var string[]
     */
    private const RAW_TOKENS = [
        '{appointments_table}',
        '{payment_details_table}',
        '{custom_fields_table}',
        '{new_password_block}',
    ];

    /**
     * Placeholders holding a URL, escaped with esc_url() rather than esc_html().
     *
     * @var string[]
     */
    private const URL_TOKENS = [
        '{site_url}',
        '{set_password_url}',
        '{login_url}',
        '{my_bookings_url}',
        '{admin_appointment_url}',
    ];

    /**
     * Per-request guard against duplicates — one admin save can fire a status
     * change and a reschedule, and order/payment syncing can re-enter.
     *
     * @var array<string, bool>
     */
    private static array $sent = [];

    /**
     * Send one e-mail.
     *
     * @param string $eventKey Event key, e.g. 'booking_confirmed'.
     * @param string $recipientType Recipient type, e.g. 'agent'.
     * @param array $context Event context (see EmailPlaceholderResolver).
     * @return bool True when at least one message was handed to wp_mail().
     */
    public static function send(string $eventKey, string $recipientType, array $context = []): bool
    {
        if (!self::isEnabled($eventKey, $recipientType, $context)) {
            return false;
        }

        $recipients = EmailRecipientResolver::resolve($recipientType, $context);
        if (empty($recipients)) {
            return false;
        }

        $rendered = self::render($eventKey, $recipientType, $context);
        if (!$rendered) {
            return false;
        }

        $args = [
            'to'          => $recipients,
            'subject'     => $rendered['subject'],
            'body'        => $rendered['body'],
            // The body is HTML — without this header wp_mail() defaults to
            // text/plain and the recipient sees the raw markup.
            'headers'     => self::headers(),
            'attachments' => [],
        ];

        /**
         * Filter the final wp_mail() arguments — last chance to alter or block
         * a message (return an empty `to` to block it).
         *
         * @param array  $args          to / subject / body / headers / attachments.
         * @param string $eventKey      Event key.
         * @param string $recipientType Recipient type.
         * @param array  $context       Event context.
         */
        $args = apply_filters('rox_appointment_booking_email_args', $args, $eventKey, $recipientType, $context);

        if (empty($args['to'])) {
            return false;
        }

        $guard = self::guardKey($eventKey, $recipientType, $args['to'], $context);
        if (isset(self::$sent[$guard])) {
            return false;
        }
        self::$sent[$guard] = true;

        return (bool) wp_mail(
            $args['to'],
            $args['subject'],
            $args['body'],
            $args['headers'],
            $args['attachments']
        );
    }

    /**
     * Whether an event x recipient e-mail is switched on.
     *
     * @param string $eventKey Event key.
     * @param string $recipientType Recipient type.
     * @param array $context Event context.
     * @return bool
     */
    public static function isEnabled(string $eventKey, string $recipientType, array $context = []): bool
    {
        $default = EmailTemplateRegistry::template($eventKey, $recipientType);

        // Not a registered template — nothing to send.
        if (!$default) {
            return false;
        }

        // A locked template can never be switched off.
        if ($default['locked']) {
            $enabled = true;
        } else {
            $stored  = self::storedTemplate($eventKey, $recipientType);
            $enabled = array_key_exists('enabled', $stored)
                ? filter_var($stored['enabled'], FILTER_VALIDATE_BOOLEAN)
                : $default['enabled'];
        }

        /**
         * Final veto before an e-mail is rendered and sent.
         *
         * @param bool   $enabled       Whether the e-mail is switched on.
         * @param string $eventKey      Event key.
         * @param string $recipientType Recipient type.
         * @param array  $context       Event context.
         */
        $enabled = (bool) apply_filters(
            'rox_appointment_booking_email_enabled',
            $enabled,
            $eventKey,
            $recipientType,
            $context
        );

        // A Pro-only e-mail never sends without a Pro licence, whatever the
        // stored switch or a third-party filter says. Checked last so nothing
        // can re-enable it.
        return $enabled && !EmailTemplateRegistry::isProLocked($eventKey);
    }

    /**
     * Render a template's subject + body with the event's placeholders.
     *
     * @param string $eventKey Event key.
     * @param string $recipientType Recipient type.
     * @param array $context Event context.
     * @return array{subject: string, body: string}|null
     */
    public static function render(string $eventKey, string $recipientType, array $context = []): ?array
    {
        $default = EmailTemplateRegistry::template($eventKey, $recipientType);
        if (!$default) {
            return null;
        }

        $stored = self::storedTemplate($eventKey, $recipientType);

        $subject = isset($stored['subject']) && trim((string) $stored['subject']) !== ''
            ? (string) $stored['subject']
            : $default['subject'];

        $body = isset($stored['body']) && trim((string) $stored['body']) !== ''
            ? (string) $stored['body']
            : $default['body'];

        $placeholders = EmailPlaceholderResolver::resolve($eventKey, $context);

        return [
            // Subjects are plain text — substitute unescaped, then let wp_mail()
            // handle the header encoding.
            'subject' => self::substitute($subject, $placeholders, false),
            'body'    => EmailLayout::wrap(self::substitute($body, $placeholders, true), $placeholders),
        ];
    }

    /**
     * Replace every {token} in a string.
     *
     * @param string $text Template text.
     * @param array<string, string> $placeholders Token => value.
     * @param bool $forHtml Whether the output lands in HTML (escape values).
     * @return string
     */
    private static function substitute(string $text, array $placeholders, bool $forHtml): string
    {
        $search  = [];
        $replace = [];

        foreach ($placeholders as $token => $value) {
            $search[] = $token;

            if (!$forHtml) {
                $replace[] = wp_strip_all_tags((string) $value);
                continue;
            }

            if (in_array($token, self::RAW_TOKENS, true)) {
                $replace[] = (string) $value;
            } elseif (in_array($token, self::URL_TOKENS, true)) {
                $replace[] = esc_url((string) $value);
            } else {
                $replace[] = esc_html((string) $value);
            }
        }

        return str_replace($search, $replace, $text);
    }

    /**
     * The stored admin override for one template, or an empty array.
     *
     * @param string $eventKey Event key.
     * @param string $recipientType Recipient type.
     * @return array<string, mixed>
     */
    private static function storedTemplate(string $eventKey, string $recipientType): array
    {
        $stored = get_option(self::TEMPLATES_OPTION, []);

        if (!is_array($stored)) {
            return [];
        }

        $key = EmailTemplateRegistry::makeKey($eventKey, $recipientType);

        return isset($stored[$key]) && is_array($stored[$key]) ? $stored[$key] : [];
    }

    /**
     * Shared From: + Content-Type headers, built from the E-mail settings.
     *
     * @return string[]
     */
    private static function headers(): array
    {
        $senderEmail = sanitize_email((string) rox_appointment_booking_email_settings('sender_email', ''));
        $senderName  = sanitize_text_field((string) rox_appointment_booking_email_settings('sender_name', ''));

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        if (!empty($senderEmail)) {
            $headers[] = empty($senderName)
                ? sprintf('From: %1$s', $senderEmail)
                : sprintf('From: %1$s <%2$s>', $senderName, $senderEmail);
        }

        return $headers;
    }

    /**
     * Key identifying one logical message for the per-request duplicate guard.
     *
     * @param string $eventKey Event key.
     * @param string $recipientType Recipient type.
     * @param mixed $to Resolved recipients.
     * @param array $context Event context.
     * @return string
     */
    private static function guardKey(string $eventKey, string $recipientType, $to, array $context): string
    {
        $entity = $context['appointment_ids']
            ?? $context['appointment_id']
            ?? $context['order_id']
            ?? $context['customer_id']
            ?? '';

        return md5(wp_json_encode([$eventKey, $recipientType, $to, $entity]));
    }
}
