<?php

namespace RoxAppointmentBooking\Modules\Customer\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_Session_Tokens;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;
use RoxAppointmentBooking\Modules\Agent\Data\AgentModel;
use RoxAppointmentBooking\Modules\Email\Services\AccountCredentialsMailer;
use RoxAppointmentBooking\Modules\Email\Services\EmailTemplateRegistry;

/**
 * Class SaveCustomer
 * 
 * @package RoxAppointmentBooking\Modules\Customer\REST
 * @description Handles saving customer data via REST API.
 */
class SaveCustomer extends AbstractREST
{
    /**
     * Whether this REST endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * REST route for creating and updating customers.
     *
     * @var string
     */
    public static string $route = '/customer(?:/(?P<id>\d+))?';

    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/customer/';

    /**
     * Get the HTTP methods allowed for this route.
     * 
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return ['POST', 'PUT'];
    }

    /**
     * Check whether the current user can access this endpoint.
     * 
     * @param WP_REST_Request $request REST request instance.
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
     * Required customer fields for create/update validation.
     *
     * @return array
     */
    private static function getRequiredFields(): array
    {
        return ['first_name', 'last_name', 'email'];
    }

    /**
     * Handle the REST API request.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = $request->get_param('id');
        $params = $request->get_params();

        // Validate required fields
        $required_fields = self::getRequiredFields();
        foreach ($required_fields as $field) {
            if (empty($params[$field])) {
                return rox_appointment_booking_rest_response(
                    data : null,
                    code : 400,
                    // translators: %s = field name
                    message : sprintf(esc_html__('%s is required', 'rox-appointment-booking'), esc_html($field)),
                    headers : ['status' => 400]
                );
            }
        }

        // Validate email format
        if (!is_email($params['email'])) {
            return rox_appointment_booking_rest_response(
                data : null,
                code : 400,
                message : esc_html__('Invalid email address', 'rox-appointment-booking'),
                headers : ['status' => 400]
            );
        }

        // Check for existing customer with same email
        $existing = CustomerModel::query()
            ->where('email', $params['email'])
            ->when($id, function($q) use ($id) {
                $q->where('id', '!=', $id);
            })
            ->first();

        if ($existing) {
            return rox_appointment_booking_rest_response(
                data : null,
                code : 400,
                message : esc_html__('A customer with this email already exists', 'rox-appointment-booking'),
                headers : ['status' => 400]
            );
        }

        // An email already used by an agent cannot be reused for a customer —
        // unless that agent row IS this customer. The mirror of the exclusion in
        // SaveAgent: an agent who books through the frontend panel gets a customer
        // row on the same email and wp_user_id, and without this the auto-created
        // row could never be edited afterwards.
        $existing_agent = AgentModel::query()
            ->where('email', $params['email'])
            ->first();
        $current_customer = $id ? CustomerModel::find($id) : null;
        $is_same_person = $existing_agent && $current_customer && (
            $params['email'] === $current_customer->email
            || (!empty($current_customer->wp_user_id)
                && (int) $existing_agent->wp_user_id === (int) $current_customer->wp_user_id)
        );
        if ($existing_agent && !$is_same_person) {
            return rox_appointment_booking_rest_response(
                data : null,
                code : 400,
                message : esc_html__('An agent with this email already exists', 'rox-appointment-booking'),
                headers : ['status' => 400]
            );
        }

        try {
            if ($id) {
                $customer = CustomerModel::find($id);
                if (!$customer) {
                    return rox_appointment_booking_rest_response(
                        data : null,
                        code : 404,
                        message : esc_html__('Customer not found', 'rox-appointment-booking'),
                        headers : ['status' => 404]
                    );
                }
            } else {
                $customer = new CustomerModel();
            }

            //Validate thumbnail_id if provided
            if (!empty($params['thumbnail_id'])) {
                if (!is_numeric($params['thumbnail_id']) || $params['thumbnail_id'] <= 0) {
                    return rox_appointment_booking_rest_response(
                        data: null,
                        code: 400,
                        message: ['Thumbnail ID must be a valid positive number'],
                        headers: ['status' => 400]
                    );
                }

                // Check if attachment exists
                if (!wp_get_attachment_url($params['thumbnail_id'])) {
                    return rox_appointment_booking_rest_response(
                        data: null,
                        code: 400,
                        message: ['Thumbnail attachment not found'],
                        headers: ['status' => 400]
                    );
                }
            }

            // Handle allow_login checkbox field properly - if not present, set to false
            if (!isset($params['allow_to_login'])) {
                $params['allow_to_login'] = false;
            } else {
                // Handle array format from frontend (e.g., ["1"] or [])
                if (is_array($params['allow_to_login'])) {
                    $params['allow_to_login'] = in_array("1", $params['allow_to_login']);
                } else {
                    // Convert string values to boolean
                    $params['allow_to_login'] = filter_var($params['allow_to_login'], FILTER_VALIDATE_BOOLEAN);
                }
            }

            // Handle send_notifications checkbox field properly. The customer
            // form no longer edits this, so an absent field means "leave the
            // stored value alone" rather than "switch it off".
            if (isset($params['send_notifications'])) {
                // Handle array format from frontend (e.g., ["1"] or [])
                if (is_array($params['send_notifications'])) {
                    $params['send_notifications'] = in_array("1", $params['send_notifications']);
                } else {
                    // Convert string values to boolean
                    $params['send_notifications'] = filter_var($params['send_notifications'], FILTER_VALIDATE_BOOLEAN);
                }
            }

            $fillable_data = array_intersect_key($params, array_flip($customer->getFillable()));
            $customer->fill($fillable_data);
            $customer->save();

            // Update WordPress user if linked and customer details changed
            if ($id && $customer->wp_user_id) {
                $wp_user_data = [
                    'ID' => $customer->wp_user_id,
                ];
                
                // Update first name if changed
                if (isset($params['first_name'])) {
                    $wp_user_data['first_name'] = $params['first_name'];
                }
                
                // Update last name if changed
                if (isset($params['last_name'])) {
                    $wp_user_data['last_name'] = $params['last_name'];
                }
                
                // Update email if changed
                if (isset($params['email'])) {
                    $wp_user_data['user_email'] = $params['email'];
                }
                
                // Only update if there are changes beyond the ID
                if (count($wp_user_data) > 1) {
                    $wp_update_result = wp_update_user($wp_user_data);
                }
            }

            // Tracks whether createWordPressUser() ran below. It already applies
            // the submitted password, so the reset block further down must not
            // apply it a second time.
            $wp_user_created = false;

            // Create or link WordPress user if allow_to_login is enabled
            if ($params['allow_to_login']) {
                // Check if WordPress user already exists with this email
                $existing_wp_user = get_user_by('email', $customer->email);
                
                if ($existing_wp_user) {
                    // Keep existing roles and add rox_appointment_booking_customer only when missing.
                    if (!in_array('rox_appointment_booking_customer', (array) $existing_wp_user->roles, true)) {
                        $existing_wp_user->add_role('rox_appointment_booking_customer');
                    }

                    // Link to existing WordPress user
                    $customer->wp_user_id = $existing_wp_user->ID;
                    $customer->save();
                } else if (!$customer->wp_user_id) {
                    // Create WordPress user for customers not yet linked to one
                    // (new customers, or existing customers enabling login later).
                    $password = !empty($params['password']) ? $params['password'] : null;
                    $wp_user_result = $this->createWordPressUser($customer, $params, $password);

                    if (!is_wp_error($wp_user_result)) {
                        // Save WordPress user ID to customer record
                        $customer->wp_user_id = $wp_user_result;
                        $customer->save();
                        $wp_user_created = true;

                        AccountCredentialsMailer::send(
                            (int) $wp_user_result,
                            EmailTemplateRegistry::RECIPIENT_CUSTOMER,
                            [
                                'first_name' => (string) ($customer->first_name ?? ''),
                                'last_name'  => (string) ($customer->last_name ?? ''),
                                'email'      => (string) $customer->email,
                                'phone'      => (string) ($customer->phone ?? ''),
                            ]
                        );
                    }
                }
            } else {
                // If allow_to_login is disabled, remove the wp_user_id link
                if ($customer->wp_user_id) {
                    $customer->wp_user_id = null;
                    $customer->save();
                }
            }

            // Manual password reset from the edit form. Runs after the link/create
            // block so it also covers an account that was linked in this same
            // request, and only on update — on create the password was already
            // consumed by createWordPressUser().
            if ($id && !empty($params['password']) && $customer->wp_user_id && !$wp_user_created) {
                // Checkbox arrives as ["1"] from the form's Checkbox.Group, the
                // same shape allow_to_login handles above.
                $send_password_email = false;
                if (isset($params['send_password_email'])) {
                    $send_password_email = is_array($params['send_password_email'])
                        ? in_array("1", $params['send_password_email'])
                        : filter_var($params['send_password_email'], FILTER_VALIDATE_BOOLEAN);
                }

                $password_result = $this->updateWordPressUserPassword(
                    $customer,
                    $params['password'],
                    $send_password_email
                );

                if (is_wp_error($password_result)) {
                    return rox_appointment_booking_rest_response(
                        data : null,
                        code : 403,
                        message : $password_result->get_error_message(),
                        headers : ['status' => 403]
                    );
                }
            }

            return rox_appointment_booking_rest_response(
                data : [
                    'id' => $customer->getID(),
                    'full_name' => $customer->full_name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'thumbnail_id' => $customer->thumbnail_id,
                    'gender' => $customer->gender,
                    'dob' => $customer->dob ? gmdate('Y-m-d', strtotime($customer->dob)) : null,
                    'allow_to_login' => (bool)$customer->allow_to_login,
                    'send_notifications' => (bool)$customer->send_notifications,
                    'notes' => $customer->internal_notes,
                    'status' => $customer->status,
                    'created_at' => $customer->created_at,
                    'updated_at' => $customer->updated_at,
                ],
                message : $id 
                    ? esc_html__('Customer updated successfully', 'rox-appointment-booking')
                    : esc_html__('Customer created successfully', 'rox-appointment-booking'),
                code : $id ? 200 : 201,
            );

        } catch (\Exception $e) {
            return rox_appointment_booking_rest_response(
                data : null,
                code : 500,
                message : $e->getMessage(),
                headers : ['status' => 500]
            );
        }
    }

    /**
     * Create a WordPress user for the customer.
     *
     * @param CustomerModel $customer Customer model instance.
     * @param array $params Customer request parameters.
     * @param string|null $password Optional password for the new user.
     * @return int|WP_Error The user ID on success, WP_Error on failure
     */
    private function createWordPressUser(CustomerModel $customer, array $params, $password = null)
    {
        // Check if user already exists with this email
        $existing_user = get_user_by('email', $customer->email);
        if ($existing_user) {
            return new WP_Error(
                'user_exists',
                esc_html__('A WordPress user with this email already exists', 'rox-appointment-booking')
            );
        }

        // Generate username from email (remove domain)
        $username = sanitize_user(explode('@', $customer->email)[0]);
        
        // Ensure username is unique
        $original_username = $username;
        $counter = 1;
        while (username_exists($username)) {
            $username = $original_username . $counter;
            $counter++;
        }

        // Use provided password or generate a random one
        if (empty($password)) {
            $password = wp_generate_password(12, true, true);
        }

        // Prepare user data
        $user_data = [
            'user_login' => $username,
            'user_email' => $customer->email,
            'user_pass' => $password,
            'display_name' => $customer->full_name,
            'first_name' => $customer->full_name,
            'role' => 'rox_appointment_booking_customer'
        ];

        // Create the user
        $user_id = wp_insert_user($user_data);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        // Send password reset email to the new user
        wp_new_user_notification($user_id, null, 'user');

        return $user_id;
    }

    /**
     * Set a new password on the customer's linked WordPress account.
     *
     * @param CustomerModel $customer Customer whose linked account is being updated.
     * @param string $password Raw password submitted from the edit form.
     * @param bool $send_password Whether to include the new password in the notification email.
     * @return true|WP_Error True on success, WP_Error when the account may not be edited.
     */
    private function updateWordPressUserPassword(CustomerModel $customer, string $password, bool $send_password = false)
    {
        $user_id = (int) $customer->wp_user_id;

        // `manage_options` on its own is not enough here. The block above links
        // a customer to any existing WordPress account matching their email, so
        // that account can outrank the current user — an administrator, or a
        // super admin on multisite. `edit_user` is what enforces that ranking.
        if (!current_user_can('edit_user', $user_id)) {
            return new WP_Error(
                'rox_appointment_booking_cannot_edit_user',
                esc_html__('Customer saved, but you do not have permission to change the password of the linked WordPress account.', 'rox-appointment-booking')
            );
        }

        $result = wp_update_user([
            'ID' => $user_id,
            'user_pass' => $password,
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        // Drop sessions so the old password stops working immediately. When the
        // customer is linked to the account doing the editing, destroy only the
        // *other* sessions: wp_update_user() has just re-issued this request's
        // auth cookie against a fresh session token, and destroy_all() would
        // throw that away and log them straight out of wp-admin.
        if (get_current_user_id() === $user_id) {
            wp_destroy_other_sessions();
        } else {
            WP_Session_Tokens::get_instance($user_id)->destroy_all();
        }

        // wp_password_change_notification() emails the site admin, not the
        // account holder — so tell the customer themselves, otherwise they are
        // locked out with no idea why. Routed through the plugin's e-mail module
        // rather than wp_mail() so it uses the configured sender, the shared
        // layout, and the template the admin can edit under Settings > E-mails.
        // `new_password` is only filled when the admin ticked the send checkbox;
        // empty leaves {new_password_block} out of the rendered body.
        do_action(
            'rox_appointment_booking_email_event',
            'password_changed',
            [
                'customer_id'  => (int) $customer->getID(),
                'new_password' => $send_password ? $password : '',
            ],
            [EmailTemplateRegistry::RECIPIENT_CUSTOMER]
        );

        return true;
    }
}
