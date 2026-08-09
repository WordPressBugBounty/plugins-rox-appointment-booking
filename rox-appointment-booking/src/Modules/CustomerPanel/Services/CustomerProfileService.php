<?php

namespace RoxAppointmentBooking\Modules\CustomerPanel\Services;

use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;
use RoxAppointmentBooking\Modules\Payment\Data\PaymentModel;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Reads and persists the logged-in customer's profile for the Customer Panel's
 * Profile view (ProfileView) — replacing the Phase B mock data. Identity is
 * always the logged-in WP user (resolved via CustomerPanelService); this service
 * never trusts a client-supplied customer id.
 *
 * The customer table has a single `send_notifications` boolean, so the "email
 * reminders" preference maps to that column; the SMS + marketing preferences
 * (which have no column) are stored as one user-meta array.
 */
class CustomerProfileService
{
    public static $loadable = true;

    /** User-meta key for the two preferences with no customer-table column. */
    private const COMM_PREFS_META = 'rox_appointment_booking_customer_comm_prefs';

    /** Upload limits for the profile photo. */
    private const MAX_PHOTO_BYTES = 2097152; // 2 MB
    private const ALLOWED_PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /** @var string Currency symbol for the "Spent" stat. */
    private string $currency;

    public function __construct()
    {
        $code = rox_appointment_booking_payment_settings('payment_currency', 'USD');
        $this->currency = function_exists('rox_appointment_booking__get_currency_symbol')
            ? rox_appointment_booking__get_currency_symbol($code)
            : '$';
    }

    /**
     * The current user's profile shaped for ProfileView. When no customer row
     * exists yet, the form is seeded from the WP user (name/email) with empty
     * detail fields — Save will create the row.
     *
     * @return array
     */
    public function getProfile(): array
    {
        $customer = CustomerPanelService::currentCustomer();
        $user = wp_get_current_user();

        return $this->shape($customer, $user);
    }

    /**
     * Persist the profile for the current user (find-or-create the customer row),
     * then return the fresh shaped profile.
     *
     * @param array $input Raw request params.
     * @return array
     */
    public function saveProfile(array $input): array
    {
        $user = wp_get_current_user();
        $customer = CustomerPanelService::currentCustomer();

        if (! $customer) {
            $customer = new CustomerModel();
            $customer->wp_user_id = $user->ID;
            // Anchor a new row to the login identity; email is not editable here.
            $customer->email = $user->user_email;
        }

        $emailPref = $this->boolInput($input['prefs']['email'] ?? true);

        $customer->fill([
            'first_name'         => sanitize_text_field($input['firstName'] ?? ''),
            'last_name'          => sanitize_text_field($input['lastName'] ?? ''),
            'phone'              => sanitize_text_field($input['phone'] ?? ''),
            'dob'                => $this->sanitizeDob($input['dob'] ?? ''),
            'gender'             => $this->sanitizeGender($input['gender'] ?? ''),
            'send_notifications' => $emailPref,
        ]);
        $customer->save();

        update_user_meta($user->ID, self::COMM_PREFS_META, [
            'sms'       => $this->boolInput($input['prefs']['sms'] ?? false),
            'marketing' => $this->boolInput($input['prefs']['marketing'] ?? false),
        ]);

        return $this->shape($customer, $user);
    }

    /**
     * Store an uploaded profile photo as the customer's thumbnail and return the
     * fresh shaped profile. The upload is validated as an image here because the
     * customer role has no upload_files capability of its own — this endpoint is
     * the only way a customer can add to the media library.
     *
     * @param string $field Name of the multipart field carrying the image.
     * @param array  $file  That entry of the request's file params ($_FILES shape).
     * @return array|\WP_Error
     */
    public function saveProfilePhoto(string $field, array $file)
    {
        $error = $this->validatePhoto($file);

        if ($error) {
            return $error;
        }

        if (! function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $attachmentId = media_handle_upload($field, 0);

        if (is_wp_error($attachmentId)) {
            return $attachmentId;
        }

        $user = wp_get_current_user();
        $customer = CustomerPanelService::currentCustomer();

        if (! $customer) {
            $customer = new CustomerModel();
            $customer->wp_user_id = $user->ID;
            $customer->email = $user->user_email;
        }

        $customer->fill(['thumbnail_id' => (int) $attachmentId]);
        $customer->save();

        return $this->shape($customer, $user);
    }

    /**
     * Reject anything that isn't a reasonably sized real image.
     *
     * @param array $file
     * @return \WP_Error|null
     */
    private function validatePhoto(array $file)
    {
        if (empty($file['tmp_name']) || ! empty($file['error'])) {
            return new \WP_Error(
                'rox_profile_photo_missing',
                esc_html__('No photo was uploaded.', 'rox-appointment-booking'),
                ['status' => 400]
            );
        }

        if (! empty($file['size']) && (int) $file['size'] > self::MAX_PHOTO_BYTES) {
            return new \WP_Error(
                'rox_profile_photo_too_large',
                esc_html__('The photo must be smaller than 2 MB.', 'rox-appointment-booking'),
                ['status' => 400]
            );
        }

        $checked = wp_check_filetype(sanitize_file_name($file['name'] ?? ''));

        if (! $checked['type'] || ! in_array($checked['type'], self::ALLOWED_PHOTO_TYPES, true)) {
            return new \WP_Error(
                'rox_profile_photo_invalid',
                esc_html__('Please choose a JPG, PNG or WebP image.', 'rox-appointment-booking'),
                ['status' => 400]
            );
        }

        return null;
    }

    /**
     * Build the ProfileView payload from a (possibly null) customer row + WP user.
     *
     * @param CustomerModel|null $customer
     * @param \WP_User           $user
     * @return array
     */
    private function shape($customer, $user): array
    {
        // Seed name from the WP user when there's no customer row yet.
        $displayName = $user->display_name ?: '';
        $nameParts = preg_split('/\s+/', trim($displayName), 2);

        $firstName = $customer ? (string) $customer->first_name : ($nameParts[0] ?? '');
        $lastName = $customer ? (string) $customer->last_name : ($nameParts[1] ?? '');
        $email = $customer && $customer->email ? (string) $customer->email : (string) $user->user_email;
        $customerId = $customer ? (int) $customer->getID() : 0;

        $meta = get_user_meta($user->ID, self::COMM_PREFS_META, true);
        $meta = is_array($meta) ? $meta : [];

        return [
            'firstName' => $firstName,
            'lastName'  => $lastName,
            'email'     => $email,
            'phone'     => $customer ? (string) $customer->phone : '',
            'dob'       => $customer && $customer->dob ? gmdate('Y-m-d', strtotime((string) $customer->dob)) : '',
            'gender'    => $customer ? strtolower((string) $customer->gender) : '',
            'avatar'    => $this->avatarUrl($customer, $user),
            'prefs'     => [
                // No customer row yet → default the email opt-in on (matches the mock).
                'email'     => $customer ? (bool) $customer->send_notifications : true,
                'sms'       => !empty($meta['sms']),
                'marketing' => !empty($meta['marketing']),
            ],
            'stats'     => $this->stats($customerId),
        ];
    }

    /**
     * Avatar for the profile sidebar: the photo set on the customer record in the
     * admin (thumbnail_id) wins; otherwise fall back to the WP user's avatar.
     *
     * @param CustomerModel|null $customer
     * @param \WP_User           $user
     * @return string
     */
    private function avatarUrl($customer, $user): string
    {
        if ($customer && $customer->thumbnail_id) {
            $url = wp_get_attachment_url((int) $customer->thumbnail_id);

            if ($url) {
                return $url;
            }
        }

        return get_avatar_url($user->ID, ['size' => 96]) ?: '';
    }

    /**
     * Sidebar stats: total appointments + total spent (paid payments).
     *
     * @param int $customerId
     * @return array<int,array{value:string,label:string}>
     */
    private function stats(int $customerId): array
    {
        if ($customerId <= 0) {
            return [
                ['value' => '0', 'label' => esc_html__('Appointments', 'rox-appointment-booking')],
                ['value' => $this->currency . '0', 'label' => esc_html__('Spent', 'rox-appointment-booking')],
            ];
        }

        $appointments = count(
            AppointmentModel::query()->where('customer_id', $customerId)->get()->toArray()
        );

        $payments = PaymentModel::query()
            ->where('customer_id', $customerId)
            ->where('status', 'paid')
            ->get()
            ->toArray();
        $spent = array_sum(array_map(static fn ($p) => (float) ($p['amount'] ?? 0), $payments));

        return [
            ['value' => (string) $appointments, 'label' => esc_html__('Appointments', 'rox-appointment-booking')],
            ['value' => $this->currency . number_format($spent, 0), 'label' => esc_html__('Spent', 'rox-appointment-booking')],
        ];
    }

    /** Restrict gender to the known lowercase values (matches the admin form). */
    private function sanitizeGender(string $gender): string
    {
        $gender = strtolower(sanitize_text_field($gender));
        return in_array($gender, ['male', 'female', 'prefer_not_to_say'], true) ? $gender : '';
    }

    /** Accept a Y-m-d date or empty string; anything else is dropped. */
    private function sanitizeDob(string $dob): string
    {
        $dob = sanitize_text_field($dob);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) ? $dob : '';
    }

    /** Normalize a JSON/boolean/string checkbox value to a bool. */
    private function boolInput($value): bool
    {
        if (is_array($value)) {
            return in_array('1', $value, true) || in_array(true, $value, true);
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
