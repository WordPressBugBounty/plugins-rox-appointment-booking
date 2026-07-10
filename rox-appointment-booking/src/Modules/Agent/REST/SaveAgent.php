<?php

namespace RoxAppointmentBooking\Modules\Agent\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Agent\Data\AgentModel;
use RoxAppointmentBooking\Modules\Service\Services\ServiceService;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceAgentRelationModel;

/**
 * Class SaveAgent
 *
 * @package RoxAppointmentBooking\Modules\Agent\REST
 * @description Handles saving agent data via REST API.
 */
class SaveAgent extends AbstractREST
{
    /**
     * Whether the endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for saving agents.
     *
     * @var string
     */
    public static string $route = '/agent(?:/(?P<id>\d+))?';
    /**
     * Usable route template for docs.
     *
     * @var string
     */
    public static string $usableRoute = '/agent/';

    /**
     * Maximum number of agents allowed on the free plan. Creating additional
     * agents requires Rox Appointment Booking Pro.
     *
     * @var int
     */
    private const FREE_AGENT_LIMIT = 1;

    /**
     * Gets HTTP methods for the route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return ['POST', 'PUT'];
    }

    /**
     * Check user permissions.
     *
     * @since 1.0.0
     *
     * @param WP_REST_Request $request
     *
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
	 * Required agent fields for create/update validation.
	 *
	 * @return array
	 */
    private static function getRequiredFields(): array
    {
        return ['first_name', 'last_name', 'email'];
    }

	/**
	 * Handles create or update agent requests.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = $request->get_param('id');
        $params = $request->get_params();

        // The free plan allows only a single agent; creating more requires Pro.
        if (
            !$id
            && !rox_appointment_booking_is_pro_user()
            && AgentModel::query()->count() >= self::FREE_AGENT_LIMIT
        ) {
            return rox_appointment_booking_rest_response(
                data    : null,
                code    : 403,
                message : esc_html__('The free plan is limited to 1 agent. Please upgrade to Rox Appointment Booking Pro to add more agents.', 'rox-appointment-booking'),
                headers : ['status' => 403]
            );
        }

        $required_fields = self::getRequiredFields();

        foreach ($required_fields as $field) {
            if (empty($params[$field])) {
                return rox_appointment_booking_rest_response(
                    data : null,
                    code : 400,
                    // translators: %s is the name of the required field
                    message : sprintf(esc_html__('%s is required', 'rox-appointment-booking'), esc_html($field)),
                    headers : ['status' => 400]
                );
            }
        }
        if (!is_email($params['email'])) {
            return rox_appointment_booking_rest_response(
                data : null,
                code : 400,
                message : esc_html__('Invalid email address', 'rox-appointment-booking'),
                headers : ['status' => 400]
            );
        }
        $existing = AgentModel::query()
            ->where('email', $params['email'])
            ->when($id, function($q) use ($id) {
                $q->where('id', '!=', $id);
            })
            ->first();
        if ($existing) {
            return rox_appointment_booking_rest_response(
                data : null,
                code : 400,
                message : esc_html__('An agent with this email already exists', 'rox-appointment-booking'),
                headers : ['status' => 400]
            );
        }
        try {
            if ($id) {
                $agent = AgentModel::find($id);
                if (!$agent) {
                    return rox_appointment_booking_rest_response(
                        data : null,
                        code : 404,
                        message : esc_html__('Agent not found', 'rox-appointment-booking'),
                        headers : ['status' => 404]
                    );
                }
            } else {
                $agent = new AgentModel();
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

            if(isset($params['weekly_schedule']) && is_array($params['weekly_schedule'])) {
                $params['weekly_schedule'] = json_encode($params['weekly_schedule']);
            }
            
            if(isset($params['special_days']) && is_array($params['special_days'])) {
                $params['special_days'] = json_encode($params['special_days']);
            }

            if(isset($params['holiday']) && is_array($params['holiday'])) {
                $params['holiday'] = json_encode($params['holiday']);
            }

           // Handle allow_to_login checkbox field properly - if not present, set to 0
           if (!isset($params['allow_to_login'])) {
               $params['allow_to_login'] = 0;
           } else {
               // Handle array format from frontend (e.g., ["1"] or [])
               if (is_array($params['allow_to_login'])) {
                   $params['allow_to_login'] = in_array("1", $params['allow_to_login']) ? 1 : 0;
               } else {
                   // Convert string/boolean values to integer (1 or 0)
                   $params['allow_to_login'] = filter_var($params['allow_to_login'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
               }
           }

            // Remove relationship arrays from params before saving to agent table
            $relationship_fields = ['service_ids'];
            $filtered_params = $params;
            foreach ($relationship_fields as $field) {
                unset($filtered_params[$field]);
            }

            $fillable_data = array_intersect_key($filtered_params, array_flip($agent->getFillable()));
            $agent->fill($fillable_data);

            $agent->save();

            // Update WordPress user if linked and agent details changed
            if ($id && $agent->wp_user_id) {
                $wp_user_data = [
                    'ID' => $agent->wp_user_id,
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
                    wp_update_user($wp_user_data);
                }
            }

            // Create or link WordPress user if allow_to_login is enabled
            if ($params['allow_to_login']) {
                // Check if WordPress user already exists with this email
                $existing_wp_user = get_user_by('email', $agent->email);
                
                if ($existing_wp_user) {
                    // Keep existing roles and add rox_appointment_booking_agent only when missing.
                    if (!in_array('rox_appointment_booking_agent', (array) $existing_wp_user->roles, true)) {
                        $existing_wp_user->add_role('rox_appointment_booking_agent');
                    }

                    // Link to existing WordPress user
                    $agent->wp_user_id = $existing_wp_user->ID;
                    $agent->save();
                } else if (!$id) {
                    // Create new WordPress user only for new agents
                    $password = !empty($params['password']) ? $params['password'] : null;
                    $wp_user_result = $this->createWordPressUser($agent, $params, $password);

                    if (!is_wp_error($wp_user_result)) {
                        // Save WordPress user ID to agent record
                        $agent->wp_user_id = $wp_user_result;
                        $agent->save();
                    }
                }
            } else {
                // If allow_to_login is disabled, remove the wp_user_id link
                if ($agent->wp_user_id) {
                    $agent->wp_user_id = null;
                    $agent->save();
                }
            }

            if (empty($params['service_ids']) && !empty($params['service_id'])) {
                $params['service_ids'] = $params['service_id'];
            }

            // Handle service relationships
            $this->handleServiceRelationships($agent, $params);
            return rox_appointment_booking_rest_response(
                data : [
                    'id' => $agent->getID(),
                    'full_name' => $agent->full_name,
                    'title' => $agent->title,
                    'email' => $agent->email,
                    'phone' => $agent->phone,
                    'created_at' => $agent->created_at,
                    'internal_notes' => $agent->internal_notes ?? null,
                    'updated_at' => $agent->updated_at,
                    'thumbnail_id' => $agent->thumbnail_id ?? null,
                    'experience_years' => $agent->experience_years ?? null,
                    'certifications' => $agent->certifications ?? null,
                    'linkedin' => $agent->linkedin ?? null,
                    'twitter' => $agent->twitter ?? null,
                    'bio' => $agent->bio ?? null,
                    'services' => $this->getAgentServices($agent),
                ],
                message : $id 
                    ? esc_html__('Agent updated successfully', 'rox-appointment-booking')
                    : esc_html__('Agent created successfully', 'rox-appointment-booking'),
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
     * Handle service relationships for the agent
     *
     * @param AgentModel $agent
     * @param array $params
     * @return void
     * @throws \Exception
     */
    private function handleServiceRelationships(AgentModel $agent, array $params): void
    {
        $agent_id = $agent->getID();
        
        // Get the service data from params
        $service_data = $params['service_ids'] ?? [];
        $service_ids = ServiceService::normalizeIds($service_data);

        // Remove existing relationships for this agent
        ServiceAgentRelationModel::query()
            ->where('agent_id', $agent_id)
            ->delete();

        // Create new relationships
        if (!empty($service_ids)) {
            foreach ($service_ids as $service_id) {
                try {
                    ServiceAgentRelationModel::createRelation((int)$agent_id, (int)$service_id);
                } catch (\Exception $e) {
                }
            }
        }
    }

    /**
     * Get services for an agent
     *
     * @param AgentModel $agent
     * @return array
     */
    private function getAgentServices(AgentModel $agent): array
    {
        $services = ServiceAgentRelationModel::getServicesByAgent($agent->getID());
        return $services->map(function($service) {
            return [
                'id' => $service->id,
                'title' => $service->title
            ];
        })->toArray();
    }

    /**
     * Create a WordPress user for the agent with subscriber role
     *
     * @param AgentModel $agent
     * @param array $params
     * @param string|null $password Optional password to use for the new user
     * @return int|WP_Error The user ID on success, WP_Error on failure
     */
    private function createWordPressUser(AgentModel $agent, array $params, $password = null)
    {
        // Check if user already exists with this email
        $existing_user = get_user_by('email', $agent->email);
        if ($existing_user) {
            return new WP_Error(
                'user_exists',
                esc_html__('A WordPress user with this email already exists', 'rox-appointment-booking')
            );
        }

        // Generate username from email (remove domain)
        $username = sanitize_user(explode('@', $agent->email)[0]);
        
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
            'user_email' => $agent->email,
            'user_pass' => $password,
            'display_name' => $agent->full_name,
            'first_name' => $agent->full_name,
            'role' => 'rox_appointment_booking_agent'
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
     * Upload image to WordPress media library and return attachment ID
     *
     * @param string $file_path
     * @param string $file_name
     * @return int|WP_Error
     */
    protected function upload_image_to_media_library($file_path, $file_name) {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }
        if (!file_exists($file_path)) {
            return new WP_Error('file_missing', 'File does not exist.');
        }
        $file = array(
            'name' => $file_name,
            'type' => mime_content_type($file_path),
            'tmp_name' => $file_path,
            'error' => 0,
            'size' => filesize($file_path),
        );
        $overrides = array(
            'test_form' => false,
            'test_size' => true,
            'test_type' => true,
        );
        $upload = wp_handle_sideload($file, $overrides);
        if (isset($upload['error'])) {
            return new WP_Error('upload_error', $upload['error']);
        }
        $filetype = wp_check_filetype($upload['file'], null);
        $attachment = array(
            'guid' => $upload['url'],
            'post_mime_type' => $filetype['type'],
            'post_title' => sanitize_file_name(pathinfo($file_name, PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit',
        );
        $attach_id = wp_insert_attachment($attachment, $upload['file']);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);
        return $attach_id;
    }
}