<?php

namespace RoxAppointmentBooking\Modules\Service\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Service\Data\ServiceModel;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceAgentRelationModel;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceCategoryRelationModel;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceLocationRelationModel;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceExtraserviceRelationModel;

/**
 * Class DeleteService
 *
 * @package RoxAppointmentBooking\Modules\Service\REST
 * @description Handles deleting service via REST API.
 */
class DeleteService extends AbstractREST
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
    public static string $route = '/service(?:/(?P<id>\d+))?';
    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/service/{id}';

    /**
     * Get the HTTP methods allowed for this route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'DELETE';
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
     * Handle the REST API request.
     *
     * @param WP_REST_Request $request REST request instance.
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
                $service = ServiceModel::find($singleId);
                if ($service) {
                    $validation_error = $this->validateServiceCanBeDeleted($singleId);
                    if ($validation_error) {
                        $errors[] = $validation_error;
                        continue;
                    }
                    
                    try {
                        $this->deleteServiceRelationships($singleId);
                        $service->delete();
                        $deleted[] = $singleId;
                    } catch (\Exception $e) {
                        $errors[] = $e->getMessage();
                    }
                } else {
                    $errors[] = esc_html__('Service not found', 'rox-appointment-booking');
                }
            }
            $status = empty($errors) ? 200 : 500;
            return rox_appointment_booking_rest_response(
                data : ['deleted' => $deleted, 'errors' => $errors],
                code : $status,
                message : empty($errors) ? esc_html__('Services deleted successfully', 'rox-appointment-booking') : esc_html__('Some services could not be deleted', 'rox-appointment-booking'),
                headers : ['status' => $status]
            );
        }
        
        // Single delete fallback
        $service = ServiceModel::find($id);
        if (!$service) {
            return rox_appointment_booking_rest_response(
                data : null,
                code : 404,
                message : esc_html__('Service not found', 'rox-appointment-booking'),
                headers : ['status' => 404]
            );
        }
        
        $validation_error = $this->validateServiceCanBeDeleted($id);
        if ($validation_error) {
            return rox_appointment_booking_rest_response(
                data : null,
                code : 400,
                message : $validation_error,
                headers : ['status' => 400]
            );
        }
        
        try {
            $this->deleteServiceRelationships($id);
            $service->delete();
            return rox_appointment_booking_rest_response(
                data : null,
                message : esc_html__('Service deleted successfully', 'rox-appointment-booking'),
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
     * Validate if a service can be deleted
     *
     * @param int $id
     * @return string|null Returns error message if cannot be deleted, null if can be deleted
     */
    private function validateServiceCanBeDeleted(int $id): ?string
    {
        // Check appointments that have this service_id directly
        $appointmentsWithServiceId = AppointmentModel::query()
            ->where('service_id', $id)
            ->count();

        if ($appointmentsWithServiceId > 0) {
            return esc_html__('This service has some appointments scheduled. Please remove them first', 'rox-appointment-booking');
        }

        // Check appointments that have this service in service_details JSON
        $appointmentsWithServiceDetails = AppointmentModel::query()
            ->get()
            ->filter(function ($appointment) use ($id) {
                $service_details = $appointment->service_details;
                return is_array($service_details) && 
                       isset($service_details['id']) && 
                       (int)$service_details['id'] === (int)$id;
            });

        if ($appointmentsWithServiceDetails->count() > 0) {
            return esc_html__('This service has some appointments scheduled. Please remove them first', 'rox-appointment-booking');
        }

        return null; // Service can be deleted
    }

    /**
     * Delete all relationships associated with a service
     *
     * @param int $service_id
     * @return void
     * @throws \Exception
     */
    private function deleteServiceRelationships(int $service_id): void
    {
        try {
            // Delete service-agent relationships
            ServiceAgentRelationModel::removeAllForService($service_id);
            
            // Delete service-category relationships
            ServiceCategoryRelationModel::query()->byService($service_id)->delete();
            
            // Delete service-location relationships
            ServiceLocationRelationModel::query()->byService($service_id)->delete();

            // Delete service-extra service relationships
            ServiceExtraserviceRelationModel::query()->byService($service_id)->delete();

        } catch (\Exception $e) {
            throw new \Exception(esc_html__('Failed to delete service relationships: ', 'rox-appointment-booking') . esc_html($e->getMessage()));
        }
    }
}
