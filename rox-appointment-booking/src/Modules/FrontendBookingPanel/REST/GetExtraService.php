<?php

namespace RoxAppointmentBooking\Modules\FrontendBookingPanel\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;

/**
 * Class GetExtraService
 *
 * @package RoxAppointmentBooking\Modules\FrontendBookingPanel\REST
 * @description Handles retrieving list of extra services and single extra service via REST API.
 */
class GetExtraService extends AbstractREST
{
    /**
     * Whether this REST endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * REST route for listing and retrieving public extra services.
     *
     * @var string
     */
    public static string $route = '/public/extra-service(?:/(?P<id>\d+))?';

    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/public/extra-service/';

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
        return true;
    }

    /**
     * Format extra service data for REST responses.
     *
     * @param ExtraServiceModel $extraService Extra service model instance.
     * @param bool $detailed Whether to include detailed information.
     * @param string $mode Response mode.
     * @return array
     */
    protected function getExtraServiceData($extraService, bool $detailed = false, string $mode = 'default'): array
    {
        $currency = rox_appointment_booking_payment_settings('payment_currency') ?? 'USD';
        
        $data = [
            'id' => $extraService->getID(),
            'name' => $extraService->title ? $extraService->title : '',
            'description' => $extraService->description,
            'duration' => $extraService->getFormattedDuration(),
            'currency' => $currency,
            'currency_symbol' => rox_appointment_booking__get_currency_symbol($currency),
            'price' => $extraService->price,
            'icon' => $extraService->thumbnail_id ? wp_get_attachment_image_url($extraService->thumbnail_id, 'medium') : null,
            'sort_order' => $extraService->sort_order ?? 0,
        ];


        if ($detailed) {
            $data = array_merge($data, [
                'title' => $extraService->title,
                'description' => $extraService->description,
                'price' => $extraService->price,
                'formatted_price' => $extraService->getFormattedPrice(),
                'duration' => $extraService->getFormattedDuration(), // Duration formatted like GetService.php
                'status' => $extraService->status,
                'is_active' => $extraService->isActive(),
                'created_at' => $extraService->created_at,
                'updated_at' => $extraService->updated_at,
                'internal_notes' => $extraService->internal_notes,
                'duration_in_hours' => $extraService->getDurationInHours(),
                'duration_raw' => $extraService->duration, // Raw duration value for form editing
                'thumbnail_id' => $extraService->thumbnail_id,
                'thumbnail_url' => $extraService->thumbnail_id ? wp_get_attachment_image_url($extraService->thumbnail_id, 'medium') : null,
            ]);
        }

        if( $mode === 'list' ) {
            return [
                'value' => $extraService->getID(),
                'label' => $extraService->title,
            ];
        }

        return $data;
    }

    /**
     * Handle the REST API request.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $extraServiceModelClass = '\\RoxAppointmentBookingPro\\Modules\\ExtraService\\Data\\ExtraServiceModel';
        if (!class_exists($extraServiceModelClass)) {
            return rox_appointment_booking_rest_response(
                data: [],
                message: esc_html__('Extra services are a Pro feature', 'rox-appointment-booking')
            );
        }

        $id = $request->get_param('id');

        if ($id) {
            $extraService = $extraServiceModelClass::find($id);
            if (!$extraService) {
                return rox_appointment_booking_rest_response(
                    data : null,
                    code : 404,
                    message : esc_html__('Extra service not found', 'rox-appointment-booking'),
                    headers : ['status' => 404]
                );
            }

            return rox_appointment_booking_rest_response(
                data : $this->getExtraServiceData($extraService, true),
                message : esc_html__('Extra service retrieved successfully', 'rox-appointment-booking')
            );
        }

        $page = $request->get_param('page') ?? 1;
        $per_page = $request->get_param('per_page') ?? 10;
        $search = $request->get_param('search') ?? '';
        $status = $request->get_param('status') ?? '';
        $mode = $request->get_param('mode') ?? 'default';
        $service_id = $request->get_param('service_id') ?? '';

        $query = $extraServiceModelClass::query();

        if (!empty($service_id)) {
            $pivotTable = ROX_APPOINTMENT_BOOKING_DB_PREFIX . ROX_APPOINTMENT_BOOKING_PREFIX . '_service_extra_service';
            $extraServiceTable = (new $extraServiceModelClass())->getTable();

            $query->select([$extraServiceTable . '.*'])
                  ->join($pivotTable, $pivotTable . '.extra_service_id', '=', $extraServiceTable . '.id')
                  ->where($pivotTable . '.service_id', $service_id);
        }

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($status)) {
            $query->where('status', $status);
        }

        $extraServices = $query->offset(($page - 1) * $per_page)
                              ->limit($per_page)
                              ->orderBy('created_at', 'DESC')
                              ->get();

        $data = [];
        
        if( $mode === 'list' ) {
            foreach ($extraServices as $extraService) {
                $data[] = $this->getExtraServiceData($extraService, false, $mode);
            }
        } else {
            foreach ($extraServices as $extraService) {
                $data[] = $this->getExtraServiceData($extraService);
            }
        }

        return rox_appointment_booking_rest_response(
            data : $data,
            message : esc_html__('Extra services retrieved successfully', 'rox-appointment-booking'),
            options: [
                'action_items' => [
                    [
                        'key' => 'edit',
                        'label' => esc_html__('Edit', 'rox-appointment-booking'),
                        'icon' => 'edit',
                        'route' => '/services/extra-service/',
                    ],
                    [
                        'key' => 'delete',
                        'label' => esc_html__('Delete', 'rox-appointment-booking'),
                        'icon' => 'trash',
                    ],
                ],
                'api' => [
                    'delete' => get_rest_url(null, ROX_APPOINTMENT_BOOKING_TEXT_DOMAIN . '/v1/extra-service'),
                ],
            ],
        );
    }
}
