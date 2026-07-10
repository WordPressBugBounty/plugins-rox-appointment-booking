<?php
namespace RoxAppointmentBooking\Modules\FrontendBookingPanel\Services;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Service\Data\ServiceModel;
use RoxAppointmentBooking\Modules\Agent\Data\AgentModel;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\Order\Data\OrderModel;
use RoxAppointmentBooking\Modules\Category\Data\CategoryModel;
use RoxAppointmentBookingPro\Modules\Location\Data\LocationModel;

/**
 * Class BookingEmailService
 *
 * @package RoxAppointmentBooking\Modules\FrontendBookingPanel\Services
 * @description Handles frontend booking confirmation emails.
 */
class BookingEmailService
{
    /**
     * Send booking confirmation email to the customer.
     *
     * @param array $customer Customer data.
     * @param array $appointmentResult Appointment creation result.
     * @param array $payment Payment result.
     * @param array $params Booking request parameters.
     * @return void
     */
    public function sendBookingConfirmation(array $customer, array $appointmentResult, array $payment, array $params): void
    {
        // Get order from params
        $orderId = $params['order_id'] ?? null;
        $order = $orderId ? OrderModel::find($orderId) : null;
        $appointments = $this->getAppointments($appointmentResult['appointment_ids'] ?? []);

        $emailSettings = get_option('rox_appointment_booking_email_settings', []);
        if (empty($emailSettings)) {
            $emailSettings = get_option('rox_appointment_booking_notification_settings', []);
        }
        $senderEmail = sanitize_email($emailSettings['sender_email'] ?? '');
        $senderName = sanitize_text_field($emailSettings['sender_name'] ?? '');
        $headers = [];
        if (!empty($senderEmail)) {
            $headers[] = empty($senderName)
                ? sprintf('From: %1$s', $senderEmail)
                : sprintf('From: %1$s <%2$s>', $senderName, $senderEmail);
        }

        $email_body = $this->buildEmailBody($customer, $order, $appointments, $payment);

        wp_mail(
            $customer['email'],
            // translators: %s = order number or N/A if not available
            sprintf(esc_html__('Booking Confirmation - Order %s', 'rox-appointment-booking'), esc_html($order ? $order->getOrderNumber() : 'N/A')),
            $email_body,
            $headers
        );
    }

    /**
     * Get appointments by IDs.
     *
     * @param array $appointmentIds Appointment IDs.
     * @return array
     */
    private function getAppointments(array $appointmentIds): array
    {
        $appointments = [];
        foreach ($appointmentIds as $id) {
            $appointment = AppointmentModel::find($id);
            if ($appointment) {
                $appointments[] = $appointment;
            }
        }
        return $appointments;
    }

    /**
     * Build the booking confirmation email body.
     *
     * @param array $customer Customer data.
     * @param OrderModel|null $order Order model instance.
     * @param array $appointments Appointment model instances.
     * @param array $payment Payment result.
     * @return string
     */
    private function buildEmailBody(array $customer, ?OrderModel $order, array $appointments, array $payment): string
    {
        $appointmentsHtml = '';
        foreach ($appointments as $appointment) {
            $service = ServiceModel::find($appointment->service_id);
            $agent = AgentModel::find($appointment->agent_id);
            $category = CategoryModel::find($appointment->category_id);
            $location = class_exists(\RoxAppointmentBookingPro\Modules\Location\Data\LocationModel::class)
                ? LocationModel::find($appointment->location_id)
                : null;
            
            $appointmentsHtml .= sprintf(
                '<tr>' .
                '<td style="padding: 8px; border: 1px solid #ddd;">%s</td>' .
                '<td style="padding: 8px; border: 1px solid #ddd;">%s</td>' .
                '<td style="padding: 8px; border: 1px solid #ddd;">%s</td>' .
                '<td style="padding: 8px; border: 1px solid #ddd;">%s</td>' .
                '<td style="padding: 8px; border: 1px solid #ddd;">%s</td>' .
                '<td style="padding: 8px; border: 1px solid #ddd;">%s</td>' .
                '<td style="padding: 8px; border: 1px solid #ddd;">%s</td>' .
                '</tr>',
                $service ? $service->title : 'N/A',
                $category ? $category->title : 'N/A',
                $agent ? $agent->getFullName() : 'N/A',
                $location ? $location->title : 'N/A',
                $appointment->date ?? 'N/A',
                gmdate('h:i A', strtotime($appointment->start_time)) . ' - ' . gmdate('h:i A', strtotime($appointment->end_time)),
                ucfirst($appointment->status ?? 'Pending')
            );
        }

        $customFieldsHtml = $this->buildCustomFieldsHtml($order);

        return sprintf(
            '<h2>Booking Confirmation</h2>' .
            '<p>Dear <strong>%s %s</strong>,</p>' .
            '<p>Your booking has been confirmed successfully.</p>' .
            '<h3>Order Details:</h3>' .
            '<table style="border-collapse: collapse; width: 100%%;">' .
            '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>Order Number:</strong></td><td style="padding: 8px; border: 1px solid #ddd;">%s</td></tr>' .
            '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>Order Status:</strong></td><td style="padding: 8px; border: 1px solid #ddd;">%s</td></tr>' .
            '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>Total Appointments:</strong></td><td style="padding: 8px; border: 1px solid #ddd;">%d</td></tr>' .
            '</table>' .
            '<h3>Appointments:</h3>' .
            '<table style="border-collapse: collapse; width: 100%%;">' .
            '<tr style="background-color: #f5f5f5;">' .
            '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Service</th>' .
            '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Category</th>' .
            '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Agent</th>' .
            '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Location</th>' .
            '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Date</th>' .
            '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Time</th>' .
            '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Status</th>' .
            '</tr>' .
            '%s' .
            '</table>' .
            '<h3>Payment Details:</h3>' .
            '<table style="border-collapse: collapse; width: 100%%;">' .
            '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>Transaction ID:</strong></td><td style="padding: 8px; border: 1px solid #ddd;">%s</td></tr>' .
            '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>Amount:</strong></td><td style="padding: 8px; border: 1px solid #ddd;">%s</td></tr>' .
            '</table>' .
            '<h3>Customer Information:</h3>' .
            '<table style="border-collapse: collapse; width: 100%%;">' .
            '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>Name:</strong></td><td style="padding: 8px; border: 1px solid #ddd;">%s %s</td></tr>' .
            '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>Email:</strong></td><td style="padding: 8px; border: 1px solid #ddd;">%s</td></tr>' .
            '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>Phone:</strong></td><td style="padding: 8px; border: 1px solid #ddd;">%s</td></tr>' .
            '</table>' .
            '%s' .
            '<p>Thank you for your booking!</p>',
            $customer['first_name'],
            $customer['last_name'],
            $order ? $order->getOrderNumber() : 'N/A',
            $order ? ucfirst($order->order_status) : 'N/A',
            count($appointments),
            $appointmentsHtml,
            $payment['transaction_id'] ?? 'N/A',
            $order ? $order->getFormattedTotal() : '$0.00',
            $customer['first_name'],
            $customer['last_name'],
            $customer['email'],
            $customer['phone'],
            $customFieldsHtml
        );
    }

    /**
     * Build the "Custom Information" email block from the order's stored custom
     * field values. Returns '' when there are none (so the block is omitted).
     *
     * @param OrderModel|null $order Order model instance.
     * @return string
     */
    private function buildCustomFieldsHtml(?OrderModel $order): string
    {
        if (!$order) {
            return '';
        }

        $fields = apply_filters('rox_appointment_booking_order_custom_fields', [], $order->getID());
        if (empty($fields) || !is_array($fields)) {
            return '';
        }

        $rows = '';
        foreach ($fields as $field) {
            $label = $field['field_label'] ?? '';
            $value = $field['value'] ?? '';

            if (($field['field_type'] ?? '') === 'checkbox') {
                $value = ($value === '1' || $value === 1 || $value === true)
                    ? esc_html__('Yes', 'rox-appointment-booking')
                    : esc_html__('No', 'rox-appointment-booking');
            }

            $rows .= sprintf(
                '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>%s</strong></td><td style="padding: 8px; border: 1px solid #ddd;">%s</td></tr>',
                esc_html($label),
                esc_html($value)
            );
        }

        return '<h3>' . esc_html__('Custom Information', 'rox-appointment-booking') . '</h3>' .
            '<table style="border-collapse: collapse; width: 100%;">' . $rows . '</table>';
    }
}
