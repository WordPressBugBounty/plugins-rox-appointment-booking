<?php

namespace RoxAppointmentBooking\Modules\Filter\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Supports\Security;
use RoxAppointmentBooking\Modules\Service\Data\ServiceModel;
use RoxAppointmentBooking\Modules\Category\Data\CategoryModel;
use RoxAppointmentBookingPro\Modules\Location\Data\LocationModel;
use RoxAppointmentBooking\Modules\Agent\Data\AgentModel;
use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;

/**
 * Class GetFilterOptions
 *
 * @package RoxAppointmentBooking\Modules\Filter\REST
 * @description Handles retrieving filter option lists via REST API.
 */
class GetFilterOptions extends AbstractREST
{
    /**
     * Whether this REST endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * REST route for retrieving filter options.
     *
     * @var string
     */
    public static string $route = 'filter-options';

    /**
     * Get the HTTP methods allowed for this route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'GET';
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

        if (!is_user_logged_in() || !Security::canAccessPanel()) {
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
        $table = $request->get_param('type');
        
        $data = match($table) {
            'service' => $this->getServiceOptions(),
            'category' => $this->getCategoryOptions(),
            'location' => $this->getLocationOptions(),
            'agent' => $this->getAgentOptions(),
            'customer' => $this->getCustomerOptions(),
            default => null
        };

        if ($data === null) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 404,
                message: esc_html__('Invalid type parameter', 'rox-appointment-booking'),
                headers: ['status' => 404]
            );
        }

        return rox_appointment_booking_rest_response(
            data: $data,
            message: esc_html__('Filter options retrieved successfully', 'rox-appointment-booking')
        );
    }

    /**
     * Get service filter options.
     *
     * @return array
     */
    private function getServiceOptions(): array
    {
        $services = ServiceModel::query()
            ->orderBy('title', 'ASC')
            ->get();
            
        $options = [];
        foreach ($services as $service) {
            $options[] = [
                'id' => $service->getID(),
                'name' => $service->title
            ];
        }
        return $options;
    }

    /**
     * Get category filter options.
     *
     * @return array
     */
    private function getCategoryOptions(): array
    {
        $categories = CategoryModel::query()
            ->orderBy('title', 'ASC')
            ->get();
            
        $options = [];
        foreach ($categories as $category) {
            $options[] = [
                'id' => $category->getID(),
                'name' => $category->title
            ];
        }
        return $options;
    }

    /**
     * Get location filter options.
     *
     * @return array
     */
    private function getLocationOptions(): array
    {
        if (!class_exists(\RoxAppointmentBookingPro\Modules\Location\Data\LocationModel::class)) {
            return [];
        }

        $locations = LocationModel::query()
            ->orderBy('title', 'ASC')
            ->get();
            
        $options = [];
        foreach ($locations as $location) {
            $options[] = [
                'value' => $location->getID(),
                'label' => $location->title,
            ];
        }
        return $options;
    }

    /**
     * Get agent filter options.
     *
     * @return array
     */
    private function getAgentOptions(): array
    {
        $agents = AgentModel::query()
            ->orderBy('first_name', 'ASC')
            ->get();
            
        $options = [];
        foreach ($agents as $agent) {
            $options[] = [
                'id' => $agent->getID(),
                'name' => trim($agent->first_name . ' ' . $agent->last_name)
            ];
        }
        return $options;
    }

    /**
     * Get customer filter options.
     *
     * @return array
     */
    private function getCustomerOptions(): array
    {
        $customers = CustomerModel::query()
            ->orderBy('first_name', 'ASC')
            ->get();
            
        $options = [];
        foreach ($customers as $customer) {
            $options[] = [
                'value' => $customer->getID(),
                'label' => $customer->getFullName()
            ];
        }
        return $options;
    }
}
