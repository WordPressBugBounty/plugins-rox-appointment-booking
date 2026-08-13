<?php

namespace RoxAppointmentBooking\Modules\Email\Services;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Agent\Data\AgentModel;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;

/**
 * Class EmailRecipientResolver
 *
 * @package RoxAppointmentBooking\Modules\Email\Services
 * @description Turns a recipient type ('customer' | 'agent' | 'admin') plus an
 *              event context into the address(es) to send to. An empty result is
 *              normal — a booking with no agent has nobody to notify — and
 *              EmailService skips the send silently.
 */
class EmailRecipientResolver
{
    /**
     * Resolve the addresses for one recipient type.
     *
     * @param string $recipientType One of the EmailTemplateRegistry::RECIPIENT_* values.
     * @param array $context Event context.
     * @return string[] Valid, de-duplicated e-mail addresses.
     */
    public static function resolve(string $recipientType, array $context = []): array
    {
        switch ($recipientType) {
            case EmailTemplateRegistry::RECIPIENT_CUSTOMER:
                $addresses = self::customerAddresses($context);
                break;
            case EmailTemplateRegistry::RECIPIENT_AGENT:
                $addresses = self::agentAddresses($context);
                break;
            case EmailTemplateRegistry::RECIPIENT_ADMIN:
                $addresses = self::adminAddresses();
                break;
            default:
                $addresses = [];
        }

        /**
         * Filter the resolved recipients for an e-mail.
         *
         * @param string[] $addresses     Resolved addresses.
         * @param string   $recipientType Recipient type.
         * @param array    $context       Event context.
         */
        $addresses = apply_filters(
            'rox_appointment_booking_email_recipients',
            $addresses,
            $recipientType,
            $context
        );

        return self::clean($addresses);
    }

    /**
     * The customer's address — taken from the context when the caller already
     * has the customer data, otherwise looked up by id, otherwise read off the
     * booking itself.
     *
     * @param array $context Event context.
     * @return string[]
     */
    private static function customerAddresses(array $context): array
    {
        if (!empty($context['customer']['email'])) {
            return [$context['customer']['email']];
        }

        $customerId = (int) ($context['customer_id'] ?? 0);

        if ($customerId <= 0) {
            $first      = self::appointments($context)[0] ?? null;
            $customerId = (int) ($first->customer_id ?? 0);
        }

        if ($customerId <= 0) {
            return [];
        }

        $customer = CustomerModel::find($customerId);

        return $customer && !empty($customer->email) ? [$customer->email] : [];
    }

    /**
     * The assigned agent's address. Empty when the appointment has no agent
     * (Agent-Optional Booking). Falls back to the booking's own agent(s) when the
     * caller passed no agent — one order can span more than one agent, and each of
     * them is notified.
     *
     * @param array $context Event context.
     * @return string[]
     */
    private static function agentAddresses(array $context): array
    {
        if (!empty($context['agent']['email'])) {
            return [$context['agent']['email']];
        }

        $agentIds = [];

        if ((int) ($context['agent_id'] ?? 0) > 0) {
            $agentIds[] = (int) $context['agent_id'];
        } else {
            foreach (self::appointments($context) as $appointment) {
                if (!empty($appointment->agent_id)) {
                    $agentIds[] = (int) $appointment->agent_id;
                }
            }
        }

        $addresses = [];

        foreach (array_unique($agentIds) as $agentId) {
            $agent = AgentModel::find($agentId);

            if ($agent && !empty($agent->email)) {
                $addresses[] = $agent->email;
            }
        }

        return $addresses;
    }

    /**
     * The appointments referenced by the context, so a caller that only knows the
     * booking does not have to resolve its customer and agent itself.
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
     * The admin notification address(es) — the comma-separated
     * `admin_recipients` e-mail setting, falling back to the WordPress site
     * admin e-mail when it is empty.
     *
     * @return string[]
     */
    private static function adminAddresses(): array
    {
        $configured = (string) rox_appointment_booking_email_settings('admin_recipients', '');

        if (trim($configured) === '') {
            return [(string) get_option('admin_email')];
        }

        return array_map('trim', explode(',', $configured));
    }

    /**
     * Drop blanks, invalid addresses and duplicates.
     *
     * @param mixed $addresses Candidate addresses.
     * @return string[]
     */
    private static function clean($addresses): array
    {
        if (!is_array($addresses)) {
            return [];
        }

        $clean = [];

        foreach ($addresses as $address) {
            if (!is_string($address)) {
                continue;
            }

            $sanitized = sanitize_email(trim($address));

            if ($sanitized !== '' && is_email($sanitized)) {
                $clean[] = $sanitized;
            }
        }

        return array_values(array_unique($clean));
    }
}
