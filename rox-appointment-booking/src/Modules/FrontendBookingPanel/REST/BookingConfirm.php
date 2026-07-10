<?php
namespace RoxAppointmentBooking\Modules\FrontendBookingPanel\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\FrontendBookingPanel\Services\CustomerService;
use RoxAppointmentBooking\Modules\FrontendBookingPanel\Services\PaymentProcessingService;
use RoxAppointmentBooking\Modules\FrontendBookingPanel\Services\AppointmentService;
use RoxAppointmentBooking\Modules\FrontendBookingPanel\Services\BookingEmailService;
use Exception;

/**
 * Class BookingConfirm
 *
 * @package RoxAppointmentBooking\Modules\FrontendBookingPanel\REST
 * @description Handles public frontend booking confirmation requests.
 */
class BookingConfirm extends AbstractREST
{
    /**
     * Whether this REST endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * REST route for public booking confirmation.
     *
     * @var string
     */
    public static string $route = '/public/booking';

    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/public/booking';

    /**
     * Get the HTTP methods allowed for this route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'POST';
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
     * Handle the REST API request.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $params = $request->get_json_params();
        $appointmentIds = [];
        $orderId = null;
        $appointmentService = new AppointmentService();

        try {
            // Validate Pro custom fields before any DB writes. The Pro plugin
            // answers this filter with a WP_Error to reject the booking; with Pro
            // inactive it stays null and the booking proceeds as before.
            $customFieldError = apply_filters('rox_appointment_booking_validate_custom_fields', null, $params);
            if (is_wp_error($customFieldError)) {
                return $customFieldError;
            }

            $customerResult = (new CustomerService())->saveCustomer($params);
            if (is_wp_error($customerResult)) {
                return $customerResult;
            }

            if (!$customerResult || !isset($customerResult['id'])) {
                return new WP_Error(
                    'customer_save_failed',
                    esc_html__('Unable to process customer information. Please try again or contact support.', 'rox-appointment-booking'),
                    ['status' => 500]
                );
            }

            $appointmentResult = $appointmentService->saveAppointments($params, $customerResult['id']);
            if (is_wp_error($appointmentResult)) {
                return $appointmentResult;
            }

            if (!$appointmentResult || empty($appointmentResult['appointment_ids'])) {
                return new WP_Error(
                    'appointment_save_failed',
                    esc_html__('Unable to book the appointment. Please try again or contact support.', 'rox-appointment-booking'),
                    ['status' => 500]
                );
            }

            $appointmentIds = $appointmentResult['appointment_ids'];

            $orderResult = $appointmentService->saveOrder($customerResult['id'], $appointmentIds, $params);
            if (is_wp_error($orderResult)) {
                $appointmentService->deleteAppointments($appointmentIds);
                return $orderResult;
            }

            if (!$orderResult || !isset($orderResult['order_id'])) {
                $appointmentService->deleteAppointments($appointmentIds);
                return new WP_Error(
                    'order_create_failed',
                    esc_html__('Unable to create the order. Please contact support.', 'rox-appointment-booking'),
                    ['status' => 500]
                );
            }

            $orderId = $orderResult['order_id'];

            $paymentResult = (new PaymentProcessingService())->processPayment($params, $customerResult['id'], $orderId);
            if (is_wp_error($paymentResult)) {
                $appointmentService->deleteOrder($orderId);
                $appointmentService->deleteAppointments($appointmentIds);
                return $paymentResult;
            }

            if (!$paymentResult) {
                $appointmentService->deleteOrder($orderId);
                $appointmentService->deleteAppointments($appointmentIds);
                return new WP_Error(
                    'payment_create_failed',
                    esc_html__('Payment processing failed. Please contact support.', 'rox-appointment-booking'),
                    ['status' => 500]
                );
            }

            $appointmentService->updatePaymentStatus($appointmentIds, $orderId, $paymentResult);

            // Let the Pro plugin persist submitted custom field values for this order.
            do_action('rox_appointment_booking_after_booking_confirmed', $params, $customerResult['id'], $orderId, $appointmentIds);

            (new BookingEmailService())->sendBookingConfirmation($customerResult, $appointmentResult, $paymentResult, array_merge($params, ['order_id' => $orderId]));

            return rox_appointment_booking_rest_response(
                data: [
                    'customer' => $customerResult,
                    'appointments' => $appointmentResult,
                    'order' => $orderResult,
                    'payment' => $paymentResult
                ],
                message: esc_html__('Your appointment has been booked successfully. A confirmation has been sent.', 'rox-appointment-booking')
            );

        } catch (Exception $e) {
            if ($orderId) {
                $appointmentService->deleteOrder($orderId);
            }
            if (!empty($appointmentIds)) {
                $appointmentService->deleteAppointments($appointmentIds);
            }
            return new WP_Error(
                'booking_failed',
                esc_html__('Something went wrong while processing your booking. Please try again or contact support.', 'rox-appointment-booking'),
                ['status' => 500]
            );
        }
    }
}
