<?php
namespace RoxAppointmentBooking\Modules\FrontendBookingPanel\Services;

defined('ABSPATH') || exit;

/**
 * Class BookingEmailService
 *
 * @package RoxAppointmentBooking\Modules\FrontendBookingPanel\Services
 * @description Fires the booking-confirmation e-mail for the frontend booking
 *              panel. Recipients, templates and rendering live in Modules\Email —
 *              this only assembles the event context.
 */
class BookingEmailService
{
    /**
     * Send the booking confirmation e-mails — customer, agent and admin, each
     * subject to its own toggle in Settings → E-mail.
     *
     * @param array $customer Customer data.
     * @param array $appointmentResult Appointment creation result.
     * @param array $payment Payment result.
     * @param array $params Booking request parameters.
     * @return void
     */
    public function sendBookingConfirmation(array $customer, array $appointmentResult, array $payment, array $params): void
    {
        do_action(
            'rox_appointment_booking_email_event',
            'booking_confirmed',
            [
                'customer'        => $customer,
                'appointment_ids' => $appointmentResult['appointment_ids'] ?? [],
                'order_id'        => $params['order_id'] ?? null,
                'payment'         => $payment,
            ]
        );
    }
}
