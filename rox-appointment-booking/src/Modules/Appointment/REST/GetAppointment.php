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

            // A booking the caller placed as a CUSTOMER is theirs to read even
            // though they are not the serving agent — this is what the panel's
            // "My Bookings" page opens. The customer id is resolved from the
            // login, never from a request parameter, so this cannot be aimed
            // at anyone else's record. Resolved up here because it decides both
            // access below and how much of the response survives the strip.
            $currentCustomerId = AppointmentService::getCurrentCustomerId();
            $isOwnBooking = $currentCustomerId
                && (int) ($appointmentData['customer_id'] ?? 0) === $currentCustomerId;

            if (!Security::canManageBookings()) {
                // Otherwise only an agent may read a single record, and only their
                // own. Note the checks are NOT conditional on $isAgentUser: a panel
                // user who is not an agent must be denied, not waved through to a
                // full record (which carries the customer's name/email and the
                // order totals).
                if (!$isOwnBooking) {
                    if (!$isAgentUser) {
                        return rox_appointment_booking_rest_response(
                            data: null,
                            code: 403,
                            message: esc_html__('You are not allowed to view this appointment.', 'rox-appointment-booking'),
                            headers: ['status' => 403]
                        );
                    }

                    if (!$currentAgentId) {
                        return rox_appointment_booking_rest_response(
                            data: null,
                            code: 403,
                            message: esc_html__('Agent account not found for this user.', 'rox-appointment-booking'),
                            headers: ['status' => 403]
                        );
                    }

                    if ((int) ($appointmentData['agent_id'] ?? 0) !== $currentAgentId) {
                        return rox_appointment_booking_rest_response(
                            data: null,
                            code: 403,
                            message: esc_html__('You are not allowed to view this appointment.', 'rox-appointment-booking'),
                            headers: ['status' => 403]
                        );
                    }
                }
            }

            $customer = !empty($appointmentData['customer_id']) ? $customerService->getCustomer((int) $appointmentData['customer_id']) : null;
            $agent = !empty($appointmentData['agent_id']) ? $agentService->getAgent((int) $appointmentData['agent_id']) : null;
            $service = !empty($appointmentData['service_id']) ? ServiceModel::find((int) $appointmentData['service_id']) : null;

            $extraServiceIds = $this->getFormattedExtraServices($appointmentData['extra_services'] ?? []);
            $extraServiceDetails = $this->buildExtraServiceDetails($extraServiceIds);
            $extraServiceMinutes = $this->getExtraServiceMinutes($extraServiceDetails);

            // This appointment's OWN price (service + its own extras) — not the
            // order's total, which can include other appointments when the
            // order groups several bookings (CLAUDE.md §12.10). The Price
            // Breakdown card only ever lists this one appointment's line
            // items, so its "Total" must match, not the whole order's sum.
            $appointmentOwnTotal = ($service ? (float) ($service->price ?? 0) : 0)
                + array_sum(array_column($extraServiceDetails, 'price'));
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
                'location_option' => $this->getLocationOption($appointmentData['location_id'] ?? null), // {value,label} so the form's Location select shows a label even without the Pro list endpoint
                'category_id' => $appointmentData['category_id'] ?? null,
                'service_id' => $appointmentData['service_id'] ?? null,
                'extra_services' => $extraServiceIds,
                'agent_id' => $appointmentData['agent_id'] ?? null,
                'status' => strtolower($appointmentData['status'] ?? ''),
                'payment_status' => $appointmentData['payment_status'] ?? '',
                'customer_id' => $appointmentData['customer_id'] ?? null,
                'internal_notes' => $appointmentData['internal_notes'] ?? '',
                // Shaped for the form's checkbox group, which takes an array of
                // checked values — same convention as GetCustomer's send_notifications.
                'send_notification' => !empty($appointmentData['send_notification']) ? ["1"] : [],
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
                // Agents get a Customer Details card instead of Order Details, so
                // they need a way to contact the customer for this appointment.
                'customer_phone' => $customer ? ($customer->phone ?? '') : '',
                'order_date' => $order ? gmdate('F j, Y', strtotime($order->created_at)) : '',
                'payment_method' => $order ? $this->formatPaymentMethodLabel($order->payment_method ?? '') : '',
                'order_status' => $orderStatus,
                'subtotal' => $appointmentOwnTotal,
                'service_price' => $service ? (float) ($service->price ?? 0) : 0,
                'discount_amount' => $order ? (float) ($order->discount_amount ?? 0) : 0,
                'coupon_code' => $order ? ($order->coupon_code ?? '') : '',
                'tax_amount' => $order ? (float) ($order->tax_amount ?? 0) : 0,
                // Net the (still order-wide, not per-line) discount out of this
                // appointment's own price so the card's own numbers stay
                // internally consistent — line items minus the Discount row
                // shown above actually equals this Total.
                'total_amount' => max(0, $appointmentOwnTotal - ($order ? (float) ($order->discount_amount ?? 0) : 0)),
                'payment_status_label' => rox_appointment_booking_get_payment_status_label($appointmentData['payment_status'] ?? ''),
                'deposit_amount' => $order ? (float) ($order->deposit_amount ?? 0) : 0,
                'amount_due_later' => $order ? (float) ($order->amount_due_later ?? 0) : 0,
                'agent_name' => $agent ? $agent->full_name : '',
                // Lets the panel tell its two cases apart: a booking the caller
                // made as a customer (My Bookings — prices shown, reschedule
                // offered) versus one they are assigned to serve.
                'is_own_booking' => $isOwnBooking,
                // Answered by Pro's Google Calendar integration, if connected and
                // this appointment synced with a Meet link; empty otherwise.
                'meet_link' => apply_filters('rox_appointment_booking_meet_link', '', $appointmentData['id'] ?? 0),
            ];

            // A panel user never sees the ORDER: the Order Details card and the
            // "Edit Order" action behind it are admin territory either way.
            //
            // The MONEY is a separate question. On a booking the caller placed as
            // a customer it is their own spend, so it stays — that is what the
            // panel's "My Bookings" page opens. On an appointment they are merely
            // assigned to serve, an agent's view is the appointment plus the
            // customer to contact, never what was charged. Stripped here rather
            // than only hidden in the UI, so the data never reaches them.
            if (!Security::canManageBookings()) {
                $stripFields = [
                    'order_id',
                    'order_number',
                    'order_date',
                    'order_status',
                    'payment_method',
                ];

                if (!$isOwnBooking) {
                    $stripFields = array_merge($stripFields, [
                        'payment_status',
                        'payment_status_label',
                        'subtotal',
                        'service_price',
                        'discount_amount',
                        'coupon_code',
                        'tax_amount',
                        'total_amount',
                        'deposit_amount',
                        'amount_due_later',
                    ]);

                    // Extra services stay (their duration is part of the appointment),
                    // but without their prices.
                    $response['extra_service_details'] = array_map(
                        function ($extraService) {
                            unset($extraService['price']);
                            return $extraService;
                        },
                        $response['extra_service_details']
                    );
                }

                $response = array_diff_key($response, array_flip($stripFields));
            }

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
        
        // Opt-in "My Bookings" scope: what the caller booked AS A CUSTOMER, not
        // what they are assigned to serve. Only the scope is client-supplied — the
        // customer id itself comes from the login, so this cannot be pointed at
        // another person's bookings.
        $scope = sanitize_key((string) $request->get_param('scope'));

        $query = \RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel::query();
        if ($scope === 'customer') {
            $currentCustomerId = AppointmentService::getCurrentCustomerId();

            // No customer row simply means they have never booked anything — an
            // empty list is the correct answer, unlike the agent branch below where
            // a missing agent record is a misconfiguration worth reporting.
            if (!$currentCustomerId) {
                return rox_appointment_booking_rest_response(
                    data: [],
                    message: esc_html__('Appointments retrieved successfully', 'rox-appointment-booking'),
                    options: $this->buildPaginationMeta(0, $pagination['page'], $pagination['per_page'])
                );
            }

            $query->where('customer_id', $currentCustomerId);
        } elseif (!Security::canManageBookings()) {
            // Only an agent may see a scoped list. Anything else that gets past the
            // panel permission check must not fall through to an UNFILTERED list.
            if (!$isAgentUser) {
                return rox_appointment_booking_rest_response(
                    data: null,
                    code: 403,
                    message: esc_html__('You are not allowed to view appointments.', 'rox-appointment-booking'),
                    headers: ['status' => 403]
                );
            }

            // No agent record linked to this login: say so instead of returning an
            // empty list, which reads as "you have no appointments".
            if (!$currentAgentId) {
                return rox_appointment_booking_rest_response(
                    data: null,
                    code: 403,
                    message: esc_html__('Agent account not found for this user.', 'rox-appointment-booking'),
                    headers: ['status' => 403]
                );
            }

            $query->where('agent_id', $currentAgentId);
        }
        $query = $this->applyFilters($request, $query);
        
        $filteredTotal = $query->count();
        $appointments = $query->offset(($pagination['page'] - 1) * $pagination['per_page'])
                                ->limit($pagination['per_page'])
                                ->orderBy('created_at', $order)
                                ->get();

        $appointmentsData = $appointments->toArray();
        // Titles for the list's Location column, or null when the Locations
        // module isn't usable (no Pro / module switched off / no active
        // location). Null means the `location` key is left off the rows
        // entirely, which is how the appointments table decides to drop the
        // column instead of rendering an empty one.
        $locationTitles = $this->getLocationTitleMap($appointmentsData);
        $groupedAppointments = [];
        $groupId = 1;
        foreach ($appointmentsData as $appointment) {
            $response = $this->buildAppointmentResponse($appointment, $customerService, $agentService, $locationTitles);
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
     * Get a {value,label} pair for a location ID.
     *
     * Reads the `rox_appointment_location` table directly (created by core on
     * activation) instead of going through the Pro-only Location REST endpoint,
     * so the label resolves even when the Pro plugin isn't active.
     *
     * @param int|null $locationId
     * @return array|null
     */
    private function getLocationOption($locationId): ?array
    {
        if (empty($locationId)) {
            return null;
        }

        global $wpdb;
        $table = ROX_APPOINTMENT_BOOKING_DB_PREFIX . ROX_APPOINTMENT_BOOKING_PREFIX . '_location';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- No core Location model exists; table is created by core regardless of Pro.
        $title = $wpdb->get_var($wpdb->prepare("SELECT title FROM $table WHERE id = %d", $locationId));

        if ($title === null) {
            return null;
        }

        return [
            'value' => (int) $locationId,
            'label' => $title,
        ];
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
     * @param array|null $locationTitles Location id => title map, or null when the Locations module isn't usable.
     * @return array
     */
    private function buildAppointmentResponse($appointmentData, $customerService, $agentService, ?array $locationTitles = null): array
    {
        $customer = isset($appointmentData['customer_id']) ? $customerService->getCustomer($appointmentData['customer_id']) : null;
        $agent = isset($appointmentData['agent_id']) ? $agentService->getAgent($appointmentData['agent_id']) : null;
        $serviceTitle = isset($appointmentData['service_id']) ? ServiceService::getServiceTitleById($appointmentData['service_id'] ?? null) : '';

        $response = [
            'id' => $appointmentData['id'] ?? null,
            'time' => isset($appointmentData['start_time']) ? gmdate('g:i A', strtotime($appointmentData['start_time'])) : null,
            'service' => $serviceTitle,
            'customer' => $this->getCustomerDetails($customer),
            'agent' => $this->getAgentDetails($agent),
            'duration' => ( isset( $appointmentData['start_time'] ) && isset( $appointmentData['end_time'] ) ) ? ( round( ( strtotime( $appointmentData['end_time'] ) - strtotime( $appointmentData['start_time'] ) ) / 60 ) ) . ' mins' : '',
            'status' => strtolower($appointmentData['status'] ?? ''),
            'created_at' => isset($appointmentData['created_at']) ? gmdate('Y-m-d H:i:s', strtotime($appointmentData['created_at'])) : null
        ];

        // Only carried when the module is usable — see getLocationTitleMap().
        // A row without a location still gets the key (as an empty string) so
        // the column stays consistent across the whole page.
        if ($locationTitles !== null) {
            $locationId = (int) ($appointmentData['location_id'] ?? 0);
            $response['location'] = $locationId ? ($locationTitles[$locationId] ?? '') : '';
        }

        return $response;
    }

    /**
     * Build the location id => title map for the appointment list rows.
     *
     * Returns null when the Locations module isn't usable, which tells
     * buildAppointmentResponse() to leave the `location` key off entirely.
     * "Usable" means the same three things the booking panel checks before it
     * opens on the location step: Pro is active, the Locations module switch is
     * on, and at least one ACTIVE location exists — an inactive-only install has
     * nothing bookable to show.
     *
     * Reads the `rox_appointment_location` table directly for the same reason
     * getLocationOption() does: the table is created by core, while the Location
     * model ships with Pro.
     *
     * @param array $appointmentsData
     * @return array<int, string>|null
     */
    private function getLocationTitleMap(array $appointmentsData): ?array
    {
        if (!rox_appointment_booking_is_pro_user()) {
            return null;
        }

        $location_settings = get_option('rox_appointment_booking_location_settings', []);
        if (!filter_var($location_settings['location_module_enable'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        global $wpdb;
        $table = ROX_APPOINTMENT_BOOKING_DB_PREFIX . ROX_APPOINTMENT_BOOKING_PREFIX . '_location';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- No core Location model exists; table is created by core regardless of Pro.
        $activeCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status = %s", 'active'));
        if ($activeCount < 1) {
            return null;
        }

        $locationIds = array_values(array_unique(array_filter(array_map(
            static function ($appointment) {
                return (int) ($appointment['location_id'] ?? 0);
            },
            $appointmentsData
        ))));

        // The column is still shown (this page's bookings simply predate the
        // module, or were made without a location), just with empty cells.
        if (empty($locationIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($locationIds), '%d'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Placeholders are generated, values are passed through prepare().
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id, title FROM $table WHERE id IN ($placeholders)", $locationIds), ARRAY_A);

        $titles = [];
        foreach ((array) $rows as $row) {
            $titles[(int) $row['id']] = (string) $row['title'];
        }

        return $titles;
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
            'woocommerce' => 'WooCommerce',
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