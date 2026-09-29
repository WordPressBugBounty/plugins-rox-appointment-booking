<?php

namespace RoxAppointmentBooking\Modules\Email\Services;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Agent\Data\AgentModel;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\Category\Data\CategoryModel;
use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;
use RoxAppointmentBooking\Modules\Order\Data\OrderModel;
use RoxAppointmentBooking\Modules\Service\Data\ServiceModel;

use RoxAppointmentBookingPro\Modules\Location\Data\LocationModel;

/**
 * Class EmailPlaceholderResolver
 *
 * @package RoxAppointmentBooking\Modules\Email\Services
 * @description Builds the {token} => value map an e-mail template is rendered
 *              with, from a loosely-typed event context (customer_id | customer,
 *              agent_id | agent, appointment_id | appointment_ids, order_id,
 *              payment, appointment_status, old_/new_ date+time, username,
 *              set_password_url — all optional). EmailService escapes the
 *              scalars; the *_table tokens are trusted HTML inserted raw.
 */
class EmailPlaceholderResolver
{
    /**
     * Resolve every placeholder for an event.
     *
     * @param string $eventKey Event key.
     * @param array $context Event context.
     * @param string $recipientType Recipient type, e.g. 'customer'.
     * @return array<string, string> Token (with braces) => value.
     */
    public static function resolve(string $eventKey, array $context = [], string $recipientType = ''): array
    {
        $appointments = self::appointments($context);
        $first        = $appointments[0] ?? null;
        $order        = self::order($context);
        $customer     = self::customer($context, $first);
        $agent        = self::agent($context, $first);
        $readerZone   = self::readerZone($eventKey, $recipientType, $first);

        $map = array_merge(
            self::siteValues(),
            self::customerValues($customer),
            self::agentValues($agent),
            self::appointmentValues($appointments, $first, $context, $readerZone),
            self::orderValues($order, $context),
            self::tableValues($appointments, $order, $context, $readerZone),
            self::changeValues($context),
            self::accountValues($context),
            self::linkValues($first)
        );

        /**
         * Filter the placeholder map used to render an e-mail.
         *
         * @param array  $map           Token => value.
         * @param string $eventKey      Event key.
         * @param array  $context       Event context.
         * @param string $recipientType Recipient type.
         */
        return apply_filters('rox_appointment_booking_email_placeholders', $map, $eventKey, $context, $recipientType);
    }

    /* ---------------------------------------------------------------------
     * Context hydration.
     * ------------------------------------------------------------------ */

    /**
     * Load the appointments referenced by the context.
     *
     * @param array $context Event context.
     * @return AppointmentModel[]
     */
    private static function appointments(array $context): array
    {
        $ids = [];

        if (!empty($context['appointment_ids']) && is_array($context['appointment_ids'])) {
            $ids = $context['appointment_ids'];
        } elseif (!empty($context['appointment_id'])) {
            $ids = [$context['appointment_id']];
        }

        $appointments = [];

        foreach ($ids as $id) {
            $appointment = AppointmentModel::find((int) $id);
            if ($appointment) {
                $appointments[] = $appointment;
            }
        }

        return $appointments;
    }

    /**
     * Load the order referenced by the context.
     *
     * @param array $context Event context.
     * @return OrderModel|null
     */
    private static function order(array $context): ?OrderModel
    {
        $orderId = (int) ($context['order_id'] ?? 0);

        return $orderId > 0 ? OrderModel::find($orderId) : null;
    }

    /**
     * Resolve the customer as a plain array, preferring data the caller already
     * has over a database round-trip.
     *
     * @param array $context Event context.
     * @param AppointmentModel|null $first First appointment.
     * @return array<string, string>
     */
    private static function customer(array $context, ?AppointmentModel $first): array
    {
        if (!empty($context['customer']) && is_array($context['customer'])) {
            return $context['customer'];
        }

        $customerId = (int) ($context['customer_id'] ?? ($first->customer_id ?? 0));
        if ($customerId <= 0) {
            return [];
        }

        $customer = CustomerModel::find($customerId);
        if (!$customer) {
            return [];
        }

        return [
            'first_name' => (string) ($customer->first_name ?? ''),
            'last_name'  => (string) ($customer->last_name ?? ''),
            'email'      => (string) ($customer->email ?? ''),
            'phone'      => (string) ($customer->phone ?? ''),
        ];
    }

    /**
     * Resolve the agent as a plain array. Empty when the appointment has no
     * agent (Agent-Optional Booking).
     *
     * @param array $context Event context.
     * @param AppointmentModel|null $first First appointment.
     * @return array<string, string>
     */
    private static function agent(array $context, ?AppointmentModel $first): array
    {
        if (!empty($context['agent']) && is_array($context['agent'])) {
            return $context['agent'];
        }

        $agentId = (int) ($context['agent_id'] ?? ($first->agent_id ?? 0));
        if ($agentId <= 0) {
            return [];
        }

        $agent = AgentModel::find($agentId);
        if (!$agent) {
            return [];
        }

        return [
            'name'  => (string) $agent->getFullName(),
            'email' => (string) ($agent->email ?? ''),
            'phone' => (string) ($agent->phone ?? ''),
        ];
    }

    /* ---------------------------------------------------------------------
     * Value groups.
     * ------------------------------------------------------------------ */

    /**
     * Site + company tokens. Company details live in the General settings.
     *
     * @return array<string, string>
     */
    private static function siteValues(): array
    {
        return [
            '{site_name}'       => (string) get_bloginfo('name'),
            '{site_url}'        => (string) home_url(),
            '{company_name}'    => (string) rox_appointment_booking_general_settings('company_name', ''),
            '{company_phone}'   => (string) rox_appointment_booking_general_settings('company_phone', ''),
            '{company_email}'   => (string) rox_appointment_booking_general_settings('company_email', ''),
            '{company_address}' => (string) rox_appointment_booking_general_settings('company_address', ''),
        ];
    }

    /**
     * Customer tokens.
     *
     * @param array $customer Customer data.
     * @return array<string, string>
     */
    private static function customerValues(array $customer): array
    {
        $firstName = (string) ($customer['first_name'] ?? '');
        $lastName  = (string) ($customer['last_name'] ?? '');

        return [
            '{customer_name}'       => trim($firstName . ' ' . $lastName),
            '{customer_first_name}' => $firstName,
            '{customer_last_name}'  => $lastName,
            '{customer_email}'      => (string) ($customer['email'] ?? ''),
            '{customer_phone}'      => (string) ($customer['phone'] ?? ''),
        ];
    }

    /**
     * Agent tokens.
     *
     * @param array $agent Agent data.
     * @return array<string, string>
     */
    private static function agentValues(array $agent): array
    {
        return [
            '{agent_name}'  => (string) ($agent['name'] ?? ''),
            '{agent_email}' => (string) ($agent['email'] ?? ''),
            '{agent_phone}' => (string) ($agent['phone'] ?? ''),
        ];
    }

    /**
     * Appointment tokens, describing the first appointment of the set.
     *
     * @param AppointmentModel[] $appointments All appointments.
     * @param AppointmentModel|null $first First appointment.
     * @param array $context Event context.
     * @param string $readerZone Zone to read the times on, '' for site time.
     * @return array<string, string>
     */
    private static function appointmentValues(array $appointments, ?AppointmentModel $first, array $context, string $readerZone = ''): array
    {
        if (!$first) {
            return [
                '{service_name}'           => '',
                '{category_name}'          => '',
                '{location_name}'          => '',
                '{appointment_date}'       => '',
                '{appointment_start_time}' => '',
                '{appointment_end_time}'   => '',
                '{appointment_date_customer_timezone}'       => '',
                '{appointment_start_time_customer_timezone}' => '',
                '{customer_timezone}'       => '',
                '{customer_timezone_block}' => '',
                '{appointment_status}'     => (string) ($context['appointment_status'] ?? ''),
                '{appointment_id}'         => '',
                '{appointment_count}'      => '0',
                '{meet_link}'              => '',
                '{internal_notes}'         => '',
            ];
        }

        $service  = $first->service_id ? ServiceModel::find((int) $first->service_id) : null;
        $category = $first->category_id ? CategoryModel::find((int) $first->category_id) : null;
        $location = self::location($first);

        // Answered by Pro's Google Calendar integration when the appointment
        // synced with a Meet link; empty otherwise.
        $meetLink = apply_filters('rox_appointment_booking_meet_link', '', $first->id ?? 0);

        $status = (string) ($context['appointment_status'] ?? ($first->status ?? ''));

        $inCustomerZone = self::customerZoneValues($first);

        // Empty for every e-mail that stays on site time, so those keep the
        // original strtotime()/gmdate() formatting untouched.
        $onReaderClock = $readerZone === '' ? null : self::readOnZone($first, $readerZone);

        return [
            '{service_name}'           => $service ? (string) $service->title : '',
            '{category_name}'          => $category ? (string) $category->title : '',
            '{location_name}'          => $location ? (string) $location->title : '',
            // The appointment itself, read on the reader's clock when they have
            // one. A time on offer may have read longer — the service's "Show
            // buffer time in the slot's time" runs the times together so no gap
            // shows — but what is recorded is the booking.
            '{appointment_date}'       => $onReaderClock
                ? self::formatDateOn($onReaderClock['start'])
                : self::formatDate($first->date ?? ''),
            '{appointment_start_time}' => $onReaderClock
                ? $onReaderClock['start']->format('h:i A')
                : self::formatTime($first->start_time ?? ''),
            '{appointment_end_time}'   => $onReaderClock
                ? $onReaderClock['end']->format('h:i A')
                : self::formatTime($first->end_time ?? ''),
            '{appointment_status}'     => $status === '' ? '' : ucfirst(str_replace('_', ' ', $status)),
            '{appointment_date_customer_timezone}'       => $inCustomerZone['date'],
            '{appointment_start_time_customer_timezone}' => $inCustomerZone['start_time'],
            '{customer_timezone}'       => $inCustomerZone['zone'],
            '{customer_timezone_block}' => $inCustomerZone['block'],
            '{appointment_id}'         => (string) ($first->id ?? ''),
            '{appointment_count}'      => (string) count($appointments),
            '{meet_link}'              => (string) $meetLink,
            '{internal_notes}'         => (string) ($first->internal_notes ?? ''),
        ];
    }

    /**
     * Order + payment tokens.
     *
     * @param OrderModel|null $order Order model.
     * @param array $context Event context.
     * @return array<string, string>
     */
    private static function orderValues(?OrderModel $order, array $context): array
    {
        $payment = (array) ($context['payment'] ?? []);

        $paidAmount = (float) ($payment['amount'] ?? ($order?->amount_due_now ?? $order?->total_amount ?? 0));

        return [
            '{order_number}'    => $order ? (string) $order->getOrderNumber() : 'N/A',
            '{order_status}'    => $order ? ucfirst((string) $order->order_status) : 'N/A',
            '{order_total}'     => self::formatAmount($order, (float) ($order?->total_amount ?? 0)),
            '{amount_paid}'     => self::formatAmount($order, $paidAmount),
            '{amount_due}'      => self::formatAmount($order, (float) ($order?->amount_due_later ?? 0)),
            '{deposit_amount}'  => self::formatAmount($order, (float) ($order?->deposit_amount ?? 0)),
            '{payment_method}'  => $order ? (string) $order->payment_method : '',
            '{payment_status}'  => $order ? ucfirst((string) $order->payment_status) : '',
            '{transaction_id}'  => (string) ($payment['transaction_id'] ?? ($order?->payment_transaction_id ?? '')),
            '{refund_amount}'   => self::formatAmount($order, (float) ($context['refund_amount'] ?? ($order?->refund_amount ?? 0))),
            '{refund_reason}'   => (string) ($context['refund_reason'] ?? ($order?->refund_reason ?? '')),
        ];
    }

    /**
     * Reschedule tokens.
     *
     * @param array $context Event context.
     * @return array<string, string>
     */
    private static function changeValues(array $context): array
    {
        return [
            '{old_date}' => self::formatDate((string) ($context['old_date'] ?? '')),
            '{old_time}' => self::formatTime((string) ($context['old_time'] ?? '')),
            '{new_date}' => self::formatDate((string) ($context['new_date'] ?? '')),
            '{new_time}' => self::formatTime((string) ($context['new_time'] ?? '')),
        ];
    }

    /**
     * Account tokens.
     *
     * @param array $context Event context.
     * @return array<string, string>
     */
    private static function accountValues(array $context): array
    {
        return [
            '{username}'            => (string) ($context['username'] ?? ''),
            '{set_password_url}'    => (string) ($context['set_password_url'] ?? ''),
            '{login_url}'           => (string) wp_login_url(),
            '{new_password_block}'  => self::newPasswordBlock($context),
        ];
    }

    /**
     * The "your new password is X" paragraph, or an empty string when the caller
     * passed no password.
     *
     * A raw token (see EmailService::RAW_TOKENS) so the <strong> survives, which
     * means the value has to arrive already escaped — hence esc_html() on the
     * password here rather than in EmailService::substitute().
     *
     * @param array $context Event context.
     * @return string
     */
    private static function newPasswordBlock(array $context): string
    {
        $password = (string) ($context['new_password'] ?? '');

        if ($password === '') {
            return '';
        }

        return '<p>' . esc_html__('Your new password is:', 'rox-appointment-booking') .
            ' <strong>' . esc_html($password) . '</strong></p>' .
            '<p>' . esc_html__('Please sign in and change it as soon as you can.', 'rox-appointment-booking') . '</p>';
    }

    /**
     * Panel / dashboard link tokens.
     *
     * @param AppointmentModel|null $first First appointment.
     * @return array<string, string>
     */
    private static function linkValues(?AppointmentModel $first): array
    {
        $adminUrl = admin_url('admin.php?page=rox-appointment-booking');

        if ($first && !empty($first->id)) {
            $adminUrl .= '#/appointment/' . (int) $first->id;
        }

        return [
            '{my_bookings_url}'       => (string) rox_appointment_booking_dashboard_url(),
            '{admin_appointment_url}' => $adminUrl,
        ];
    }

    /* ---------------------------------------------------------------------
     * Rich HTML blocks.
     * ------------------------------------------------------------------ */

    /**
     * The three pre-built HTML table tokens.
     *
     * @param AppointmentModel[] $appointments Appointments.
     * @param OrderModel|null $order Order model.
     * @param array $context Event context.
     * @param string $readerZone Zone to read the times on, '' for site time.
     * @return array<string, string>
     */
    private static function tableValues(array $appointments, ?OrderModel $order, array $context, string $readerZone = ''): array
    {
        return [
            '{appointments_table}'    => self::buildAppointmentsTable($appointments, $readerZone),
            '{payment_details_table}' => self::buildPaymentDetailsTable($order, (array) ($context['payment'] ?? [])),
            '{custom_fields_table}'   => self::buildCustomFieldsTable($order),
            '{meet_link_block}'       => self::buildMeetLinkBlock($appointments),
        ];
    }

    /**
     * The video-call join link as a labelled link, or nothing at all.
     *
     * {meet_link} on its own resolves to a bare URL, which tells the reader
     * nothing about what they are clicking. This renders it with the name of
     * whichever integration produced it ("Join Zoom Meeting", "Join Google
     * Meet"), and returns an empty string when the appointment has no
     * meeting — so a template can carry the block unconditionally without
     * leaving an empty paragraph or a dead link on every other booking.
     *
     * @param AppointmentModel[] $appointments Appointments in this e-mail.
     * @return string
     */
    private static function buildMeetLinkBlock(array $appointments): string
    {
        $link = self::meetLinkAnchor($appointments[0] ?? null);

        return $link === '' ? '' : '<p>' . $link . '</p>';
    }

    /**
     * One appointment's join link as an anchor, or an empty string when it has
     * no meeting.
     *
     * target="_blank" is mostly decorative in e-mail — webmail opens links in a
     * new tab regardless and desktop clients hand off to the system browser —
     * but the handful of clients that do honour it should not navigate away
     * from the message. rel goes with it, as always.
     *
     * @param AppointmentModel|null $appointment
     * @return string
     */
    private static function meetLinkAnchor($appointment): string
    {
        $appointmentId = (int) ($appointment->id ?? 0);

        if ($appointmentId === 0) {
            return '';
        }

        $link = (string) apply_filters('rox_appointment_booking_meet_link', '', $appointmentId);
        if ($link === '') {
            return '';
        }

        $label = (string) apply_filters('rox_appointment_booking_meet_link_label', '', $appointmentId);
        if ($label === '') {
            $label = __('Join Video Call', 'rox-appointment-booking');
        }

        return sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url($link),
            esc_html($label)
        );
    }

    /**
     * The 7-column appointments table.
     *
     * The old "Video Call" column was dropped: it rendered "N/A" for every
     * booking that is not a Google Meet one. Templates that need the link
     * can still use the standalone {meet_link} token.
     *
     * @param AppointmentModel[] $appointments Appointments.
     * @param string $readerZone Zone to read the times on, '' for site time.
     * @return string
     */
    private static function buildAppointmentsTable(array $appointments, string $readerZone = ''): string
    {
        if (empty($appointments)) {
            return '';
        }

        // Only worth a column when something in this e-mail actually has a
        // link — otherwise every booking e-mail on a site with no video
        // integration grows a permanently empty eighth column.
        $meetAnchors = [];
        foreach ($appointments as $appointment) {
            $meetAnchors[(int) ($appointment->id ?? 0)] = self::meetLinkAnchor($appointment);
        }
        $hasMeetLinks = (bool) array_filter($meetAnchors);

        $rows = '';

        foreach ($appointments as $appointment) {
            $service  = ServiceModel::find($appointment->service_id);
            $agent    = AgentModel::find($appointment->agent_id);
            $category = CategoryModel::find($appointment->category_id);
            $location = self::location($appointment);

            // A shifted row can land on another day, so the date moves with the
            // clock; null keeps the original site-time rendering.
            $onReaderClock = $readerZone === '' ? null : self::readOnZone($appointment, $readerZone);

            $rows .= sprintf(
                '<tr>' .
                '<td style="padding: 8px; border: 1px solid #ddd;">%s</td>' .
                '<td style="padding: 8px; border: 1px solid #ddd;">%s</td>' .
                '<td style="padding: 8px; border: 1px solid #ddd;">%s</td>' .
                '<td style="padding: 8px; border: 1px solid #ddd;">%s</td>' .
                '<td style="padding: 8px; border: 1px solid #ddd;">%s</td>' .
                '<td style="padding: 8px; border: 1px solid #ddd;">%s</td>' .
                '<td style="padding: 8px; border: 1px solid #ddd;">%s</td>' .
                '%s' .
                '</tr>',
                $service ? $service->title : 'N/A',
                $category ? $category->title : 'N/A',
                $agent ? $agent->getFullName() : 'N/A',
                $location ? $location->title : 'N/A',
                $onReaderClock ? $onReaderClock['start']->format('Y-m-d') : ($appointment->date ?? 'N/A'),
                $onReaderClock
                    ? $onReaderClock['start']->format('h:i A') . ' - ' . $onReaderClock['end']->format('h:i A')
                    : gmdate('h:i A', strtotime($appointment->start_time)) . ' - ' . gmdate('h:i A', strtotime($appointment->end_time)),
                ucfirst($appointment->status ?? 'Pending'),
                // A whole cell, or nothing at all when no appointment in this
                // e-mail has a meeting — the header below is dropped to match.
                $hasMeetLinks
                    ? '<td style="padding: 8px; border: 1px solid #ddd;">' . ($meetAnchors[(int) ($appointment->id ?? 0)] ?? '') . '</td>'
                    : ''
            );
        }

        return '<table style="border-collapse: collapse; width: 100%;">' .
            '<tr style="background-color: #f5f5f5;">' .
            '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Service</th>' .
            '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Category</th>' .
            '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Agent</th>' .
            '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Location</th>' .
            '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Date</th>' .
            // Named only when the row is not on site time, so the reader knows
            // whose clock they are looking at.
            '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Time' .
            ($readerZone === '' ? '' : ' (' . esc_html($readerZone) . ')') . '</th>' .
            '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Status</th>' .
            ($hasMeetLinks
                ? '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">' . esc_html__('Video Call', 'rox-appointment-booking') . '</th>'
                : '') .
            '</tr>' .
            $rows .
            '</table>';
    }

    /**
     * The payment details table.
     *
     * Only a settled payment gets a receipt (transaction id + "Amount Paid").
     * A booking that has not been paid for yet - pay later, a failed charge, an
     * admin-created booking with no payment at all - would otherwise print the
     * synthetic 'pl_' transaction id under an "Amount Paid" label for money the
     * customer has not handed over, directly under an order status of
     * "Pending_payment". Those cases show the payment method and what is still
     * owed instead.
     *
     * @param OrderModel|null $order Order model.
     * @param array $payment Payment result.
     * @return string
     */
    private static function buildPaymentDetailsTable(?OrderModel $order, array $payment): string
    {
        $amount   = (float) ($payment['amount'] ?? ($order?->amount_due_now ?? $order?->total_amount ?? 0));
        $dueLater = (float) ($order?->amount_due_later ?? 0);

        if (self::isPaymentSettled($order, $payment)) {
            $rows = self::paymentRow(
                __('Transaction ID:', 'rox-appointment-booking'),
                (string) ($payment['transaction_id'] ?? 'N/A')
            ) . self::paymentRow(
                __('Amount Paid:', 'rox-appointment-booking'),
                self::formatAmount($order, $amount)
            );

            if ($dueLater > 0) {
                $rows .= self::paymentRow(
                    __('Deposit Paid:', 'rox-appointment-booking'),
                    self::formatAmount($order, (float) ($order?->deposit_amount ?? 0))
                ) . self::paymentRow(
                    __('Balance Due:', 'rox-appointment-booking'),
                    self::formatAmount($order, $dueLater)
                );
            }

            return '<table style="border-collapse: collapse; width: 100%;">' . $rows . '</table>';
        }

        $rows   = '';
        $method = self::paymentMethodLabel($order, $payment);

        if ($method !== '') {
            $rows .= self::paymentRow(__('Payment Method:', 'rox-appointment-booking'), $method);
        }

        $rows .= self::paymentRow(
            $dueLater > 0
                ? __('Amount Due Now:', 'rox-appointment-booking')
                : __('Amount Due:', 'rox-appointment-booking'),
            self::formatAmount($order, $amount)
        );

        if ($dueLater > 0) {
            $rows .= self::paymentRow(
                __('Balance Due Later:', 'rox-appointment-booking'),
                self::formatAmount($order, $dueLater)
            );
        }

        return '<table style="border-collapse: collapse; width: 100%;">' . $rows . '</table>';
    }

    /**
     * One label/value row of the payment details table.
     *
     * @param string $label Row label.
     * @param string $value Row value.
     * @return string
     */
    private static function paymentRow(string $label, string $value): string
    {
        return sprintf(
            '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>%s</strong></td>' .
            '<td style="padding: 8px; border: 1px solid #ddd;">%s</td></tr>',
            esc_html($label),
            esc_html($value)
        );
    }

    /**
     * Whether money has actually been taken for this order.
     *
     * The payment result carries its own status on the booking paths
     * ('succeeded' for a completed charge, 'pending' for pay later). The
     * payment_received / payment_failed events pass no status, and an
     * admin-created booking passes no payment at all - the order is then the
     * source of truth, since each of those paths updates it before the e-mail
     * event fires.
     *
     * @param OrderModel|null $order Order model.
     * @param array $payment Payment result.
     * @return bool
     */
    private static function isPaymentSettled(?OrderModel $order, array $payment): bool
    {
        $status = strtolower((string) ($payment['status'] ?? ''));

        if (in_array($status, ['paid', 'partially_paid', 'succeeded', 'completed'], true)) {
            return true;
        }

        if ($status !== '') {
            return false;
        }

        return in_array(
            strtolower((string) ($order?->payment_status ?? '')),
            ['paid', 'partially_paid'],
            true
        );
    }

    /**
     * Human-readable payment method for an unpaid order, or '' when unknown.
     *
     * @param OrderModel|null $order Order model.
     * @param array $payment Payment result.
     * @return string
     */
    private static function paymentMethodLabel(?OrderModel $order, array $payment): string
    {
        $method = strtolower((string) ($payment['payment_method'] ?? ($order?->payment_method ?? '')));

        return match ($method) {
            ''                   => '',
            'later', 'pay_later' => rox_appointment_booking_pay_later_label(__('Pay later', 'rox-appointment-booking')),
            'credit', 'stripe'   => __('Card', 'rox-appointment-booking'),
            'paypal'             => __('PayPal', 'rox-appointment-booking'),
            default              => ucfirst(str_replace('_', ' ', $method)),
        };
    }

    /**
     * The "Custom Information" block built from the order's stored custom field
     * values. Returns '' when there are none, so the block disappears.
     *
     * @param OrderModel|null $order Order model.
     * @return string
     */
    private static function buildCustomFieldsTable(?OrderModel $order): string
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

    /* ---------------------------------------------------------------------
     * Small helpers.
     * ------------------------------------------------------------------ */

    /**
     * The appointment's location, when the Pro Location module is present.
     *
     * @param AppointmentModel $appointment Appointment.
     * @return object|null
     */
    private static function location(AppointmentModel $appointment)
    {
        if (!class_exists(LocationModel::class)) {
            return null;
        }

        return LocationModel::find($appointment->location_id);
    }

    /**
     * Format a date for display in the site's date format.
     *
     * @param string $date Date string.
     * @return string
     */
    /**
     * The appointment read on the timezone the customer booked in.
     *
     * Bookings are stored in site-local wall time, so this converts for display
     * only. When the booking carries no timezone — every booking made before
     * the selector existed, and every booking on a site not using it — or when
     * it matches the site's own, the site values are returned unchanged and the
     * summary block resolves to nothing, so no e-mail gains an empty row.
     *
     * @param AppointmentModel $appointment First appointment of the booking.
     * @return array{date:string, start_time:string, zone:string, block:string}
     */
    private static function customerZoneValues(AppointmentModel $appointment): array
    {
        $zone = (string) ($appointment->customer_timezone ?? '');
        $site = wp_timezone_string();

        $values = [
            'date'       => self::formatDate($appointment->date ?? ''),
            'start_time' => self::formatTime($appointment->start_time ?? ''),
            'zone'       => $zone,
            'block'      => '',
        ];

        if ($zone === '' || $zone === $site || !function_exists('rox_appointment_booking_is_valid_timezone')) {
            return $values;
        }

        if (!rox_appointment_booking_is_valid_timezone($zone)) {
            $values['zone'] = '';
            return $values;
        }

        $onCustomerClock = self::readOnZone($appointment, $zone);

        if (!$onCustomerClock) {
            return $values;
        }

        $values['date']       = self::formatDateOn($onCustomerClock['start']);
        $values['start_time'] = $onCustomerClock['start']->format('h:i A');

        $values['block'] = '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>'
            . esc_html__('Your timezone:', 'rox-appointment-booking')
            . '</strong></td><td style="padding: 8px; border: 1px solid #ddd;">'
            . esc_html($values['date'] . ', ' . $values['start_time'] . ' (' . $zone . ')')
            . '</td></tr>';

        return $values;
    }

    /**
     * The clock one e-mail is written on.
     *
     * Only the customer's own booking-confirmation e-mail follows the timezone
     * the visitor booked in — every other e-mail, and every other recipient of
     * this one, stays on site time. Empty means "no conversion at all", so the
     * original formatting is used untouched.
     *
     * @param string $eventKey Event key.
     * @param string $recipientType Recipient type.
     * @param AppointmentModel|null $first First appointment of the booking.
     * @return string Zone name, or '' for site time.
     */
    private static function readerZone(string $eventKey, string $recipientType, ?AppointmentModel $first): string
    {
        if ($eventKey !== 'booking_confirmed' || $recipientType !== EmailTemplateRegistry::RECIPIENT_CUSTOMER) {
            return '';
        }

        if (!$first || !rox_appointment_booking_general_settings('timezone_selector_enable', false)) {
            return '';
        }

        $zone = (string) ($first->customer_timezone ?? '');

        if ($zone === '' || $zone === wp_timezone_string() || !function_exists('rox_appointment_booking_is_valid_timezone')) {
            return '';
        }

        return rox_appointment_booking_is_valid_timezone($zone) ? $zone : '';
    }

    /**
     * An appointment's stored site-local start and end, read on another zone.
     *
     * @param AppointmentModel $appointment Appointment.
     * @param string $zone Zone name.
     * @return array{start:\DateTime, end:\DateTime}|null Null when either end is unreadable.
     */
    private static function readOnZone(AppointmentModel $appointment, string $zone): ?array
    {
        try {
            $timezone = new \DateTimeZone($zone);

            $start = new \DateTime((string) $appointment->start_time, wp_timezone());
            $end   = new \DateTime((string) $appointment->end_time, wp_timezone());

            $start->setTimezone($timezone);
            $end->setTimezone($timezone);
        } catch (\Exception $e) {
            return null;
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * A converted instant's own wall date in the site's date format.
     *
     * date_i18n() always renders on the site's zone, so the instant's offset is
     * folded into the timestamp to get the date the reader actually sees.
     *
     * @param \DateTime $at Converted instant.
     * @return string
     */
    private static function formatDateOn(\DateTime $at): string
    {
        return date_i18n((string) get_option('date_format'), $at->getTimestamp() + $at->getOffset());
    }

    private static function formatDate(string $date): string
    {
        if ($date === '') {
            return '';
        }

        $timestamp = strtotime($date);

        return $timestamp ? date_i18n((string) get_option('date_format'), $timestamp) : $date;
    }


    /**
     * Format a time for display.
     *
     * @param string $time Time or datetime string.
     * @return string
     */
    private static function formatTime(string $time): string
    {
        if ($time === '') {
            return '';
        }

        $timestamp = strtotime($time);

        return $timestamp ? gmdate('h:i A', $timestamp) : $time;
    }

    /**
     * Format an amount with the same currency symbol as the rest of the plugin
     * (payment settings' currency, falling back to the order's own).
     *
     * @param OrderModel|null $order Order model.
     * @param float $amount Amount.
     * @return string
     */
    private static function formatAmount(?OrderModel $order, float $amount): string
    {
        $currencyCode = rox_appointment_booking_payment_settings('payment_currency') ?: ($order?->currency ?? 'USD');
        $symbol       = rox_appointment_booking__get_currency_symbol($currencyCode);

        return $symbol . number_format($amount, 2);
    }
}
