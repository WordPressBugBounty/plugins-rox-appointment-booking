<?php

namespace RoxAppointmentBooking\Modules\FrontendBookingPanel\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;

/**
 * Class CheckCustomerAvailability
 *
 * @package RoxAppointmentBooking\Modules\FrontendBookingPanel\REST
 * @description Pre-checks (before payment) whether the customer already has an
 * appointment overlapping any of the selected slots, so the panel can block the
 * Customer Info step early instead of failing at final submit.
 */
class CheckCustomerAvailability extends AbstractREST
{
    /**
     * Whether this REST endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * REST route for the customer availability pre-check.
     *
     * @var string
     */
    public static string $route = '/public/customer/check-availability';

    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/public/customer/check-availability';

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
     * Public endpoint — no auth required.
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public function permissionCheck(WP_REST_Request $request): bool
    {
        return true;
    }

    /**
     * Check for a customer time conflict against existing bookings.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $email = sanitize_email((string) $request->get_param('email'));
        $appointments = $request->get_param('appointments');

        // No email or no slots yet → nothing to check against.
        if (empty($email) || empty($appointments) || !is_array($appointments)) {
            return rox_appointment_booking_rest_response(
                data: ['conflict' => false]
            );
        }

        // A brand-new customer has no existing bookings → never a conflict.
        $customer = CustomerModel::query()->where('email', $email)->first();
        if (!$customer) {
            return rox_appointment_booking_rest_response(
                data: ['conflict' => false]
            );
        }

        $customerId = $customer->getID();

        foreach ($appointments as $appointment) {
            if (!is_array($appointment)) {
                continue;
            }

            $date = sanitize_text_field($appointment['date'] ?? '');
            $start_time = sanitize_text_field($appointment['start_time'] ?? '');
            $end_time = sanitize_text_field($appointment['end_time'] ?? '');

            if (empty($date) || empty($start_time) || empty($end_time)) {
                continue;
            }

            $full_start_time = $date . ' ' . $start_time;
            $full_end_time = $date . ' ' . $end_time;

            // Same customer cannot hold two appointments that overlap in time.
            $conflict = AppointmentModel::query()
                ->where('customer_id', $customerId)
                ->where('date', $date)
                ->whereNotIn('status', ['cancelled', 'canceled', 'rejected'])
                ->where(function ($query) use ($full_start_time, $full_end_time) {
                    $query->where('start_time', '<', $full_end_time)
                          ->where('end_time', '>', $full_start_time);
                })
                ->first();

            if ($conflict) {
                return rox_appointment_booking_rest_response(
                    data: [
                        'conflict' => true,
                        // __() rather than esc_html__(): this is JSON the panel
                        // renders as a text node, not markup, so HTML-escaping it
                        // here is never undone — the apostrophe reached the toast
                        // as a literal &#039;.
                        'message' => __('This slot doesn\'t have enough spots. Choose another time or reduce attendees.', 'rox-appointment-booking'),
                    ]
                );
            }
        }

        return rox_appointment_booking_rest_response(
            data: ['conflict' => false]
        );
    }
}
