<?php
namespace RoxAppointmentBooking\Modules\FrontendBookingPanel\Services;

defined('ABSPATH') || exit;

use WP_Error;
use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;
use RoxAppointmentBooking\Modules\Email\Services\EmailTemplateRegistry;

/**
 * Class CustomerService
 *
 * @package RoxAppointmentBooking\Modules\FrontendBookingPanel\Services
 * @description Handles frontend booking customer creation and lookup.
 */
class CustomerService
{
    /**
     * WordPress user id created for the customer during this request, when auto
     * account creation produced a brand new account. Stays null for an existing
     * customer, an already-linked WP user, or when the setting is disabled.
     *
     * @var int|null
     */
    private ?int $createdUserId = null;

    /**
     * Save or return an existing customer from booking data.
     *
     * @param array $params Customer request parameters.
     * @return array|WP_Error
     */
    public function saveCustomer(array $params): array|WP_Error
    {
        // Required built-in fields depend on the system-field config (Pro-
        // overridable). Email is always required (dedup + account login).
        $systemFields = function_exists('rox_appointment_booking_system_fields')
            ? rox_appointment_booking_system_fields()
            : [];
        $required = ['email'];
        foreach (['first_name', 'last_name', 'phone'] as $field) {
            $cfg = $systemFields[$field] ?? ['enabled' => true, 'required' => true];
            if (!empty($cfg['enabled']) && !empty($cfg['required'])) {
                $required[] = $field;
            }
        }

        foreach ($required as $field) {
            if (empty($params[$field])) {
                // translators: %s = field name
                return new WP_Error('missing_field', sprintf(esc_html__('%s is required', 'rox-appointment-booking'), esc_html($field)), ['status' => 400]);
            }
        }

        if (!is_email($params['email'])) {
            return new WP_Error('invalid_email', esc_html__('Invalid email address', 'rox-appointment-booking'), ['status' => 400]);
        }

        $existing = CustomerModel::query()->where('email', $params['email'])->first();
        if ($existing) {
            // A customer who booked while account creation was switched off has
            // no login yet, and the dedup above means they would never get one.
            // Give them the account now, but never a session: the record already
            // carries earlier bookings, so it is not this visitor's to open just
            // by typing the address.
            if (empty($existing->wp_user_id)) {
                $this->handleAutoUserCreation($existing, false);
            }

            return $this->formatCustomerData($existing);
        }

        $customer = new CustomerModel();
        $customer->fill(array_intersect_key($params, array_flip($customer->getFillable())));
        $customer->save();

        $this->handleAutoUserCreation($customer);

        return $this->formatCustomerData($customer);
    }

    /**
     * Find or create a customer from a verified external profile (e.g. a Google
     * sign-in). Unlike saveCustomer() this requires only a valid email — name is
     * optional — because the identity provider has already authenticated the
     * user. Reuses the same email dedup and optional WP-user auto-creation.
     *
     * @param array $profile ['email' => .., 'first_name' => .., 'last_name' => ..].
     * @return array|WP_Error Formatted customer data, or WP_Error on invalid email.
     */
    public function findOrCreateByEmail(array $profile): array|WP_Error
    {
        if (empty($profile['email']) || !is_email($profile['email'])) {
            return new WP_Error('invalid_email', esc_html__('Invalid email address', 'rox-appointment-booking'), ['status' => 400]);
        }

        $existing = CustomerModel::query()->where('email', $profile['email'])->first();
        if ($existing) {
            return $this->formatCustomerData($existing);
        }

        $customer = new CustomerModel();
        $customer->fill(array_intersect_key($profile, array_flip($customer->getFillable())));
        $customer->save();

        $this->handleAutoUserCreation($customer);

        return $this->formatCustomerData($customer);
    }

    /**
     * Create or link a WordPress user when auto creation is enabled.
     *
     * @param CustomerModel $customer Customer model instance.
     * @param bool $allowAutoLogin Whether the created account may be logged in
     *                             by maybeAutoLogin(). False for customers that
     *                             already existed before this booking.
     * @return void
     */
    private function handleAutoUserCreation(CustomerModel $customer, bool $allowAutoLogin = true): void
    {
        $settings = get_option('rox_appointment_booking_general_settings', []);
        $auto_create_user = isset($settings['customer_create_auto_enable']) && $settings['customer_create_auto_enable'];

        if (!$auto_create_user) {
            return;
        }

        $existing_wp_user = get_user_by('email', $customer->email);
        if ($existing_wp_user) {
            $customer->wp_user_id = $existing_wp_user->ID;
            // The customer now has a login, so the admin form's "Allow to login"
            // checkbox must reflect it — saving that form with the box unticked
            // drops the wp_user_id link again.
            $customer->allow_to_login = 1;
            $customer->save();
        } else {
            $wp_user_result = $this->createWordPressUser($customer);
            if (!is_wp_error($wp_user_result)) {
                $customer->wp_user_id = $wp_user_result['user_id'];
                $customer->allow_to_login = 1;
                $customer->save();

                if ($allowAutoLogin) {
                    $this->createdUserId = (int) $wp_user_result['user_id'];
                }
            }
        }
    }

    /**
     * Log the customer in when both the account creation and the automatic
     * login settings are enabled and a brand new account was created during
     * this request.
     *
     * Only freshly created accounts are logged into: submitting the booking
     * form proves the visitor typed an email address, not that they own it, so
     * a pre-existing account is never handed a session this way.
     *
     * @return bool True when a WordPress session was established.
     */
    public function maybeAutoLogin(): bool
    {
        if (!$this->createdUserId || is_user_logged_in()) {
            return false;
        }

        $settings = get_option('rox_appointment_booking_general_settings', []);
        if (empty($settings['customer_create_auto_enable']) || empty($settings['customer_auto_login_enable'])) {
            return false;
        }

        $user = get_user_by('id', $this->createdUserId);
        if (!$user) {
            return false;
        }

        // Same session handshake as the panel's customer login endpoint, so the
        // login carries over to the rest of the site.
        wp_set_current_user($user->ID);

        $loggedInCookie = null;
        $capture = static function ($cookie) use (&$loggedInCookie) {
            $loggedInCookie = $cookie;
        };
        add_action('set_logged_in_cookie', $capture);
        wp_set_auth_cookie($user->ID, true);
        remove_action('set_logged_in_cookie', $capture);

        // wp_set_auth_cookie() only emits Set-Cookie headers, it does not touch
        // $_COOKIE. Any nonce minted after this point would otherwise be bound
        // to the logged-out request's (empty) session token and be rejected on
        // the next call, when the browser sends the real one.
        if ($loggedInCookie) {
            $_COOKIE[LOGGED_IN_COOKIE] = $loggedInCookie;
        }

        do_action('wp_login', $user->user_login, $user);

        return true;
    }

    /**
     * Create a WordPress user for a customer.
     *
     * @param CustomerModel $customer Customer model instance.
     * @return array|WP_Error
     */
    private function createWordPressUser(CustomerModel $customer): array|WP_Error
    {
        $existing_user = get_user_by('email', $customer->email);
        if ($existing_user) {
            return new WP_Error('user_exists', esc_html__('User already exists', 'rox-appointment-booking'));
        }

        $username = $this->generateUniqueUsername($customer->email);
        $password = wp_generate_password(12, true, true);
        $full_name = trim($customer->first_name . ' ' . $customer->last_name);

        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_email' => $customer->email,
            'user_pass' => $password,
            'display_name' => $full_name,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name ?? '',
            'role' => 'rox_appointment_booking_customer'
        ]);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        $this->sendCredentialsEmail((int) $user_id, $customer->email, $full_name);

        return ['customer_id' => $customer->id, 'user_id' => $user_id];
    }

    /**
     * Generate a unique username from an email address.
     *
     * @param string $email Email address.
     * @return string
     */
    private function generateUniqueUsername(string $email): string
    {
        $username = sanitize_user(explode('@', $email)[0]);
        $original_username = $username;
        $counter = 1;
        while (username_exists($username)) {
            $username = $original_username . $counter++;
        }
        return $username;
    }

    /**
     * Send the customer their new account details.
     *
     * The generated password is never emailed — the message carries a
     * single-use WordPress reset key so the customer sets their own.
     *
     * @param int $user_id WordPress user ID.
     * @param string $email Customer email address.
     * @param string $full_name Customer full name.
     * @return void
     */
    private function sendCredentialsEmail(int $user_id, string $email, string $full_name): void
    {
        $wp_user = get_user_by('id', $user_id);
        if (!$wp_user) {
            return;
        }

        $reset_key = get_password_reset_key($wp_user);
        if (is_wp_error($reset_key)) {
            return;
        }

        $set_password_url = network_site_url(
            'wp-login.php?action=rp&key=' . rawurlencode($reset_key) . '&login=' . rawurlencode($wp_user->user_login),
            'login'
        );

        do_action(
            'rox_appointment_booking_email_event',
            'account_credentials',
            [
                'customer'         => [
                    'first_name' => $full_name,
                    'last_name'  => '',
                    'email'      => $email,
                ],
                'username'         => $wp_user->user_login,
                'set_password_url' => $set_password_url,
            ],
            // The agent variant of this e-mail is wired up in Step 9 of
            // dev-resources/EMAIL_NOTIFICATION_SYSTEM_PLAN.md.
            [EmailTemplateRegistry::RECIPIENT_CUSTOMER]
        );
    }

    /**
     * Format customer data for frontend booking responses.
     *
     * @param CustomerModel $customer Customer model instance.
     * @return array
     */
    private function formatCustomerData(CustomerModel $customer): array
    {
        return [
            'id' => $customer->getID(),
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
        ];
    }
}
