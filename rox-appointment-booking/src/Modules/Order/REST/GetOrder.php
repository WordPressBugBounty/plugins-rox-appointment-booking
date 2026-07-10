<?php

namespace RoxAppointmentBooking\Modules\Order\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Supports\Traits\RoxAppointmentBookingFilter;
use RoxAppointmentBooking\Modules\Order\Services\OrderService;
use RoxAppointmentBooking\Modules\Order\Data\OrderModel;
use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;
use RoxAppointmentBooking\Modules\Service\Data\ServiceModel;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\Agent\Data\AgentModel;
use RoxAppointmentBooking\Modules\Payment\Data\PaymentModel;

/**
 * Class GetOrder
 *
 * @package RoxAppointmentBooking\Modules\Order\REST
 * @description Handles retrieving orders via REST API.
 */
class GetOrder extends AbstractREST
{
    use RoxAppointmentBookingFilter;

    /**
     * Whether this REST endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for this endpoint.
     *
     * @var string
     */
    public static string $route = 'order(?:/(?P<id>\d+))?';

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
     * Get fields that can be searched by the filter trait.
     *
     * @return array
     */
    protected function getSearchableFields(): array
    {
        return ['id','currency','payment_method','payment_status', 'order_status'];
    }

    /**
     * Format order data for display
     * 
     * @param OrderModel $order
     * @return array
     */
    protected function getOrderData(OrderModel $order): array
    {
        $currencyCode = $order->currency ?: (rox_appointment_booking_payment_settings('payment_currency') ?? 'USD');
        $currencySymbol = rox_appointment_booking__get_currency_symbol($currencyCode);

        // Get customer details
        $customerDetails = null;
        if ($order->customer_id) {
            $customer = CustomerModel::find($order->customer_id);
            if ($customer) {
                $customerDetails = [
                    'id' => $customer->getID(),
                    'name' => $customer->getFullName(),
                    'email' => $customer->email ?? '',
                    'thumbnail' => $customer->thumbnail_id ? wp_get_attachment_url($customer->thumbnail_id) : ''
                ];
            }
        }

        // Get appointment, service and agent data from booking_ids
        $serviceTitle = '';
        $appointmentDate = '';
        $agentData = null;
        $appointment = null;
        $bookingIds = $order->getBookingIds();
        
        if (!empty($bookingIds)) {
            $appointmentId = $bookingIds[0]; // Get first appointment
            $appointment = AppointmentModel::find($appointmentId);
            
            if ($appointment) {
                // Get service title
                if ($appointment->service_id) {
                    $service = ServiceModel::find($appointment->service_id);
                    if ($service) {
                        $serviceTitle = $service->title ?? '';
                    }
                }
                
                // Format appointment date
                if ($appointment->date) {
                    $appointmentDate = wp_date('F j, Y', strtotime($appointment->date));
                    if ($appointment->start_time) {
                        $appointmentDate .= ' - ' . wp_date('g:i A', strtotime($appointment->start_time));
                    }
                }
                
                // Get agent data
                if ($appointment->agent_id) {
                    $agent = AgentModel::find($appointment->agent_id);
                    if ($agent) {
                        $agentData = [
                            'id' => $agent->getID(),
                            'name' => $agent->getFullName(),
                            'email' => $agent->email ?? '',
                            'thumbnail' => $agent->thumbnail_id ? wp_get_attachment_url($agent->thumbnail_id) : ''
                        ];
                    }
                }
            }
        }

        $displayOrderDate = $this->formatOrderDateTime($order);

        $OrderStatus = $this->resolveOrderStatus($order, $appointment);
        $totalAmount = $this->calculateOrderTotal($order, $appointment);

        return [
            'id' => $order->getID(),
            'customer' => $customerDetails,
            'service_title' => $serviceTitle,
            'appointment_date' => $appointmentDate,
            'created_at' => $displayOrderDate,
            'payment_method' => $order->payment_method,
            'order_status' => rox_appointment_booking_get_order_status_label($OrderStatus),
            'confirmation' => $order->getOrderNumber(),
            'agent' => $agentData,
            'payment_status' => $order->payment_status,
            'total_amount' => number_format($totalAmount, 2, '.', ''),
            'total_amount_formatted' => $currencySymbol . number_format($totalAmount, 2, '.', ''),
            'currency' => $currencyCode,
            'currency_symbol' => $currencySymbol,
        ];
    }

    /**
     * Get detailed order data for single order view
     * 
     * @param OrderModel $order
     * @return array
     */
    protected function getSingleOrderData(OrderModel $order): array
    {
        $currencyCode = $order->currency ?: (rox_appointment_booking_payment_settings('payment_currency') ?? 'USD');
        $currencySymbol = rox_appointment_booking__get_currency_symbol($currencyCode);
        $bookingIds = $order->getBookingIds();
        $appointment = null;
        $OrderStatus = $this->resolveOrderStatus($order);
        $displayOrderDate = $this->formatOrderDateTime($order);

        $breakdown = $this->buildOrderBreakdown($order, $currencySymbol);

        // Resolve transaction ID: order record first, fallback to payment record.
        $transactionId = $order->payment_transaction_id ?? '';
        if (empty($transactionId)) {
            $payment = PaymentModel::query()->where('order_id', $order->getID())->first();
            if ($payment) {
                $transactionId = $payment->transaction_id ?? '';
            }
        }

        // Only real Stripe transaction IDs (not synthetic pay-later 'pl_' IDs) have a Stripe Dashboard entry.
        $stripeDashboardUrl = '';
        if (str_starts_with($transactionId, 'pi_') || str_starts_with($transactionId, 'ch_')) {
            $stripeMode = rox_appointment_booking_payment_settings('stripe_mode', 'test');
            $stripeDashboardUrl = $stripeMode === 'live'
                ? "https://dashboard.stripe.com/payments/{$transactionId}"
                : "https://dashboard.stripe.com/test/payments/{$transactionId}";
        }

        // Build order information
        $orderInfo = [];
        if (!empty($bookingIds)) {
            $appointment = AppointmentModel::find($bookingIds[0]);
            if ($appointment) {
                $service = ServiceModel::find($appointment->service_id);
                $OrderStatus = $this->resolveOrderStatus($order, $appointment);

                $orderInfo = [
                    'service'        => $service->title ?? '',
                    'date_time'      => $displayOrderDate,
                    'payment_method' => $order->payment_method ?? '',
                ];
            }
        }

        // Build customer information
        $customerInfo = [];
        if ($order->customer_id) {
            $customer = CustomerModel::find($order->customer_id);
            if ($customer) {
                $avatarUrl = '';
                if ($customer->thumbnail_id) {
                    $avatarUrl = wp_get_attachment_url($customer->thumbnail_id) ?: '';
                }
                if (!$avatarUrl && !empty($customer->email)) {
                    $avatarUrl = get_avatar_url($customer->email) ?: '';
                }
                $customerInfo = [
                    'id'             => $customer->getID(),
                    'name'           => $customer->getFullName(),
                    'avatar'         => $avatarUrl,
                    'email'          => $customer->email ?? '',
                    'phone'          => $customer->phone ?? '',
                    'customer_notes' => $order->customer_notes ?? '',
                    'internal_notes' => $order->internal_notes ?? '',
                ];
            }
        }

        return [
            'id' => $order->getID(),
            'order_number' => $order->getOrderNumber(),
            'payment_status' => $order->payment_status,
            'order_status' => $OrderStatus,
            'order_status_label' => rox_appointment_booking_get_order_status_label($OrderStatus),
            'appointments' => $breakdown['appointments'],
            'price_breakdown' => [
                'items' => $this->applyDiscountToBreakdown($breakdown['price_items'], $order, $currencySymbol),
                'total' => number_format((float) ($order->total_amount ?: $breakdown['price_total']), 2, '.', ''),
                'total_formatted' => $currencySymbol . number_format((float) ($order->total_amount ?: $breakdown['price_total']), 2, '.', ''),
            ],
            'subtotal' => number_format($order->subtotal ?? 0, 2, '.', ''),
            'discount_amount' => number_format($order->discount_amount ?? 0, 2, '.', ''),
            'tax_amount' => number_format($order->tax_amount ?? 0, 2, '.', ''),
            'total_amount' => number_format($order->total_amount, 2, '.', ''),
            'total_amount_formatted' => $currencySymbol . number_format($order->total_amount, 2, '.', ''),
            'currency' => $currencyCode,
            'currency_symbol' => $currencySymbol,
            'payment_method' => $order->payment_method,
            'payment_transaction_id' => $transactionId,
            'stripe_dashboard_url' => $stripeDashboardUrl,
            'order_date' => $order->order_date ? gmdate('c', strtotime($order->order_date)) : null,
            'fulfillment_date' => $order->fulfillment_date,
            'cancellation_date' => $order->cancellation_date,
            'refund_amount' => number_format($order->refund_amount ?? 0, 2, '.', ''),
            'refund_date' => $order->refund_date,
            'refund_reason' => $order->refund_reason,
            'internal_notes' => $order->internal_notes,
            'order_info_section' => $orderInfo,
            'customer_info_section' => $customerInfo,
            // Pro custom field values submitted with this order (empty unless Pro
            // answers the filter).
            'custom_fields' => apply_filters('rox_appointment_booking_order_custom_fields', [], $order->getID()),
            "props" => [
                "layout" => [
                    "gutter" => [24, 24]
                ]
            ]
        ];
    }

    /**
     * Build the per-appointment breakdown and aggregated price line items for an order.
     *
     * @param OrderModel $order Order model instance.
     * @param string $currencySymbol Currency symbol for formatting amounts.
     * @return array{appointments: array, price_items: array, price_total: float}
     */
    private function buildOrderBreakdown(OrderModel $order, string $currencySymbol): array
    {
        $appointments = [];
        $priceItems = [];
        $priceTotal = 0.0;

        foreach ($order->getBookingIds() as $bookingId) {
            $appointment = AppointmentModel::find($bookingId);
            if (!$appointment) {
                continue;
            }

            $services = [];

            // Main service line.
            $serviceDurationMinutes = 0;
            if (!empty($appointment->service_id)) {
                $service = ServiceModel::find($appointment->service_id);
                if ($service) {
                    $serviceDurationMinutes = (int) ($service->duration ?? 0);
                    $servicePrice = (float) ($service->price ?? 0);
                    $services[] = [
                        'title' => $service->title ?? '',
                        'type' => 'main',
                        'duration' => $service->getFormattedDuration(),
                        'duration_minutes' => $serviceDurationMinutes,
                        'price' => $servicePrice,
                        'price_formatted' => $currencySymbol . number_format($servicePrice, 2, '.', ''),
                    ];
                    $priceItems[] = [
                        'title' => $service->title ?? '',
                        'type' => 'main',
                        'price' => $servicePrice,
                        'price_formatted' => $currencySymbol . number_format($servicePrice, 2, '.', ''),
                    ];
                    $priceTotal += $servicePrice;
                }
            }

            // Extra service lines.
            $extraServiceMinutes = 0;
            $extraServiceIds = $this->normalizeExtraServiceIds($appointment->extra_services ?? []);
            foreach ($this->buildExtraServiceDetails($extraServiceIds) as $extra) {
                $extraServiceMinutes += (int) $extra['duration_minutes'];
                $services[] = [
                    'title' => $extra['title'],
                    'type' => 'extra',
                    'duration' => $extra['duration'],
                    'duration_minutes' => $extra['duration_minutes'],
                    'price' => $extra['price'],
                    'price_formatted' => $currencySymbol . number_format($extra['price'], 2, '.', ''),
                ];
                $priceItems[] = [
                    'title' => $extra['title'],
                    'type' => 'extra',
                    'price' => $extra['price'],
                    'price_formatted' => $currencySymbol . number_format($extra['price'], 2, '.', ''),
                ];
                $priceTotal += $extra['price'];
            }

            $totalDurationMinutes = $this->resolveAppointmentDuration(
                $appointment,
                $serviceDurationMinutes + $extraServiceMinutes
            );

            // Agent details.
            $agentData = null;
            if (!empty($appointment->agent_id)) {
                $agent = AgentModel::find($appointment->agent_id);
                if ($agent) {
                    $agentData = [
                        'id' => $agent->getID(),
                        'name' => $agent->getFullName(),
                        'thumbnail' => $agent->thumbnail_id ? wp_get_attachment_url($agent->thumbnail_id) : '',
                    ];
                }
            }

            $appointments[] = [
                'id' => $appointment->getID(),
                'agent' => $agentData,
                'date_time' => $this->formatAppointmentDateTime($appointment),
                'services' => $services,
                'total_duration' => $this->formatDurationFromMinutes($totalDurationMinutes),
                'total_duration_minutes' => $totalDurationMinutes,
            ];
        }

        return [
            'appointments' => $appointments,
            'price_items' => $priceItems,
            'price_total' => $priceTotal,
        ];
    }

    /**
     * Normalize an appointment's extra_services value into a list of integer IDs.
     *
     * @param mixed $extraServices Raw extra_services value (array or JSON string).
     * @return array
     */
    private function normalizeExtraServiceIds($extraServices): array
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

        $ids = [];
        foreach ($extraServices as $extraService) {
            if (is_array($extraService)) {
                $extraService = $extraService['id'] ?? $extraService['extra_service_id'] ?? null;
            }

            $extraServiceId = intval($extraService);
            if ($extraServiceId > 0) {
                $ids[] = $extraServiceId;
            }
        }

        return array_values($ids);
    }

    /**
     * Build extra service detail payloads from a list of IDs.
     *
     * @param array $extraServiceIds Extra service IDs.
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
            $extraServices[] = [
                'id' => $extraService->getID(),
                'title' => $extraService->title ?? '',
                'duration_minutes' => (int) ($extraService->duration ?? 0),
                'duration' => $extraService->getFormattedDuration(),
                'price' => (float) ($extraService->price ?? 0),
            ];
        }

        return $extraServices;
    }

    /**
     * Format an appointment's date and time label for the view response.
     *
     * @param AppointmentModel $appointment Appointment model instance.
     * @return string
     */
    private function formatAppointmentDateTime(AppointmentModel $appointment): string
    {
        $date = $appointment->date ?? '';
        $startTime = $appointment->start_time ?? '';

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
     * Resolve an appointment's total duration in minutes.
     *
     * @param AppointmentModel $appointment Appointment model instance.
     * @param int $fallbackMinutes Fallback minutes when start/end times are unavailable.
     * @return int
     */
    private function resolveAppointmentDuration(AppointmentModel $appointment, int $fallbackMinutes): int
    {
        $startTime = $appointment->start_time ?? '';
        $endTime = $appointment->end_time ?? '';

        if ($startTime && $endTime) {
            $diff = (int) round((strtotime($endTime) - strtotime($startTime)) / 60);
            if ($diff > 0) {
                return $diff;
            }
        }

        return max(0, $fallbackMinutes);
    }

    /**
     * Format minutes into a readable duration string.
     *
     * @param int $minutes Duration in minutes.
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

    /**
     * Resolve the order status from order and appointment data.
     *
     * @param OrderModel $order Order model instance.
     * @param AppointmentModel|null $appointment Appointment model instance.
     * @return string
     */
    private function resolveOrderStatus(OrderModel $order, ?AppointmentModel $appointment = null): string
    {
        $orderStatus = $this->normalizeStatusValue($order->order_status ?? '');

        if ($orderStatus !== '') {
            return $orderStatus;
        }

        if (!$appointment) {
            $bookingIds = $order->getBookingIds();
            if (!empty($bookingIds)) {
                $appointment = AppointmentModel::find($bookingIds[0]);
            }
        }

        if (!$appointment) {
            return $orderStatus ?: 'pending_payment';
        }

        $appointmentStatus = $this->normalizeStatusValue($appointment->status ?? '');
        if ($appointmentStatus === '' || $appointmentStatus === 'pending') {
            return $orderStatus ?: 'pending_payment';
        }

        return $appointmentStatus;
    }

    /**
     * Normalize a status value for display and comparison.
     *
     * @param string $status Status value.
     * @return string
     */
    private function normalizeStatusValue(string $status): string
    {
        $status = strtolower(trim($status));

        if ($status === 'canceled') {
            return 'cancelled';
        }

        return $status;
    }

    /**
     * Append a discount line item when the order has a coupon discount applied.
     *
     * @param array       $items         Existing price items from buildOrderBreakdown.
     * @param OrderModel  $order         Order model instance.
     * @param string      $currencySymbol Currency symbol for formatting.
     * @return array
     */
    private function applyDiscountToBreakdown(array $items, OrderModel $order, string $currencySymbol): array
    {
        $discountAmount = (float) ($order->discount_amount ?? 0);
        if ($discountAmount <= 0) {
            return $items;
        }

        $couponCode = $order->coupon_code ?? '';
        $label      = $couponCode
            ? sprintf(esc_html__('Coupon (%s)', 'rox-appointment-booking'), $couponCode)
            : esc_html__('Discount', 'rox-appointment-booking');

        $items[] = [
            'title'          => $label,
            'type'           => 'discount',
            'price'          => -$discountAmount,
            'price_formatted' => '-' . $currencySymbol . number_format($discountAmount, 2, '.', ''),
        ];

        return $items;
    }

    /**
     * Calculate order total amount based on associated appointment, service and extra services
     *
     * @param OrderModel $order
     * @param AppointmentModel|null $appointment
     * @return float
     */
    private function calculateOrderTotal(OrderModel $order, ?AppointmentModel $appointment = null): float
    {
        $serviceTotal = 0.0;
        $extraTotal = 0.0;

        if ($appointment) {
            $attendees = max(1, (int) ($appointment->total_attendees ?? 1));

            if (!empty($appointment->service_id)) {
                $service = ServiceModel::find($appointment->service_id);
                if ($service && is_numeric($service->price)) {
                    $serviceTotal = (float) $service->price * $attendees;
                }
            }

            $extraIds = $appointment->extra_services ?? [];
            if (is_string($extraIds)) {
                $decoded = json_decode($extraIds, true);
                if (is_array($decoded)) {
                    $extraIds = $decoded;
                }
            }

            if (is_array($extraIds)) {
                $extraServiceModelClass = '\\RoxAppointmentBookingPro\\Modules\\ExtraService\\Data\\ExtraServiceModel';
                if (class_exists($extraServiceModelClass)) {
                    foreach ($extraIds as $extraId) {
                        $extraService = $extraServiceModelClass::find((int) $extraId);
                        if ($extraService && is_numeric($extraService->price)) {
                            $extraTotal += (float) $extraService->price;
                        }
                    }
                }
            }
        }

        // Prefer the stored total_amount (accurate when coupons/discounts are applied).
        // Fall back to computing from service prices for legacy orders without a stored total.
        $storedTotal = (float) ($order->total_amount ?? 0);
        if ($storedTotal > 0) {
            return $storedTotal;
        }

        $computed = $serviceTotal + $extraTotal;
        return $computed > 0 ? $computed : 0.0;
    }

    private function formatOrderDateTime(OrderModel $order): string
    {
        $timestamp = $this->resolveOrderTimestamp($order);

        if (!$timestamp) {
            return '';
        }

        return wp_date('F j, Y - g:i A', $timestamp);
    }

    /**
     * Resolve the best available order timestamp.
     *
     * @param OrderModel $order Order model instance.
     * @return int|null
     */
    private function resolveOrderTimestamp(OrderModel $order): ?int
    {
        $orderDate = trim((string) ($order->order_date ?? ''));
        $createdAt = trim((string) ($order->created_at ?? ''));

        if ($orderDate !== '') {
            $orderTimestamp = strtotime($orderDate);
            if ($orderTimestamp !== false) {
                return $orderTimestamp;
            }
        }

        if ($createdAt !== '') {
            $createdTimestamp = strtotime($createdAt);
            if ($createdTimestamp !== false) {
                return $createdTimestamp;
            }
        }

        return null;
    }

    /**
     * Handle the REST API request.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $orderService = new OrderService();
            $id = $request->get_param('id');

            if ($id) {
                // Get single order
                $order = $orderService->getOrder($id);
                
                if (!$order) {
                    return new WP_Error(
                        'order_not_found',
                        esc_html__('Order not found', 'rox-appointment-booking'),
                        ['status' => 404]
                    );
                }

                return rox_appointment_booking_rest_response(
                    data: $this->getSingleOrderData($order),
                    message: [
                        'success' => [
                            esc_html__('Order retrieved successfully', 'rox-appointment-booking')
                        ]
                    ]
                );
            }

            // Get multiple orders with filtering and pagination
            $pagination = $this->getPaginationParams($request);
            
            $query = OrderModel::query();
            $query = $this->applyFilters($request, $query);
            
            $total = $query->count();
            $orders = $query->offset(($pagination['page'] - 1) * $pagination['per_page'])
                          ->limit($pagination['per_page'])
                          ->orderBy('created_at', 'DESC')
                          ->get();

            // Format orders data for display
            $formattedOrders = [];
            foreach ($orders as $order) {
                $formattedOrders[] = $this->getOrderData($order);
            }

            return rox_appointment_booking_rest_response(
                data: $formattedOrders,
                message: [
                    'success' => [
                        sprintf(
                            // translators: %d = total number of orders found
                            esc_html__('Found %d orders', 'rox-appointment-booking'),
                            $total
                        )
                    ]
                ],
                options: $this->buildPaginationMeta($total, $pagination['page'], $pagination['per_page'])
            );

        } catch (\Exception $e) {
            return new WP_Error(
                'order_retrieval_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
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
}
