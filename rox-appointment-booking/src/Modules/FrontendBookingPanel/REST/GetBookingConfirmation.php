<?php

namespace RoxAppointmentBooking\Modules\FrontendBookingPanel\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Order\Data\OrderModel;
use RoxAppointmentBooking\Modules\Payment\Data\PaymentModel;
use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\Service\Data\ServiceModel;
use RoxAppointmentBooking\Modules\Agent\Data\AgentModel;

defined('ABSPATH') || exit;

/**
 * Class GetBookingConfirmation
 *
 * @package RoxAppointmentBooking\Modules\FrontendBookingPanel\REST
 * @description Rebuilds the booking-confirmation view data for an order from
 *              the server, for flows that leave the booking widget entirely
 *              (e.g. a redirect-based payment gateway) and come back on a
 *              fresh page load with no client-side wizard state left. Shaped
 *              to match what `BookingService/OrderConfirmation.jsx` expects
 *              (the same component the normal in-widget confirmation uses).
 */
class GetBookingConfirmation extends AbstractREST
{
    /**
     * Whether this REST endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * REST route for the public booking confirmation.
     *
     * @var string
     */
    public static string $route = '/public/booking/confirmation/(?P<order_id>\d+)';

    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/public/booking/confirmation/';

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
     * Resolve extra-service id/title/price details (Pro-only model, so this
     * degrades to an empty list when Pro/ExtraService isn't available).
     * Mirrors GetAppointment::buildExtraServiceDetails() /
     * GetOrder::buildExtraServiceDetails().
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
            $extraServices[] = [
                'id' => $extraService->getID(),
                // OrderConfirmation.jsx reads both keys depending on where it renders the item.
                'name' => $extraService->title ?? '',
                'title' => $extraService->title ?? '',
                'price' => (float) ($extraService->price ?? 0),
            ];
        }

        return $extraServices;
    }

    /**
     * Decode the appointment's extra_services column into a plain id list.
     *
     * @param mixed $extraServices
     * @return array
     */
    private function getExtraServiceIds($extraServices): array
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
            $id = intval($extraService);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * Handle the REST API request.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $orderId = (int) $request->get_param('order_id');

        $order = OrderModel::find($orderId);
        if (!$order) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 404,
                message: esc_html__('Order not found', 'rox-appointment-booking'),
                headers: ['status' => 404]
            );
        }

        // Let Pro (or any add-on) require proof of ownership before handing
        // back a customer's name/email/phone — e.g. WooCommerce's own order
        // key. Defaults to allowed: an order id alone is already effectively
        // a one-time secret for the synchronous Stripe/Pay Later flow this
        // endpoint doesn't even serve.
        $accessGranted = apply_filters('rox_appointment_booking_booking_confirmation_access', true, $orderId, $request);
        if (!$accessGranted) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 403,
                message: esc_html__('You do not have access to this booking confirmation.', 'rox-appointment-booking'),
                headers: ['status' => 403]
            );
        }

        $customer = $order->customer_id ? CustomerModel::find($order->customer_id) : null;
        $payment = PaymentModel::query()->where('order_id', $orderId)->first();

        $bookingIds = $order->getBookingIds();
        $appointments = !empty($bookingIds) ? AppointmentModel::whereIn('id', $bookingIds)->get() : [];

        $locationModelClass = '\\RoxAppointmentBookingPro\\Modules\\Location\\Data\\LocationModel';
        $locationModelAvailable = class_exists($locationModelClass);

        $bookings = [];
        foreach ($appointments as $appointment) {
            $service = $appointment->service_id ? ServiceModel::find($appointment->service_id) : null;
            $agent = $appointment->agent_id ? AgentModel::find($appointment->agent_id) : null;
            $location = ($locationModelAvailable && $appointment->location_id)
                ? $locationModelClass::find($appointment->location_id)
                : null;

            $extraServiceIds = $this->getExtraServiceIds($appointment->extra_services ?? []);

            $bookings[] = [
                'id' => $appointment->getID(),
                'date' => $appointment->date,
                'start_time' => $appointment->start_time ? gmdate('H:i', strtotime($appointment->start_time)) : '',
                'service' => [
                    'name' => $service->title ?? '',
                    'price' => (float) ($service->price ?? 0),
                ],
                'employee' => [
                    'name' => $agent->full_name ?? '',
                    'thumbnail' => ($agent && $agent->thumbnail_id) ? wp_get_attachment_url($agent->thumbnail_id) : '',
                ],
                'extraServices' => $this->buildExtraServiceDetails($extraServiceIds),
                'location' => $location ? [
                    'name' => $location->getName(),
                    'iconPath' => $location->thumbnail_id ? wp_get_attachment_url($location->thumbnail_id) : '',
                ] : null,
            ];
        }

        $couponData = null;
        if (!empty($order->coupon_code) && (float) ($order->discount_amount ?? 0) > 0) {
            $couponData = [
                'couponCode' => $order->coupon_code,
                'discountAmount' => (float) $order->discount_amount,
            ];
        }

        return rox_appointment_booking_rest_response(
            data: [
                'customer' => [
                    'id' => $customer ? $customer->getID() : null,
                    'first_name' => $customer->first_name ?? '',
                    'last_name' => $customer->last_name ?? '',
                    'email' => $customer->email ?? '',
                    'phone' => $customer->phone ?? '',
                ],
                'bookings' => $bookings,
                'bookingResponse' => [
                    'order' => ['order_id' => $order->getID()],
                    'payment' => [
                        'status' => $payment->status ?? '',
                        'amount' => (float) ($payment->amount ?? $order->total_amount ?? 0),
                    ],
                ],
                'appliedCouponData' => $couponData,
            ],
            message: esc_html__('Booking confirmation retrieved successfully', 'rox-appointment-booking')
        );
    }
}
