<?php

namespace RoxAppointmentBooking\Modules\Appointment\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\Order\Services\OrderService;
use RoxAppointmentBooking\Modules\Order\Data\OrderModel;
use RoxAppointmentBooking\Modules\Service\Data\ServiceModel;
use RoxAppointmentBooking\Modules\Payment\Data\PaymentModel;
use RoxAppointmentBooking\Modules\Payment\Services\PaymentStatusSyncService;
use RoxAppointmentBooking\Modules\Appointment\Services\AppointmentService;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceAgentRelationModel;

/**
 * Class SaveAppointment
 * 
 * @package RoxAppointmentBooking\Modules\Appointment\REST
 * @description Handles the creation and updating of appointments via REST API.
 */
class SaveAppointment extends AbstractREST
{
    /**
     * Whether the endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for saving appointments.
     *
     * @var string
     */
    public static string $route = '/appointment(?:/(?P<id>\d+))?';
    /**
     * Usable route template for docs.
     *
     * @var string
     */
    public static string $usableRoute = '/appointment/';

    /**
     * Get the methods allowed for this route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return ['POST', 'PUT'];
    }

    /**
     * Handle appointment create or update request.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest( WP_REST_Request $request ): WP_REST_Response|WP_Error
    {
        $id = $request->get_param('id');
        $rawData = $request->get_json_params();
        $validationResult = $this->validateAndSanitizeData($rawData);

        if (is_wp_error($validationResult)) {
            return $validationResult;
        }

        $sanitizedData = $validationResult;

        try {
            // The admin form offers every agent, so the selected one may not provide
            // the selected service yet — assign it before the booking is saved.
            $this->assignServiceToAgent($sanitizedData);

            $model = new AppointmentModel();
            $appointmentService = new AppointmentService();
            $userId = get_current_user_id();

            if ( $id && $id !== '0' ) {
                $existing = $model->find($id);

                if ( ! $existing ) {
                    return new WP_Error(
                        'appointment_not_found',
                        esc_html__('Appointment not found', 'rox-appointment-booking'),
                        ['status' => 404]
                    );
                }
                $oldStatus        = $existing->status;
                $oldPaymentStatus = $existing->payment_status;
                $oldDate          = $existing->date ?? '';
                $oldStartTime     = $existing->start_time ?? '';
                $sanitizedData['updated_by'] = $userId;
                $result = $existing->update($sanitizedData);

                if (!empty($sanitizedData['payment_status'])) {
                    $this->syncRelatedPaymentStatus(
                        (int) $id,
                        $sanitizedData['payment_status'],
                        $oldPaymentStatus !== $sanitizedData['payment_status']
                    );
                }

                if (!empty($sanitizedData['status'])) {
                    $this->syncRelatedOrderStatus((int) $id, $sanitizedData['status']);
                }

                // The edit may have changed the service, the extra services or the
                // attendee count, all of which the order is priced from.
                $this->syncRelatedOrderTotals((int) $id, $sanitizedData);

                /**
                 * Fires after an appointment is created or updated via the admin
                 * SaveAppointment endpoint (covers edits and status changes,
                 * including cancellation). Not fired for the public booking panel —
                 * see `rox_appointment_booking_after_booking_confirmed` for that.
                 *
                 * Deliberately BEFORE the notifications below: integrations on
                 * this hook update or remove what the e-mails then render. On a
                 * reschedule the meeting has to move before the e-mail quotes it,
                 * and on a cancellation the meeting has to be gone before the
                 * e-mail offers a link to it.
                 *
                 * @param AppointmentModel $appointment    The saved appointment.
                 * @param bool             $isNew          False here (update path).
                 * @param string|null      $previousStatus The appointment's status before this save.
                 */
                do_action('rox_appointment_booking_after_appointment_saved', $existing, false, $oldStatus);

                // Fire dashboard notification whenever the appointment status actually changes
                if (isset($sanitizedData['status']) && $oldStatus !== $sanitizedData['status']) {
                    $appointmentService->sendStatusChangeNotification($existing, $sanitizedData['status']);
                }

                // Fire reschedule notification when the appointment date or time slot is changed
                $newDate      = $sanitizedData['date'] ?? '';
                $newStartTime = $sanitizedData['start_time'] ?? '';
                if (
                    (!empty($newDate) && $oldDate !== $newDate) ||
                    (!empty($newStartTime) && $oldStartTime !== $newStartTime)
                ) {
                    $appointmentService->sendRescheduleNotification($existing, $oldDate, $oldStartTime, $newDate, $newStartTime);
                }

                $message = esc_html__('Appointment updated successfully', 'rox-appointment-booking');
                $order = null;
                $payment = null;
            } else {
                $slotCheck = $this->checkSlotAvailability($sanitizedData);
                if (is_wp_error($slotCheck)) {
                    return $slotCheck;
                }

                if (empty($sanitizedData['status'])) {
                    $sanitizedData['status'] = rox_appointment_booking_general_settings('default_appointment_status', 'pending');
                }

                // Set default payment_status only when creating
                if (empty($sanitizedData['payment_status'])) {
                    $sanitizedData['payment_status'] = rox_appointment_booking_payment_settings('default_payment_status', 'unpaid');
                }

                $sanitizedData['created_by'] = $userId;
                $sanitizedData['updated_by'] = $userId;

                $now = gmdate('Y-m-d H:i:s');

                $result = $model->create($sanitizedData);

                if (!$result || !isset($result->id)) {
                    return new WP_Error(
                        'appointment_create_failed',
                        esc_html__('Unable to book the appointment. Please try again or contact support.', 'rox-appointment-booking'),
                        ['status' => 500]
                    );
                }

                $order = $this->createOrderForAppointment($result, $sanitizedData, $now);

                if (!$order || !isset($order->id)) {
                    $result->delete();
                    return new WP_Error(
                        'order_create_failed',
                        esc_html__('Unable to create the order. Please contact support.', 'rox-appointment-booking'),
                        ['status' => 500]
                    );
                }

                $payment = $this->createPaymentForOrder($order, $sanitizedData, $now);

                if (!$payment) {
                    $order->delete();
                    $result->delete();
                    return new WP_Error(
                        'payment_create_failed',
                        esc_html__('Payment creation failed. Please contact support.', 'rox-appointment-booking'),
                        ['status' => 500]
                    );
                }
                /**
                 * Fires after a new appointment is created via the admin
                 * SaveAppointment endpoint.
                 *
                 * Deliberately BEFORE the notifications below: integrations on
                 * this hook attach things the e-mails then render (a Zoom
                 * meeting, a Google Meet link, via the
                 * `rox_appointment_booking_meet_link` filter). Send first and
                 * those placeholders resolve to nothing. The public booking
                 * panel orders it the same way — see BookingConfirm.php.
                 */
                do_action('rox_appointment_booking_after_appointment_saved', $result, true, null);

                $appointmentService->sendAdminBookingNotification($result);

                // The per-booking "Send notifications" checkbox stays an extra
                // suppress switch on top of the global Settings → E-mail toggles.
                if (!empty($sanitizedData['send_notification'])) {
                    $appointmentService->sendAppointmentNotification($result, (int) $order->id);
                }

                $message = esc_html__('Your appointment has been booked successfully. A confirmation has been sent.', 'rox-appointment-booking');
            }

            if ( !$result ) {
                return new WP_Error(
                    'save_failed',
                    esc_html__('Unable to save the appointment. Please try again or contact support.', 'rox-appointment-booking'),
                    ['status' => 500]
                );
            }

            return rox_appointment_booking_rest_response(
                data: [
                    'appointment' => $result,
                    'order' => $order ?? null,
                    'payment' => $payment ?? null,
                    'id' => $result->id ?? $id
                ],
                message: $message
            );
        } catch (\Exception $e) {
            return new WP_Error(
                'save_error',
                esc_html__('Something went wrong while processing your appointment. Please try again or contact support.', 'rox-appointment-booking'),
                ['status' => 500]
            );
        }
    }

    /**
     * Validates and sanitizes appointment payload.
     *
     * @param array $data
     * @return array|WP_Error
     */
    protected function validateAndSanitizeData($data)
    {
        $errors = [];
        $sanitized = [];

        $sanitized['location_id'] = isset($data['location_id']) && intval(wp_unslash($data['location_id'])) > 0 
            ? intval(wp_unslash($data['location_id'])) 
            : null;

        $sanitized['category_id'] = isset($data['category_id']) && intval(wp_unslash($data['category_id'])) > 0 
            ? intval(wp_unslash($data['category_id'])) 
            : null;

        $sanitized['service_id'] = isset($data['service_id']) && intval(wp_unslash($data['service_id'])) > 0 
            ? intval(wp_unslash($data['service_id'])) 
            : null;

        // Agent is required unless the selected service is agent-less
        // (allow_without_agent). Stored NULL when omitted on an agent-less service.
        // Agent-optional booking is a Pro feature — without Pro active, agent stays required.
        $selected_service = !empty($sanitized['service_id']) ? ServiceModel::find($sanitized['service_id']) : null;
        $service_allows_no_agent = $selected_service
            && defined('ROX_APPOINTMENT_BOOKING_PRO_VERSION')
            && (bool) $selected_service->allow_without_agent;

        if (isset($data['agent_id']) && intval(wp_unslash($data['agent_id'])) > 0) {
            $sanitized['agent_id'] = intval(wp_unslash($data['agent_id']));
        } elseif ($service_allows_no_agent) {
            $sanitized['agent_id'] = null;
        } else {
            $errors['agent_id'] = esc_html__('Agent ID is required and must be a positive integer.', 'rox-appointment-booking');
        }

        if (!isset($data['customer_id']) || intval(wp_unslash($data['customer_id'])) <= 0) {
            $errors['customer_id'] = esc_html__('Customer ID is required and must be a positive integer.', 'rox-appointment-booking');
        } else {
            $sanitized['customer_id'] = intval(wp_unslash($data['customer_id']));
        }

        if (isset($data['check_availability']) && is_array($data['check_availability'])) {
            $sanitized['date'] = isset($data['check_availability']['date']) 
                ? $this->normalizeDate($data['check_availability']['date']) 
                : null;
            $sanitized['start_time'] = isset($data['check_availability']['start_time']) 
                ? $this->normalizeDateTime($data['check_availability']['start_time'], $sanitized['date']) 
                : null;
            $sanitized['end_time'] = isset($data['check_availability']['end_time']) 
                ? $this->normalizeDateTime($data['check_availability']['end_time'], $sanitized['date']) 
                : null;
        } else {
            $sanitized['date'] = isset($data['date']) ? $this->normalizeDate($data['date']) : null;
            $sanitized['start_time'] = isset($data['start_time']) ? $this->normalizeDateTime($data['start_time'], $sanitized['date']) : null;
            $sanitized['end_time'] = isset($data['end_time']) ? $this->normalizeDateTime($data['end_time'], $sanitized['date']) : null;
        }

        if (isset($data['extra_services']) && is_array($data['extra_services'])) {
            $sanitized['extra_services'] = array_map(function ($service) {
                return sanitize_text_field($service);
            }, $data['extra_services']);
        } else {
            $sanitized['extra_services'] = [];
        }

        // Calculate total duration (service duration + extra services durations)
        $total_duration = 0;
        if (!empty($sanitized['service_id'])) {
            $service = ServiceModel::find($sanitized['service_id']);
            if ($service && !empty($service->duration)) {
                $total_duration += (int) $service->duration;
            }
        }
        
        if (!empty($sanitized['extra_services'])) {
            foreach ($sanitized['extra_services'] as $extra_id) {
                if (class_exists('\\RoxAppointmentBookingPro\\Modules\\ExtraService\\Data\\ExtraServiceModel')) {
                    $extra_service = \RoxAppointmentBookingPro\Modules\ExtraService\Data\ExtraServiceModel::find(intval($extra_id));
                    if ($extra_service && !empty($extra_service->duration)) {
                        $total_duration += (int) $extra_service->duration;
                    }
                }
            }
        }

        // Re-calculate end_time based on start_time + total_duration if start_time is set
        if (!empty($sanitized['start_time']) && $total_duration > 0) {
            $start_dt = new \DateTime($sanitized['start_time']);
            $start_dt->modify("+{$total_duration} minutes");
            $sanitized['end_time'] = $start_dt->format('Y-m-d H:i:s');
        }

        $sanitized['coupon_id'] = isset($data['coupon_id']) ? intval(wp_unslash($data['coupon_id'])) : null;
        $sanitized['purchase_details'] = isset($data['purchase_details']) ? sanitize_text_field($data['purchase_details']) : '';
        $sanitized['status'] = isset($data['status']) && !empty($data['status']) ? sanitize_text_field($data['status']) : null;
        $sanitized['payment_status'] = isset($data['payment_status']) ? sanitize_text_field($data['payment_status']) : null;
        $sanitized['total_attendees'] = isset($data['total_attendees']) ? max(1, intval($data['total_attendees'])) : 1;
        $sanitized['internal_notes'] = isset($data['internal_notes']) ? sanitize_textarea_field($data['internal_notes']) : '';
        $sanitized['send_notification'] = isset($data['send_notification']) ? intval($data['send_notification']) : 0;

        if (!empty($errors)) {
            return new WP_Error('invalid_appointment_data', esc_html__('Please fix the following errors and try again.', 'rox-appointment-booking'), $errors);
        }

        return $sanitized;
    }

    /**
     * Normalizes date input to Y-m-d.
     *
     * @param string $date
     * @return string|null
     */
    private function normalizeDate($date)
    {
        if (empty($date)) {
            return null;
        }

        try {
            $dt = new \DateTime($date);
            return $dt->format('Y-m-d');
        } catch (\Exception $e) {
            return sanitize_text_field($date);
        }
    }

    /**
     * Normalizes date/time input to Y-m-d H:i:s.
     *
     * @param string $datetime
     * @param string|null $defaultDate
     * @return string|null
     */
    private function normalizeDateTime($datetime, $defaultDate = null)
    {
        if (empty($datetime)) {
            return null;
        }

        try {
            // Time-only values (e.g. the admin slot picker's "08:00 AM", or a
            // bare "08:00:00") have no leading date — prepend the
            // appointment's date so DateTime doesn't silently default to
            // today. Detected by the ABSENCE of a leading Y-m-d date, rather
            // than matching one specific time format, since callers send
            // times in different shapes (24h "HH:MM:SS" vs 12h "hh:mm A").
            if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $datetime)) {
                $date = $defaultDate ?? gmdate('Y-m-d');
                $datetime = $date . ' ' . $datetime;
            }

            $dt = new \DateTime($datetime);

            return $dt->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return sanitize_text_field($datetime);
        }
    }
  
    /**
     * Checks for overlapping appointment slots.
     *
     * @param array $data
     * @return true|WP_Error
     */
    private function checkSlotAvailability($data)
    {
        // CUSTOMER DOUBLE-BOOKING CHECK:
        // The same customer cannot hold two appointments that overlap in time,
        // regardless of service or agent.
        if (!empty($data['customer_id'])) {
            $customer_conflict = AppointmentModel::where('customer_id', $data['customer_id'])
                ->where('date', $data['date'])
                ->whereNotIn('status', ['cancelled', 'canceled', 'rejected'])
                ->where(function($query) use ($data) {
                    $query->where('start_time', '<', $data['end_time'])
                          ->where('end_time', '>', $data['start_time']);
                })
                ->first();

            if ($customer_conflict) {
                return new WP_Error(
                    'customer_time_conflict',
                    esc_html__('This customer already has an appointment at this time. To add or change a service, edit or reschedule the existing appointment.', 'rox-appointment-booking'),
                    ['status' => 409]
                );
            }
        }

        // GROUP CAPACITY CHECK: a service with capacity === 'group' shares one
        // slot across multiple bookings/customers, keyed on the SUM of
        // total_attendees rather than a single-occupancy or booking-count block.
        // Scoped to the same agent when one is set, so each agent's own session
        // has its own attendee pool.
        $group_service = !empty($data['service_id']) ? ServiceModel::find($data['service_id']) : null;
        // Pro feature: without Pro active the service stays single-occupancy.
        if ($group_service && $group_service->capacity === 'group' && defined('ROX_APPOINTMENT_BOOKING_PRO_VERSION')) {
            $max_capacity = (int) $group_service->max_capacity;
            if ($max_capacity <= 0) {
                $max_capacity = 1;
            }

            $attendees_query = AppointmentModel::where('service_id', $data['service_id'])
                ->where('date', $data['date'])
                ->whereNotIn('status', ['cancelled', 'canceled', 'rejected'])
                ->where(function($query) use ($data) {
                    $query->where('start_time', '<', $data['end_time'])
                          ->where('end_time', '>', $data['start_time']);
                });
            if (!empty($data['agent_id'])) {
                $attendees_query->where('agent_id', $data['agent_id']);
            } else {
                $attendees_query->whereNull('agent_id');
            }
            $existing_attendees = (int) $attendees_query->sum('total_attendees');
            $requested_attendees = max(1, intval($data['total_attendees'] ?? 1));

            if ($existing_attendees + $requested_attendees > $max_capacity) {
                return new WP_Error(
                    'slot_already_booked',
                    esc_html__('This time slot does not have enough remaining spots for the number of attendees. Please choose a different time or reduce the number of attendees.', 'rox-appointment-booking'),
                    ['status' => 409]
                );
            }

            // The attendee pool above only covers this same group service. The agent
            // can still be occupied by another service at the same time, so the
            // single-occupancy guard still applies to everything outside the group.
            if (!empty($data['agent_id'])) {
                $agent_conflict = AppointmentModel::where('agent_id', $data['agent_id'])
                    ->where('date', $data['date'])
                    ->whereNotIn('status', ['cancelled', 'canceled', 'rejected'])
                    ->where(function($query) use ($data) {
                        $query->where('service_id', '!=', $data['service_id'])
                              ->whereNull('service_id', 'or');
                    })
                    ->where(function($query) use ($data) {
                        $query->where('start_time', '<', $data['end_time'])
                              ->where('end_time', '>', $data['start_time']);
                    })
                    ->first();

                if ($agent_conflict) {
                    return new WP_Error(
                        'slot_already_booked',
                        esc_html__('The selected agent already has another appointment at this time. Please choose a different time.', 'rox-appointment-booking'),
                        ['status' => 409]
                    );
                }
            }

            return true;
        }

        // Agent-less appointment (agent_id NULL): enforce the service capacity —
        // reject when overlapping non-cancelled bookings of THIS service (any agent)
        // already reach max_capacity (default 1).
        if (empty($data['agent_id'])) {
            $max_capacity = 1;
            if (!empty($data['service_id'])) {
                $service = ServiceModel::find($data['service_id']);
                if ($service && (int) $service->without_agent_capacity > 0) {
                    $max_capacity = (int) $service->without_agent_capacity;
                }
            }

            $overlapping_count = AppointmentModel::where('service_id', $data['service_id'])
                ->where('date', $data['date'])
                ->whereNotIn('status', ['cancelled', 'canceled', 'rejected'])
                ->where(function($query) use ($data) {
                    $query->where('start_time', '<', $data['end_time'])
                          ->where('end_time', '>', $data['start_time']);
                })
                ->count();

            if ($overlapping_count >= $max_capacity) {
                return new WP_Error(
                    'slot_already_booked',
                    esc_html__('This time slot is fully booked. Please choose a different time.', 'rox-appointment-booking'),
                    ['status' => 409]
                );
            }

            return true;
        }

        // HARD CONFLICT CHECK:
        // Why this is needed: The UI might show a slot as available based on the base service duration.
        // However, when "Extra Services" are added, the total duration increases and the end_time is recalculated.
        // If this new total duration extends into another appointment that is already booked later in the day,
        // we must reject the booking here to prevent the agent from being double-booked.
        $existing = AppointmentModel::where('agent_id', $data['agent_id'])
            ->where('date', $data['date'])
            ->whereNotIn('status', ['cancelled', 'canceled', 'rejected'])
            ->where(function($query) use ($data) {
                $query->where(function($q) use ($data) {
                    $q->where('start_time', '<', $data['end_time'])
                      ->where('end_time', '>', $data['start_time']);
                });
            })
            ->first();
        
        if ($existing) {
            return new WP_Error(
                'slot_already_booked',
                esc_html__('The selected time slot does not have enough time to accommodate your selected extra services or this timeslot already booked. Please choose a different time.', 'rox-appointment-booking'),
                ['status' => 409]
            );
        }
        
        return true;
    }

    /**
     * Relates the selected agent to the selected service when they aren't related yet.
     *
     * Mirrors what the agent form's "Services" field would have done, so an admin can
     * book any agent for any service without editing the agent first. A missing agent
     * or service is left to the save itself to reject.
     *
     * @param array $data Sanitized appointment payload.
     * @return void
     */
    private function assignServiceToAgent(array $data): void
    {
        $agentId   = (int) ($data['agent_id'] ?? 0);
        $serviceId = (int) ($data['service_id'] ?? 0);

        if ($agentId <= 0 || $serviceId <= 0) {
            return;
        }

        if (ServiceAgentRelationModel::relationExists($agentId, $serviceId)) {
            return;
        }

        try {
            ServiceAgentRelationModel::createRelation($agentId, $serviceId);
        } catch (\Exception $e) {
        }
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

        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return false;
        }

        return true;
    }

    /**
     * Creates or reuses an order for an appointment.
     *
     * @param AppointmentModel $appointment
     * @param array $appointmentData
     * @param string $now
     * @return OrderModel|null
     */
    private function createOrderForAppointment($appointment, array $appointmentData, string $now = '')
    {
        if (!$now) {
            $now = gmdate('Y-m-d H:i:s');
        }
        if (!$appointment || !isset($appointment->id)) {
            return null;
        }

        $existingOrder = OrderModel::where('booking_ids', 'LIKE', '%"' . $appointment->id . '"%')->first();
        if ($existingOrder) {
            return $existingOrder;
        }

        $service = null;
        if (!empty($appointmentData['service_id'])) {
            $service = ServiceModel::find($appointmentData['service_id']);
        }

        if (!$service) {
            throw new \Exception('Service not found for appointment');
        }

        $discountAmount = 0;
        $taxAmount = 0;

        $subtotal = $this->calculateOrderSubtotal($service, $appointmentData);

        if (!empty($appointmentData['coupon_id'])) {
        }

        $totalAmount = $subtotal - $discountAmount + $taxAmount;
        
        $paymentStatus = !empty($appointmentData['payment_status'])
            ? $appointmentData['payment_status']
            : rox_appointment_booking_payment_settings('default_payment_status', 'unpaid');

        $orderStatus = !empty($appointmentData['status'])
            ? $appointmentData['status']
            : rox_appointment_booking_general_settings('default_order_status', 'pending_payment');

        $orderData = [
            'customer_id' => $appointmentData['customer_id'],
            'booking_ids' => [$appointment->id],
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'currency' => rox_appointment_booking_payment_settings('payment_currency') ?? 'USD',
            'payment_method' => 'cash',
            'payment_status' => $paymentStatus,
            'order_status' => $orderStatus,
            'order_date' => $now,
            'created_at' => $now,
            'updated_at' => $now,
            'internal_notes' => sprintf(
                // translators: %1$d = appointment ID, %2$s = service name, %3$d = number of attendees
                esc_html__('Order auto-created for appointment #%1$d - Service: %2$s (%3$d attendees)', 'rox-appointment-booking'),
                $appointment->id,
                $service->title ?? 'Unknown Service',
                max(1, (int) ($appointmentData['total_attendees'] ?? 1))
            ),
        ];


        if (!empty($appointmentData['coupon_id'])) {
            $orderData['coupon_id'] = $appointmentData['coupon_id'];
        }

        $orderService = new OrderService();
        return $orderService->saveOrder($orderData, null, $now);
    }

    /**
     * Creates a payment record for an order.
     *
     * @param OrderModel $order
     * @param array $appointmentData
     * @param string $now
     * @return PaymentModel|null
     */
    private function createPaymentForOrder($order, array $appointmentData, string $now = '')
    {
        if (!$order || !isset($order->id)) {
            return null;
        }

        if (!$now) {
            $now = gmdate('Y-m-d H:i:s');
        }

        $paymentStatus = !empty($appointmentData['payment_status'])
            ? $appointmentData['payment_status']
            : ($order->payment_status ?? rox_appointment_booking_payment_settings('default_payment_status', 'unpaid'));

        $paymentData = [
            'order_id' => $order->id,
            'customer_id' => $appointmentData['customer_id'],
            'amount' => $order->total_amount,
            'payment_method' => 'cash',
            'status' => $paymentStatus,
            'transaction_id' => 'pl_' . wp_generate_uuid4(),
            'payment_time' => $now,
            'created_at' => $now,
            'updated_at' => $now,
            'internal_notes' => sprintf('Payment record for order #%d', $order->id),
        ];

        $payment = new PaymentModel();
        $payment->fill($paymentData);
        $payment->save();

        // Force created_at/updated_at to the shared timestamp
        $payment->update(['created_at' => $now, 'updated_at' => $now]);

        return $payment;
    }

    /**
     * Syncs payment status across related order and payment rows.
     *
     * @param int $appointmentId
     * @param string $paymentStatus
     * @param bool $statusChanged Whether this differs from the stored status —
     *        only then is the payment e-mail raised.
     * @return void
     */
    private function syncRelatedPaymentStatus(int $appointmentId, string $paymentStatus, bool $statusChanged = false): void
    {
        $order = OrderModel::where('booking_ids', 'LIKE', '%"' . $appointmentId . '"%')->first();
        if (!$order) {
            return;
        }

        $order->update(['payment_status' => $paymentStatus]);
        PaymentModel::where('order_id', $order->id)->update(['status' => $paymentStatus]);

        // This path settles the status with a bulk update instead of going
        // through PaymentStatusSyncService::applyStatus(), so it raises the
        // e-mail itself.
        if ($statusChanged) {
            PaymentStatusSyncService::notifyStatusChange(
                (int) $order->customer_id,
                (int) $order->id,
                $paymentStatus
            );
        }
    }

    /**
     * Syncs order status for the related order row.
     *
     * @param int $appointmentId
     * @param string $orderStatus
     * @return void
     */
    private function syncRelatedOrderStatus(int $appointmentId, string $orderStatus): void
    {
        $order = OrderModel::where('booking_ids', 'LIKE', '%"' . $appointmentId . '"%')->first();
        if (!$order) {
            return;
        }

        $order->update(['order_status' => $orderStatus]);
    }

    /**
     * Price an appointment: the service (per attendee) plus its extra services.
     *
     * Extra services are counted once per appointment rather than per attendee —
     * the same way validateAndSanitizeData() adds their duration once, and the way
     * the frontend booking panel prices them
     * (FrontendBookingPanel\Services\AppointmentService::calculateServerSubtotal).
     *
     * Shared by order creation and the update-time resync so the two can never
     * disagree about what a booking costs.
     *
     * @param ServiceModel $service         The booked service.
     * @param array        $appointmentData Sanitized appointment payload.
     * @return float
     */
    private function calculateOrderSubtotal($service, array $appointmentData): float
    {
        $extraServicesTotal = 0;
        if (!empty($appointmentData['extra_services']) && class_exists('\\RoxAppointmentBookingPro\\Modules\\ExtraService\\Data\\ExtraServiceModel')) {
            foreach ($appointmentData['extra_services'] as $extra_id) {
                $extra_service = \RoxAppointmentBookingPro\Modules\ExtraService\Data\ExtraServiceModel::find(intval($extra_id));
                if ($extra_service) {
                    $extraServicesTotal += (float) $extra_service->price;
                }
            }
        }

        $totalAttendees = max(1, (int) ($appointmentData['total_attendees'] ?? 1));

        return round((float) ($service->price ?? 0) * $totalAttendees + $extraServicesTotal, 2);
    }

    /**
     * Re-price the order behind an appointment after it was edited.
     *
     * Editing a booking can change what it costs — a different service, an extra
     * service added or removed, a different number of attendees — but the order
     * was priced once at creation and nothing recomputed it, so the appointment
     * view would list an extra service the total did not include.
     *
     * Only the money the appointment determines is rewritten: an existing discount
     * or tax stays as it is, since neither is derived from the appointment here.
     * Payment rows are deliberately left alone — money already taken is not this
     * endpoint's to rewrite, and the amount due is derived from the order total.
     *
     * @param int   $appointmentId   The edited appointment.
     * @param array $appointmentData Sanitized appointment payload.
     * @return void
     */
    private function syncRelatedOrderTotals(int $appointmentId, array $appointmentData): void
    {
        if (empty($appointmentData['service_id'])) {
            return;
        }

        $service = ServiceModel::find($appointmentData['service_id']);
        if (!$service) {
            return;
        }

        // JSON_CONTAINS, not a LIKE on the encoded column: `booking_ids` holds
        // unquoted numbers (`[31]`), so the `%"31"%` pattern the status syncs above
        // use never matches an order created by the booking panel. Same lookup
        // GetAppointment uses to resolve an appointment's order.
        $order = OrderModel::whereRaw('JSON_CONTAINS(booking_ids, %s)', [json_encode($appointmentId)])->first();
        if (!$order) {
            return;
        }

        // An order can cover several appointments (the booking panel books more
        // than one slot at a time). Re-pricing from this one appointment alone
        // would wipe the others out of the total, so leave those orders alone.
        $bookingIds = $order->booking_ids;
        if (is_array($bookingIds) && count($bookingIds) > 1) {
            return;
        }

        $subtotal = $this->calculateOrderSubtotal($service, $appointmentData);
        $discountAmount = (float) ($order->discount_amount ?? 0);
        $taxAmount = (float) ($order->tax_amount ?? 0);

        $order->update([
            'subtotal' => $subtotal,
            'total_amount' => max(0, round($subtotal - $discountAmount + $taxAmount, 2)),
        ]);
    }
}
