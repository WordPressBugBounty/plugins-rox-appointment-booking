<?php

namespace RoxAppointmentBooking\Modules\Agent\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Agent\Data\AgentModel;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;

/**
 * Class DeleteAgent
 *
 * @package RoxAppointmentBooking\Modules\Agent\REST
 * @description Handles deleting agent via REST API.
 */
class DeleteAgent extends AbstractREST
{
    /**
     * Whether the endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for deleting agents.
     *
     * @var string
     */
    public static string $route = '/agent(?:/(?P<id>\d+))?';
    /**
     * Usable route template for docs.
     *
     * @var string
     */
    public static string $usableRoute = '/agent/{id}';

    /**
     * Gets HTTP methods for the route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'DELETE';
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
	 * Handles agent delete requests.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $ids = $request->get_param('ids');
        $id = $request->get_param('id');
        $deleted = [];
        $errors = [];
        
        if ($ids && is_array($ids)) {
            foreach ($ids as $singleId) {
                $agent = AgentModel::find($singleId);
                if ($agent) {
                    $validation_error = $this->validateAgentCanBeDeleted($singleId);
                    if ($validation_error) {
                        $errors[] = $validation_error;
                        continue;
                    }
                    
                    try {
                        $agent->delete();
                        $deleted[] = $singleId;
                    } catch (\Exception $e) {
                        $errors[] = $e->getMessage();
                    }
                } else {
                    $errors[] = esc_html__('Agent not found', 'rox-appointment-booking');
                }
            }
            $status = empty($errors) ? 200 : 500;
            return rox_appointment_booking_rest_response(
                data : ['deleted' => $deleted, 'errors' => $errors],
                code : $status,
                message : empty($errors) ? esc_html__('Agents deleted successfully', 'rox-appointment-booking') : esc_html__('Some agents could not be deleted', 'rox-appointment-booking'),
                headers : ['status' => $status]
            );
        }
        
        // Single delete fallback
        $agent = AgentModel::find($id);
        if (!$agent) {
            return rox_appointment_booking_rest_response(
                data : null,
                code : 404,
                message : esc_html__('Agent not found', 'rox-appointment-booking'),
                headers : ['status' => 404]
            );
        }
        
        $validation_error = $this->validateAgentCanBeDeleted($id);
        if ($validation_error) {
            return rox_appointment_booking_rest_response(
                data : null,
                code : 400,
                message : $validation_error,
                headers : ['status' => 400]
            );
        }
        
        try {
            $agent->delete();
            return rox_appointment_booking_rest_response(
                data : null,
                message : esc_html__('Agent deleted successfully', 'rox-appointment-booking'),
                code: 200
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
     * Validate if an agent can be deleted
     *
     * @param int $id
     * @return string|null Returns error message if cannot be deleted, null if can be deleted
     */
    private function validateAgentCanBeDeleted(int $id): ?string
    {
        // Check if there are appointments for this agent
        $appointments = AppointmentModel::query()
            ->where('agent_id', $id)
            ->count();

        if ($appointments > 0) {
            return esc_html__('This agent has been added some Appointments. Firstly remove them!', 'rox-appointment-booking');
        }

        return null; // Agent can be deleted
    }
}