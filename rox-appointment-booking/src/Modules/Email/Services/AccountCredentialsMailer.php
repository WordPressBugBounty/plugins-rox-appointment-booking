<?php

namespace RoxAppointmentBooking\Modules\Email\Services;

defined('ABSPATH') || exit;

/**
 * Class AccountCredentialsMailer
 *
 * @package RoxAppointmentBooking\Modules\Email\Services
 * @description Raises the `account_credentials` e-mail for a WordPress user the
 *              plugin has just created, for one recipient type. The generated
 *              password is never mailed — the message carries a single-use
 *              WordPress reset key so the person sets their own.
 */
class AccountCredentialsMailer
{
    /**
     * Send the credentials e-mail for a newly created WordPress user.
     *
     * Silently does nothing when the user is gone or WordPress refuses to mint a
     * reset key — an account that cannot be handed over is not worth a mail.
     *
     * @param int $userId WordPress user ID.
     * @param string $recipientType EmailTemplateRegistry::RECIPIENT_* the person is.
     * @param array<string, string> $person The person, in the shape that recipient
     *        type's placeholders expect — customer: first_name/last_name/email/phone,
     *        agent: name/email/phone.
     * @return void
     */
    public static function send(int $userId, string $recipientType, array $person): void
    {
        $wpUser = get_user_by('id', $userId);
        if (!$wpUser) {
            return;
        }

        $resetKey = get_password_reset_key($wpUser);
        if (is_wp_error($resetKey)) {
            return;
        }

        $setPasswordUrl = network_site_url(
            'wp-login.php?action=rp&key=' . rawurlencode($resetKey) . '&login=' . rawurlencode($wpUser->user_login),
            'login'
        );

        do_action(
            'rox_appointment_booking_email_event',
            'account_credentials',
            [
                $recipientType     => $person,
                'username'         => $wpUser->user_login,
                'set_password_url' => $setPasswordUrl,
            ],
            // Only the recipient whose account this is — an agent's credentials
            // are not the customer's business and vice versa.
            [$recipientType]
        );
    }
}
