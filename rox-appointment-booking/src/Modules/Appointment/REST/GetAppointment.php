<?php

namespace RoxAppointmentBooking\Modules\Appointment\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Supports\Security;
use RoxAppointmentBooking\Modules\Customer\Services\CustomerService;
use RoxAppointmentBooking\Modules\Agent\Services\AgentService;
use RoxAppointmentBooking\Modules\Service\Services\ServiceService;
use RoxAppointmentBooking\Modules\Service\Data\ServiceModel;
use RoxAppointmentBooking\Modules\Order\Data\OrderModel;
use RoxAppointmentBooking\Supports\Traits\RoxAppointmentBookingFilter;
use RoxAppointmentBooking\Modules\Appointment\Services\AppointmentService;

/**
 * Class GetAppointment
 * 
 * @package RoxAppointmentBooking\Modules\Appointment\REST
 * @description Provides the data of the appointment via REST API.
 */
class GetAppointment extends AbstractREST

{

    use RoxAppointmentBookingFilter;
    /**
     * Whether the endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for listing appointments.
     *
     * @var string
     */
    public static string $route = '/appointment(?:/(?P<id>\d+))?';
    /**
     * Usable route template for docs.
     *
     * @var string
     */
    public static string $usableRoute = '/appointment';

    /**
     * Get the methods allowed for this route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'GET';
    }

    /**
     * Defines searchable fields for filtering.
     *
     * @return array
     */
    protected function getSearchableFields(): array
    {
        return ['id', 'service_details', 'status', 'payment_status', 'internal_notes'];
    }

    /**
     * Handle the REST API request.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $appointmentId = isset($request) ? intval($request->get_param('id')) : null;
        $customerService = new CustomerService();
        $agentService = new AgentService();
        $currentCustomerId = AppointmentService::getCurrentCustomerId();
        $isCustomerUser = AppointmentService::isCustomerUser();
        $currentAgentId = AppointmentService::getCurrentAgentId();
        $isAgentUser = AppointmentService::isAgentUser();
        if ($appointmentId) {
            $appointment = \RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel::find($appointmentId);
            if (!$appointment) {
                return rox_appointment_booking_rest_response(
                    data: null,
                    code: 404,
                    message: esc_html__('Appointment not found', 'rox-appointment-booking'),
                    headers: ['status' => 404]
                );
            }
            $appointmentData = $appointment->toArray();

            if (!Security::canManageBookings()) {
                if ($isCustomerUser && !$currentCustomerId) {
                    return rox_appointment_booking_rest_response(
                        data: null,
                        code: 403,
                        message: esc_html__('Customer account not found for this user.', 'rox-appointment-booking'),
                        headers: ['status' => 403]
                    );
                }

                if ($isCustomerUser && $currentCustomerId && (int) ($appointmentData['customer_id'] ?? 0) !== $currentCustomerId) {
                    return rox_appointment_booking_rest_response(
                        data: null,
                        code: 403,
                        message: esc_html__('You are not allowed to view this appointment.', 'rox-appointment-booking'),
                        headers: ['status' => 403]
                    );
                }

                if ($isAgentUser && !$currentAgentId) {
                    return rox_appointment_booking_rest_response(
                        data: null,
                        code: 403,
                        message: esc_html__('Agent account not found for this user.', 'rox-appointment-booking'),
                        headers: ['status' => 403]
                    );
                }

                if ($isAgentUser && $currentAgentId && (int) ($appointmentData['agent_id'] ?? 0) !== $currentAgentId) {
                    return rox_appointment_booking_rest_response(
                        data: null,
                        code: 403,
                        message: esc_html__('You are not allowed to view this appointment.', 'rox-appointment-booking'),
                        headers: ['status' => 403]
                    );
                }
            }

            $customer = !empty($appointmentData['customer_id']) ? $customerService->getCustomer((int) $appointmentData['customer_id']) : null;
            $agent = !empty($appointmentData['agent_id']) ? $agentService->getAgent((int) $appointmentData['agent_id']) : null;
            $service = !empty($appointmentData['service_id']) ? ServiceModel::find((int) $appointmentData['service_id']) : null;

            $extraServiceIds = $this->getFormattedExtraServices($appointmentData['extra_services'] ?? []);
            $extraServiceDetails = $this->buildExtraServiceDetails($extraServiceIds);
            $extraServiceMinutes = $this->getExtraServiceMinutes($extraServiceDetails);
            $serviceDurationMinutes = $service ? (int) ($service->duration ?? 0) : 0;
            $appointmentDurationMinutes = $this->getAppointmentDurationMinutes($appointmentData, $serviceDurationMinutes, $extraServiceMinutes);

            $order = null;
            if (!empty($appointmentData['id'])) {
                $order = OrderModel::whereRaw('JSON_CONTAINS(booking_ids, %s)', [json_encode((int) $appointmentData['id'])])->first();
            }

            $orderStatus = $order ? $this->formatStatusLabel($order->order_status ?? '') : '';
            if ($orderStatus === '') {
                $orderStatus = $this->formatStatusLabel($appointmentData['status'] ?? '');
            }

            $response = [
                'id' => $appointmentData['id'] ?? null,
                'location_id' => $appointmentData['location_id'] ?? null,
                'category_id' => $appointmentData['category_id'] ?? null,
                'service_id' => $appointmentData['service_id'] ?? null,
                'extra_services' => $extraServiceIds,
                'agent_id' => $appointmentData['agent_id'] ?? null,
                'status' => strtolower($appointmentData['status'] ?? ''),
                'payment_status' => $appointmentData['payment_status'] ?? '',
                'customer_id' => $appointmentData['customer_id'] ?? null,
                'internal_notes' => $appointmentData['internal_notes'] ?? '',
                'send_notification' => $appointmentData['send_notification'] ?? 0,
                'check_availability' => [
                    'date' => $appointmentData['date'] ?? null,
                    'start_time' => $this->formatTimeForFrontend($appointmentData['start_time'] ?? null),
                    'end_time' => $this->formatTimeForFrontend($appointmentData['end_time'] ?? null),
                ],
                'date_time' => $this->formatAppointmentDateTime($appointmentData),
                'service_title' => $service ? ($service->title ?? '') : '',
                'total_duration' => $this->formatDurationFromMinutes($appointmentDurationMinutes),
                'service_duration' => $service ? $service->getFormattedDuration() : '',
                'service_duration_minutes' => $serviceDurationMinutes,
                'order_id' => $order ? $order->getID() : null,
                'extra_service_details' => $extraServiceDetails,
                'total_duration_minutes' => $appointmentDurationMinutes,
                'order_number' => $order ? $order->getOrderNumber() : '',
                'customer_name' => $customer ? $customer->full_name : '',
                'customer_email' => $customer ? $customer->email : '',
                'order_date' => $order ? gmdate('F j, Y', strtotime($order->created_at)) : '',
                'payment_method' => $order ? $this->formatPaymentMethodLabel($order->payment_method ?? '') : '',
                'order_status' => $orderStatus,
                'subtotal' => $order ? (float) ($order->subtotal ?? 0) : 0,
                'service_price' => $service ? (float) ($service->price ?? 0) : 0,
                'discount_amount' => $order ? (float) ($order->discount_amount ?? 0) : 0,
                'coupon_code' => $order ? ($order->coupon_code ?? '') : '',
                'tax_amount' => $order ? (float) ($order->tax_amount ?? 0) : 0,
                'total_amount' => $order ? (float) ($order->total_amount ?? 0) : 0,
                'agent_name' => $agent ? $agent->full_name : '',
            ];
            return rox_appointment_booking_rest_response(
                data: $response,
                message: esc_html__('Appointment retrieved successfully', 'rox-appointment-booking')
            );
        }

        // Handle appointment list request with pagination and filters
        $pagination = $this->getPaginationParams($request);
        $order = strtoupper(sanitize_text_field($request->get_param('order') ?? 'DESC'));
        if ($order !== 'ASC' && $order !== 'DESC') {
            $order = 'DESC';
        }
        
        $query = \RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel::query();
        if (!Security::canManageBookings()) {
            if ($isCustomerUser) {
                if ($currentCustomerId) {
                    $query->where('customer_id', $currentCustomerId);
                } else {
                    $query->where('id', 0);
                }
            } elseif ($isAgentUser) {
                if ($currentAgentId) {
                    $query->where('agent_id', $currentAgentId);
                } else {
                    $query->where('id', 0);
                }
            }
        }
        $query = $this->applyFilters($request, $query);
        
        $filteredTotal = $query->count();
        $appointments = $query->offset(($pagination['page'] - 1) * $pagination['per_page'])
                                ->limit($pagination['per_page'])
                                ->orderBy('created_at', $order)
                                ->get();

        $appointmentsData = $appointments->toArray();
        $groupedAppointments = [];
        $groupId = 1;
        foreach ($appointmentsData as $appointment) {
            $response = $this->buildAppointmentResponse($appointment, $customerService, $agentService);
            $dateKey = !empty($appointment['date'])
                ? $appointment['date']
                : (isset($appointment['start_time']) ? gmdate('Y-m-d', strtotime($appointment['start_time'])) : 'unknown');

            if (!isset($groupedAppointments[$dateKey])) {
                $groupedAppointments[$dateKey] = [
                    'id' => $groupId++,
                    'date' => $dateKey !== 'unknown' ? gmdate('F d, Y', strtotime($dateKey)) : '',
                    'appointments' => []
                ];
            }
            $groupedAppointments[$dateKey]['appointments'][] = $response;
        }
        $responseList = array_values($groupedAppointments);
        $meta = $this->buildPaginationMeta($filteredTotal, $pagination['page'], $pagination['per_page']);

        return rox_appointment_booking_rest_response(
            data: $responseList,
            message: esc_html__('Appointments retrieved successfully', 'rox-appointment-booking'),
            options: $meta
        );
    }

    /**
     * Check if the user has permission to access the endpoint.
     * 
     * @param WP_REST_Request $request
     * @return bool
     */
    public function permissionCheck(WP_REST_Request $request): bool
    {
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return false;
        }

        if (!is_user_logged_in()) {
            return false;
        }

        return Security::canAccessPanel();
    }

    /**
     * Get customer details array
     */
    /**
     * Builds customer details for response payload.
     *
     * @param mixed $customer
     * @return array
     */
    private function getCustomerDetails($customer): array
    {
        return [
            'name' => ($customer) ? $customer->full_name : '',
            'email' => ($customer) ? $customer->email : '',
            'thumbnail' => ($customer && $customer->thumbnail_id) ? wp_get_attachment_url($customer->thumbnail_id) : ''
        ];
    }

    /**
     * Get agent details array
     */
    /**
     * Builds agent details for response payload.
     *
     * @param mixed $agent
     * @return array
     */
    private function getAgentDetails($agent): array
    {
        return [
            'name' => ($agent) ? $agent->full_name : '',
            'thumbnail' => ($agent && $agent->thumbnail_id) ? wp_get_attachment_url($agent->thumbnail_id) : ''
        ];
    }

    /**
     * Format datetime for frontend (extract time in Y-m-d H:i:s format)
     * 
     * @param string|null $datetime
     * @return string|null
     */
    private function formatTimeForFrontend($datetime)
    {
        if (empty($datetime)) {
            return null;
        }

        try {
            $dt = new \DateTime($datetime);
            return $dt->format('h:i A');
        } catch (\Exception $e) {
            return $datetime;
        }
    }

    /**
     * Normalizes extra services list to integer IDs.
     *
     * @param array $extraServices
     * @return array
     */
    private function getFormattedExtraServices($extraServices): array
    {
        if (is_string($extraServices)) {
            $decoded = json_decode($extraServices, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $extraServices = $decoded;
            }
        }

        if (!is_array($extraServices)) {
            return [];
        }

        $formattedExtraServices = [];
        foreach ($extraServices as $extraService) {
            if (is_array($extraService)) {
                $extraService = $extraService['id'] ?? $extraService['extra_service_id'] ?? null;
            }

            $extraServiceId = intval($extraService);

            if ($extraServiceId > 0) {
                $formattedExtraServices[] = $extraServiceId;
            }
        }
        return array_values($formattedExtraServices);
    }

    /**
     * Build appointment response array
     */
    /**
     * Builds appointment list response item.
     *
     * @param array $appointmentData
     * @param CustomerService $customerService
     * @param AgentService $agentService
     * @return array
     */
    private function buildAppointmentResponse($appointmentData, $customerService, $agentService): array
    {
        $customer = isset($appointmentData['customer_id']) ? $customerService->getCustomer($appointmentData['customer_id']) : null;
        $agent = isset($appointmentData['agent_id']) ? $agentService->getAgent($appointmentData['agent_id']) : null;
        $serviceTitle = isset($appointmentData['service_id']) ? ServiceService::getServiceTitleById($appointmentData['service_id'] ?? null) : '';
        
        return [
            'id' => $appointmentData['id'] ?? null,
            'time' => isset($appointmentData['start_time']) ? gmdate('g:i A', strtotime($appointmentData['start_time'])) : null,
            'service' => $serviceTitle,
            'customer' => $this->getCustomerDetails($customer),
            'agent' => $this->getAgentDetails($agent),
            'duration' => ( isset( $appointmentData['start_time'] ) && isset( $appointmentData['end_time'] ) ) ? ( round( ( strtotime( $appointmentData['end_time'] ) - strtotime( $appointmentData['start_time'] ) ) / 60 ) ) . ' mins' : '',
            'status' => strtolower($appointmentData['status'] ?? ''),
            'created_at' => isset($appointmentData['created_at']) ? gmdate('Y-m-d H:i:s', strtotime($appointmentData['created_at'])) : null
        ];
    }

    /**
     * Build extra service details payloads.
     *
     * @param array $extraServiceIds
     * @return array
     */
    private function buildExtraServiceDetails(array $extraServiceIds): array
    {
        if (empty($extraServiceIds)) {
            return [];
        }

        $extraServiceModelClass = '\\RoxAppointmentBookingPro\\Modules\\ExtraService\\Data\\ExtraServiceModel';
        if (!class_exists($extraServiceModelClass)) {
            return [];
        }

        $extraServiceModels = $extraServiceModelClass::query()
            ->whereIn('id', $extraServiceIds)
            ->get();

        $extraServices = [];
        foreach ($extraServiceModels as $extraService) {
            $durationMinutes = (int) ($extraService->duration ?? 0);
            $extraServices[] = [
                'id' => $extraService->getID(),
                'title' => $extraService->title ?? '',
                'duration_minutes' => $durationMinutes,
                'duration' => $extraService->getFormattedDuration(),
                'price' => (float) ($extraService->price ?? 0),
            ];
        }

        return $extraServices;
    }

    /**
     * Sum extra service minutes from details payload.
     *
     * @param array $extraServices
     * @return int
     */
    private function getExtraServiceMinutes(array $extraServices): int
    {
        $totalMinutes = 0;
        foreach ($extraServices as $extraService) {
            $totalMinutes += (int) ($extraService['duration_minutes'] ?? 0);
        }
        return $totalMinutes;
    }

    /**
     * Formats appointment date and time label for the view response.
     *
     * @param array $appointmentData
     * @return string
     */
    private function formatAppointmentDateTime(array $appointmentData): string
    {
        $date = $appointmentData['date'] ?? '';
        $startTime = $appointmentData['start_time'] ?? '';

        if (!$date && $startTime) {
            $date = gmdate('Y-m-d', strtotime($startTime));
        }

        if (!$date) {
            return '';
        }

        $dateLabel = wp_date('F j, Y', strtotime($date));
        $timeLabel = $startTime ? wp_date('g:i A', strtotime($startTime)) : '';

        return $timeLabel ? $dateLabel . ' at ' . $timeLabel : $dateLabel;
    }

    /**
     * Formats order or appointment status into a display label.
     *
     * @param string $status
     * @return string
     */
    private function formatStatusLabel(string $status): string
    {
        $status = trim($status);
        if ($status === '') {
            return '';
        }

        $status = str_replace(['-', '_'], ' ', $status);
        return ucwords(strtolower($status));
    }

    /**
     * Formats payment method slug into a display label.
     *
     * @param string $method
     * @return string
     */
    private function formatPaymentMethodLabel(string $method): string
    {
        $method = trim($method);
        if ($method === '') {
            return '';
        }

        $map = [
            'pay_later' => 'On-site',
            'stripe' => 'Stripe',
        ];

        if (isset($map[$method])) {
            return $map[$method];
        }

        return ucwords(str_replace('_', ' ', $method));
    }

    /**
     * Calculates appointment duration in minutes.
     *
     * @param array $appointmentData
     * @param int $serviceDurationMinutes
     * @param int $extraServiceMinutes
     * @return int
     */
    private function getAppointmentDurationMinutes(array $appointmentData, int $serviceDurationMinutes, int $extraServiceMinutes): int
    {
        $startTime = $appointmentData['start_time'] ?? '';
        $endTime = $appointmentData['end_time'] ?? '';
        if ($startTime && $endTime) {
            $diff = (int) round((strtotime($endTime) - strtotime($startTime)) / 60);
            if ($diff > 0) {
                return $diff;
            }
        }

        return max(0, $serviceDurationMinutes + $extraServiceMinutes);
    }

    /**
     * Formats minutes into a readable duration string.
     *
     * @param int $minutes
     * @return string
     */
    private function formatDurationFromMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '';
        }

        if ($minutes >= 1440) {
            $days = floor($minutes / 1440);
            $remainingMinutes = $minutes % 1440;
            if ($remainingMinutes === 0) {
                return $days . 'd';
            }
            if ($remainingMinutes >= 60 && $remainingMinutes % 60 === 0) {
                $hours = (int) ($remainingMinutes / 60);
                return $days . 'd ' . $hours . 'h';
            }
            return $days . 'd';
        }

        if ($minutes >= 60) {
            $hours = floor($minutes / 60);
            $remainingMinutes = $minutes % 60;
            if ($remainingMinutes === 0) {
                return $hours . 'h';
            }
            return $hours . 'h ' . $remainingMinutes . 'm';
        }

        return $minutes . 'm';
    }
}