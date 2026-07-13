<?php
namespace RoxAppointmentBooking\Modules\FrontendBookingPanel\Services;

defined('ABSPATH') || exit;

use WP_Error;
use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;

/**
 * Class CustomerService
 *
 * @package RoxAppointmentBooking\Modules\FrontendBookingPanel\Services
 * @description Handles frontend booking customer creation and lookup.
 */
class CustomerService
{
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
     * @return void
     */
    private function handleAutoUserCreation(CustomerModel $customer): void
    {
        $settings = get_option('rox_appointment_booking_general_settings', []);
        $auto_create_user = isset($settings['customer_create_auto_enable']) && $settings['customer_create_auto_enable'];

        if (!$auto_create_user) {
            return;
        }

        $existing_wp_user = get_user_by('email', $customer->email);
        if ($existing_wp_user) {
            $customer->wp_user_id = $existing_wp_user->ID;
            $customer->save();
        } else {
            $wp_user_result = $this->createWordPressUser($customer);
            if (!is_wp_error($wp_user_result)) {
                $customer->wp_user_id = $wp_user_result['user_id'];
                $customer->save();
            }
        }
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

        $this->sendCredentialsEmail($customer->email, $username, $password, $full_name);

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
     * Send WordPress login credentials to the customer.
     *
     * @param string $email Customer email address.
     * @param string $username WordPress username.
     * @param string $password Generated password.
     * @param string $full_name Customer full name.
     * @return void
     */
    private function sendCredentialsEmail(string $email, string $username, string $password, string $full_name): void
    {
        add_action('phpmailer_init', function($phpmailer) {
            $phpmailer->isSMTP();
            $phpmailer->Host = 'localhost';
            $phpmailer->Port = 1025;
            $phpmailer->SMTPAuth = false;
            $phpmailer->SMTPSecure = '';
            $phpmailer->ContentType = 'text/html';
        });

        $email_body = sprintf(
            '<p>Hello <strong>%s</strong>,</p>' .
            '<p>Your account has been created.</p>' .
            '<p><strong>Username:</strong> %s<br>' .
            '<strong>Password:</strong> %s</p>' .
            '<p><a href="%s">Login here</a></p>',
            $full_name,
            $username,
            $password,
            wp_login_url()
        );

        wp_mail($email, esc_html__('Booking Engine Login Credentials', 'rox-appointment-booking'), $email_body);
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
