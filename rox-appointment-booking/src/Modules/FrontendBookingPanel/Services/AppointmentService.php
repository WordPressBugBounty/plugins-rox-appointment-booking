<?php
namespace RoxAppointmentBooking\Modules\FrontendBookingPanel\Services;

use WP_Error;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\Agent\Data\AgentModel;
use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;
use RoxAppointmentBooking\Modules\Order\Data\OrderModel;
use RoxAppointmentBooking\Modules\Payment\Data\PaymentModel;
use RoxAppointmentBooking\Modules\Notification\Services\NotificationService;
use RoxAppointmentBooking\Modules\Customer\Services\CustomerService;
use RoxAppointmentBooking\Modules\Service\Data\ServiceModel;
use RoxAppointmentBooking\Modules\Service\Services\ServiceService;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceCategoryRelationModel;
use RoxAppointmentBooking\Modules\RelationshipModel\Data\ServiceLocationRelationModel;

/**
 * Class AppointmentService
 *
 * @package RoxAppointmentBooking\Modules\FrontendBookingPanel\Services
 * @description Handles frontend booking appointment, order, and payment status operations.
 */
class AppointmentService
{
    /**
     * Save frontend booking appointments.
     *
     * @param array $params Booking request parameters.
     * @param int $customerId Customer ID.
     * @return array|WP_Error
     */
    public function saveAppointments(array $params, int $customerId): array|WP_Error
    {
        if (empty($params['appointments']) || !is_array($params['appointments'])) {
            return new WP_Error('missing_appointments', esc_html__('Appointments array is required', 'rox-appointment-booking'), ['status' => 400]);
        }

        // Two-phase save: the whole cart is validated first and only written
        // once every appointment in it passes. Validating and inserting in a
        // single pass left the already-inserted rows behind — with no order, no
        // payment and no notification — as soon as a later appointment was
        // rejected, and those orphan rows then blocked the customer's own retry.
        $validated = [];

        foreach ($params['appointments'] as $appointmentData) {
            // Frontend might send end_time or we can calculate it securely.
            // agent_id is validated conditionally below (agent-less services skip it).
            $required = ['service_id', 'date', 'start_time'];
            foreach ($required as $field) {
                if (empty($appointmentData[$field])) {
                    // translators: %s = field name
                    return new WP_Error('missing_field', sprintf(esc_html__('%s is required', 'rox-appointment-booking'), esc_html($field)), ['status' => 400]);
                }
            }

            // Calculate total duration (service duration + extra services durations)
            $total_duration = 0;
            $service = ServiceModel::find(intval($appointmentData['service_id']));
            if (!$service) {
                return new WP_Error(
                    'service_not_found',
                    esc_html__('Selected service was not found.', 'rox-appointment-booking'),
                    ['status' => 404]
                );
            }

            // The listing endpoints (GetService/GetAgent/GetLocation) hide inactive
            // records, but that only stops a fresh panel from offering them. A stale
            // open tab, a cached page or a direct API call can still post an inactive
            // id, so the write path enforces the toggle itself.
            if (!$service->isActive()) {
                return new WP_Error(
                    'service_inactive',
                    esc_html__('Selected service is no longer available for booking.', 'rox-appointment-booking'),
                    ['status' => 409]
                );
            }

            if (!empty($appointmentData['location_id']) && class_exists('\\RoxAppointmentBookingPro\\Modules\\Location\\Data\\LocationModel')) {
                $location = \RoxAppointmentBookingPro\Modules\Location\Data\LocationModel::find(intval($appointmentData['location_id']));
                if ($location && !$location->isActive()) {
                    return new WP_Error(
                        'location_inactive',
                        esc_html__('Selected location is no longer available for booking.', 'rox-appointment-booking'),
                        ['status' => 409]
                    );
                }
            }

            if (!$this->isServiceValidForCategoryAndLocation($service->getID(), $appointmentData['category_id'] ?? null, $appointmentData['location_id'] ?? null)) {
                return new WP_Error(
                    'service_scope_mismatch',
                    esc_html__('Selected service is not available for the chosen category or location.', 'rox-appointment-booking'),
                    ['status' => 409]
                );
            }

            // BOOKING WINDOW:
            // A service can be restricted to slots no sooner than X and no further
            // out than Y. The panel already hides everything outside that window,
            // but a stale open tab or a direct API call can still post one, so the
            // write path enforces both ends too.
            $slot_time = strtotime($appointmentData['date'] . ' ' . $appointmentData['start_time']);
            $now_time = strtotime(current_time('mysql'));

            $minimum_advance = ServiceService::minimumAdvanceMinutes($service);
            if ($minimum_advance > 0 && (!$slot_time || $slot_time < $now_time + $minimum_advance * 60)) {
                return new WP_Error(
                    'minimum_advance_required',
                    sprintf(
                        // translators: %s = the minimum window, e.g. "5 days 4 hours"
                        esc_html__('This service must be booked at least %s before it starts. Please choose a later date or time.', 'rox-appointment-booking'),
                        esc_html(ServiceService::formatAdvanceWindow($minimum_advance))
                    ),
                    ['status' => 409]
                );
            }

            $maximum_advance = ServiceService::maximumAdvanceMinutes($service);
            if ($maximum_advance > 0 && (!$slot_time || $slot_time > $now_time + $maximum_advance * 60)) {
                return new WP_Error(
                    'maximum_advance_exceeded',
                    sprintf(
                        // translators: %s = the maximum window, e.g. "90 days"
                        esc_html__('This service can only be booked up to %s ahead. Please choose an earlier date or time.', 'rox-appointment-booking'),
                        esc_html(ServiceService::formatAdvanceWindow($maximum_advance))
                    ),
                    ['status' => 409]
                );
            }

            // Agent-less services (allow_without_agent) may be booked with no agent;
            // everything else still requires one. agent_id is stored NULL when agent-less.
            // Pro feature: without Pro active the service stays agent-required.
            $allow_without_agent = defined('ROX_APPOINTMENT_BOOKING_PRO_VERSION') && (bool) $service->allow_without_agent;
            if (!$allow_without_agent && empty($appointmentData['agent_id'])) {
                // translators: %s = field name
                return new WP_Error('missing_field', sprintf(esc_html__('%s is required', 'rox-appointment-booking'), esc_html('agent_id')), ['status' => 400]);
            }
            $agent_id = $allow_without_agent ? null : intval($appointmentData['agent_id']);

            if ($agent_id !== null) {
                $booked_agent = AgentModel::find($agent_id);
                if (!$booked_agent || !$booked_agent->isActive()) {
                    return new WP_Error(
                        'agent_inactive',
                        esc_html__('Selected agent is no longer available for booking.', 'rox-appointment-booking'),
                        ['status' => 409]
                    );
                }
            }

            // SELF-BOOKING CHECK:
            // An agent is allowed to be a customer (booking through the panel with
            // their own email gives them a customer row alongside their agent row —
            // see Appointment\Services\AppointmentService::getCurrentCustomerId), but
            // never with themselves as the provider: that puts one person on both
            // sides of the appointment and silently consumes their own slot.
            if ($agent_id !== null && $this->isAgentTheCustomer($agent_id, $customerId)) {
                return new WP_Error(
                    'agent_self_booking',
                    esc_html__('An agent cannot be booked as their own customer. Please choose a different agent.', 'rox-appointment-booking'),
                    ['status' => 409]
                );
            }

            if ($service && !empty($service->duration)) {
                $total_duration += (int) $service->duration;
            }

            $extra_services = $appointmentData['extra_service_ids'] ?? [];
            if (!empty($extra_services) && is_array($extra_services) && class_exists('\\RoxAppointmentBookingPro\\Modules\\ExtraService\\Data\\ExtraServiceModel')) {
                foreach ($extra_services as $extra_id) {
                    $extra_service = \RoxAppointmentBookingPro\Modules\ExtraService\Data\ExtraServiceModel::find(intval($extra_id));
                    if ($extra_service && !empty($extra_service->duration)) {
                        $total_duration += (int) $extra_service->duration;
                    }
                }
            }

            // Calculate backend end_time
            $start_dt = new \DateTime($appointmentData['date'] . ' ' . $appointmentData['start_time']);
            $start_dt->modify("+{$total_duration} minutes");
            $calculated_end_time = $start_dt->format('H:i:s');

            $full_start_time = $appointmentData['date'] . ' ' . $appointmentData['start_time'];
            $full_end_time = $appointmentData['date'] . ' ' . $calculated_end_time;

            // CUSTOMER DOUBLE-BOOKING CHECK:
            // The same customer cannot hold two appointments that overlap in time,
            // regardless of service or agent (a person can't be in two places at once).
            $customer_conflict = AppointmentModel::query()
                ->where('customer_id', $customerId)
                ->where('date', $appointmentData['date'])
                ->whereNotIn('status', ['cancelled', 'canceled', 'rejected'])
                ->where(function($query) use ($full_start_time, $full_end_time) {
                    $query->where('start_time', '<', $full_end_time)
                          ->where('end_time', '>', $full_start_time);
                })
                ->first();

            // Appointments validated earlier in this same cart are not in the
            // database yet, so they are matched in memory. Every appointment in
            // the cart belongs to $customerId, so an overlap caught here also
            // covers the agent and capacity checks below.
            $pending_conflict = $this->hasOverlappingValidated($validated, $appointmentData['date'], $full_start_time, $full_end_time);

            if ($customer_conflict || $pending_conflict) {
                return new WP_Error(
                    'customer_time_conflict',
                    // __() rather than esc_html__(): this is JSON the panel
                    // renders as a text node, not markup, so HTML-escaping it
                    // here is never undone — the apostrophe would reach the
                    // toast as a literal &#039;.
                    __('This slot doesn\'t have enough spots. Choose another time or reduce attendees.', 'rox-appointment-booking'),
                    ['status' => 409]
                );
            }

            // Group-capacity services (capacity === 'group'): one appointment is
            // shared by up to $service->max_capacity attendees. The slot stays open
            // to other, unrelated customers (scoped to the same agent, when one is
            // required) until the sum of total_attendees reaches max_capacity —
            // unlike the single-occupancy and agent-less-multi-booking models below,
            // which count bookings, not people.
            // Pro feature: without Pro active the service stays single-occupancy.
            $is_group = defined('ROX_APPOINTMENT_BOOKING_PRO_VERSION') && ($service->capacity === 'group');
            $total_attendees = 1;

            if ($is_group) {
                $total_attendees = intval($appointmentData['total_attendees'] ?? 1);
                if ($total_attendees < 1) {
                    $total_attendees = 1;
                }

                $max_capacity = (int) $service->max_capacity;
                if ($max_capacity <= 0) {
                    $max_capacity = 1;
                }

                $existing_attendees_query = AppointmentModel::query()
                    ->where('service_id', intval($appointmentData['service_id']))
                    ->where('date', $appointmentData['date'])
                    ->whereNotIn('status', ['cancelled', 'canceled', 'rejected'])
                    ->where(function($query) use ($full_start_time, $full_end_time) {
                        $query->where('start_time', '<', $full_end_time)
                              ->where('end_time', '>', $full_start_time);
                    });
                if ($agent_id !== null) {
                    $existing_attendees_query->where('agent_id', $agent_id);
                } else {
                    $existing_attendees_query->whereNull('agent_id');
                }
                $existing_attendees = (int) $existing_attendees_query->sum('total_attendees');

                if ($existing_attendees + $total_attendees > $max_capacity) {
                    return new WP_Error(
                        'slot_full',
                        esc_html__('This time slot does not have enough remaining spots for the number of people you selected. Please choose a different time or reduce the number of attendees.', 'rox-appointment-booking'),
                        ['status' => 409]
                    );
                }

                // The attendee pool above only covers this same group service. The
                // agent can still be occupied by another service at the same time,
                // so the single-occupancy guard still applies outside the group.
                if ($agent_id !== null) {
                    $agent_conflict = AppointmentModel::query()
                        ->where('agent_id', $agent_id)
                        ->where('date', $appointmentData['date'])
                        ->whereNotIn('status', ['cancelled', 'canceled', 'rejected'])
                        ->where(function($query) use ($appointmentData) {
                            $query->where('service_id', '!=', intval($appointmentData['service_id']))
                                  ->whereNull('service_id', 'or');
                        })
                        ->where(function($query) use ($full_start_time, $full_end_time) {
                            $query->where('start_time', '<', $full_end_time)
                                  ->where('end_time', '>', $full_start_time);
                        })
                        ->first();

                    if ($agent_conflict) {
                        return new WP_Error(
                            'time_conflict',
                            esc_html__('The selected agent already has another appointment at this time. Please choose a different time.', 'rox-appointment-booking'),
                            ['status' => 409]
                        );
                    }
                }
            } elseif ($allow_without_agent) {
                // CAPACITY CONFLICT CHECK (agent-less):
                // Slots are shared across all agents for this service. Reject when the
                // number of overlapping non-cancelled bookings of THIS service already
                // reaches its max_capacity (default 1).
                $max_capacity = (int) $service->without_agent_capacity;
                if ($max_capacity <= 0) {
                    $max_capacity = 1;
                }

                $overlapping_count = AppointmentModel::query()
                    ->where('service_id', intval($appointmentData['service_id']))
                    ->where('date', $appointmentData['date'])
                    ->whereNotIn('status', ['cancelled', 'canceled', 'rejected'])
                    ->where(function($query) use ($full_start_time, $full_end_time) {
                        $query->where('start_time', '<', $full_end_time)
                              ->where('end_time', '>', $full_start_time);
                    })
                    ->count();

                if ($overlapping_count >= $max_capacity) {
                    return new WP_Error(
                        'slot_full',
                        esc_html__('This time slot is fully booked. Please choose a different time.', 'rox-appointment-booking'),
                        ['status' => 409]
                    );
                }
            } else {
                // HARD CONFLICT CHECK:
                // Why this is needed: The frontend might show a slot as available based on the base service duration.
                // However, when a user adds "Extra Services", the total duration increases.
                // If the total duration extends into another appointment that is already booked later in the same day,
                // we must reject it here to prevent the agent from being double-booked.
                $existing = AppointmentModel::query()
                    ->where('agent_id', $agent_id)
                    ->where('date', $appointmentData['date'])
                    ->whereNotIn('status', ['cancelled', 'canceled', 'rejected'])
                    ->where(function($query) use ($full_start_time, $full_end_time) {
                        $query->where(function($q) use ($full_start_time, $full_end_time) {
                            $q->where('start_time', '<', $full_end_time)
                              ->where('end_time', '>', $full_start_time);
                        });
                    })
                    ->first();

                if ($existing) {
                    return new WP_Error(
                        'time_conflict',
                        esc_html__('The selected time slot does not have enough time to accommodate your selected extra services. Please choose a different time.', 'rox-appointment-booking'),
                        ['status' => 409]
                    );
                }
            }

            $validated[] = [
                'date' => $appointmentData['date'],
                'start_time' => $full_start_time,
                'end_time' => $full_end_time,
                'fill' => [
                    'customer_id' => $customerId,
                    'service_id' => intval($appointmentData['service_id']),
                    'agent_id' => $agent_id,
                    'location_id' => $appointmentData['location_id'] ?? null,
                    'category_id' => $appointmentData['category_id'] ?? null,
                    'date' => $appointmentData['date'],
                    'start_time' => $appointmentData['date'] . ' ' . $appointmentData['start_time'],
                    'end_time' => $appointmentData['date'] . ' ' . $calculated_end_time,
                    'extra_services' => !empty($extra_services) ? $extra_services : [],
                    'status' => rox_appointment_booking_general_settings('default_appointment_status', 'pending'),
                    'payment_status' => rox_appointment_booking_payment_settings('default_payment_status', 'unpaid'),
                    'total_attendees' => $total_attendees,
                ],
            ];
        }

        // Phase 2: every appointment passed, so nothing below can reject the
        // cart and leave a partial booking behind.
        $appointmentIds = [];

        foreach ($validated as $validatedAppointment) {
            $appointment = new AppointmentModel();
            $appointment->fill($validatedAppointment['fill']);
            $appointment->save();

            $appointmentIds[] = $appointment->getID();
        }

        $this->createAppointmentNotifications($appointmentIds, $customerId);
        return [
            'appointment_ids' => $appointmentIds,
            'count' => count($appointmentIds)
        ];
    }

    /**
     * Check an already-validated cart for an appointment overlapping the given slot.
     *
     * Mirrors the SQL overlap test used against the booking table (same date,
     * start before the new end and end after the new start), for the rows that
     * are not written yet. Compared as timestamps rather than strings because
     * the panel's start_time is not guaranteed to share the H:i:s format of the
     * calculated end_time, and MySQL's own coercion does not apply here.
     *
     * @param array $validated Appointments validated so far in this cart.
     * @param string $date Booking date (Y-m-d).
     * @param string $start_time Full start datetime.
     * @param string $end_time Full end datetime.
     * @return bool
     */
    private function hasOverlappingValidated(array $validated, string $date, string $start_time, string $end_time): bool
    {
        $start = strtotime($start_time);
        $end = strtotime($end_time);

        if ($start === false || $end === false) {
            return false;
        }

        foreach ($validated as $validatedAppointment) {
            if ($validatedAppointment['date'] !== $date) {
                continue;
            }

            $validated_start = strtotime($validatedAppointment['start_time']);
            $validated_end = strtotime($validatedAppointment['end_time']);

            if ($validated_start === false || $validated_end === false) {
                continue;
            }

            if ($validated_start < $end && $validated_end > $start) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether an agent row and a customer row describe the same person.
     *
     * The two tables are separate, so the same human can hold a row in each. The
     * linked `wp_user_id` is authoritative; email is the fallback, because a
     * customer created while account auto-creation is switched off never gets one.
     *
     * @param int $agentId Agent row ID.
     * @param int $customerId Customer row ID.
     * @return bool
     */
    private function isAgentTheCustomer(int $agentId, int $customerId): bool
    {
        $agent = AgentModel::find($agentId);
        $customer = CustomerModel::find($customerId);

        if (!$agent || !$customer) {
            return false;
        }

        if (!empty($agent->wp_user_id) && !empty($customer->wp_user_id)) {
            return (int) $agent->wp_user_id === (int) $customer->wp_user_id;
        }

        return !empty($agent->email)
            && !empty($customer->email)
            && strcasecmp($agent->email, $customer->email) === 0;
    }

    /**
     * Create notifications for newly created appointments
     * @param array $appointmentIds
     * @param int $customerId
     * @return void
     */
    private function createAppointmentNotifications(array $appointmentIds, int $customerId): void
    {
        $customerService = new CustomerService();
        $customer = $customerService->getCustomer($customerId);

        foreach ($appointmentIds as $appointmentId) {
            $appointment = AppointmentModel::find($appointmentId);
            if (!$appointment) {
                continue;
            }

            $service = ServiceModel::find($appointment->service_id);

            NotificationService::createAppointmentNotification([
                'admin_user_id' => get_current_user_id(),
                'appointment_id' => $appointment->id,
                'customer_name' => $customer ? $customer->full_name : __('Customer', 'rox-appointment-booking'),
                'service_name' => $service ? $service->title : __('Service', 'rox-appointment-booking')
            ]);
        }
    }

    /**
     * Compute a single appointment's line total: its service price plus any
     * Pro extra-service prices. Shared by calculateServerSubtotal() and
     * calculateServerDepositBreakdown() so both price a line the same way.
     *
     * @param AppointmentModel $appointment
     * @return float
     */
    public function calculateAppointmentLineTotal(AppointmentModel $appointment): float
    {
        $lineTotal = 0.0;

        $service = ServiceModel::find($appointment->service_id);
        if ($service) {
            $lineTotal += (float) $service->price;
        }

        $extraServiceIds = $appointment->extra_services ?? [];
        if (!empty($extraServiceIds) && is_array($extraServiceIds) && class_exists('\\RoxAppointmentBookingPro\\Modules\\ExtraService\\Data\\ExtraServiceModel')) {
            foreach ($extraServiceIds as $extraId) {
                $extraService = \RoxAppointmentBookingPro\Modules\ExtraService\Data\ExtraServiceModel::find(intval($extraId));
                if ($extraService) {
                    $lineTotal += (float) $extraService->price;
                }
            }
        }

        return $lineTotal;
    }

    /**
     * Compute the real order subtotal from the saved appointments' service +
     * extra-service prices, looked up fresh from the DB. This is the
     * authoritative source for what a booking costs — client-submitted
     * amounts are never trusted for billing.
     *
     * @param array $appointmentIds Appointment IDs.
     * @return float
     */
    private function calculateServerSubtotal(array $appointmentIds): float
    {
        $subtotal = 0.0;

        foreach ($appointmentIds as $appointmentId) {
            $appointment = AppointmentModel::find($appointmentId);
            if (!$appointment) {
                continue;
            }

            $subtotal += $this->calculateAppointmentLineTotal($appointment);
        }

        return round($subtotal, 2);
    }

    /**
     * Compute the pre-discount "due now" portion of an order from each
     * appointment's service deposit rule (fixed/percent) — see
     * dev-resources/DEPOSIT_PAYMENT_PLAN.md §5/§7.4. "due now" is the sum of
     * ONLY the deposit-enabled lines' deposit amounts — a line without a
     * deposit contributes nothing extra here; its price is covered either by
     * the full-total fallback in saveOrder() (when no line in the order has
     * a deposit at all) or deferred to amount_due_later alongside any
     * deposit remainder (when at least one line does). Discount is applied
     * once, against the combined due-now total, by the caller (saveOrder()).
     *
     * @param array $appointmentIds Appointment IDs.
     * @return array{due_now: float, deposit_amount: float, requires_online_payment: bool}
     */
    private function calculateServerDepositBreakdown(array $appointmentIds): array
    {
        $dueNow = 0.0;
        $depositAmount = 0.0;
        $requiresOnlinePayment = false;

        // Deposit payments are a Pro feature — without Pro active, every
        // order behaves exactly as it did before this feature existed
        // (full payment due now, Pay Later still available), same gating
        // idiom as allow_without_agent elsewhere in this class.
        if (!defined('ROX_APPOINTMENT_BOOKING_PRO_VERSION')) {
            return [
                'due_now' => 0.0,
                'deposit_amount' => 0.0,
                'requires_online_payment' => false,
            ];
        }

        foreach ($appointmentIds as $appointmentId) {
            $appointment = AppointmentModel::find($appointmentId);
            if (!$appointment) {
                continue;
            }

            $service = ServiceModel::find($appointment->service_id);
            if (!$service || !$service->requiresDeposit()) {
                continue;
            }

            // Amelia model: a deposit requires an online payment method,
            // so Pay Later is not offered for an order like this (§7.1).
            $requiresOnlinePayment = true;

            $lineTotal = $this->calculateAppointmentLineTotal($appointment);
            $lineDeposit = $service->deposit_type === 'percent'
                ? round($lineTotal * ((float) $service->deposit_amount) / 100, 2)
                : min((float) $service->deposit_amount, $lineTotal);

            $dueNow += $lineDeposit;
            $depositAmount += $lineDeposit;
        }

        return [
            'due_now' => round($dueNow, 2),
            'deposit_amount' => round($depositAmount, 2),
            'requires_online_payment' => $requiresOnlinePayment,
        ];
    }

    /**
     * Save an order for frontend booking appointments.
     *
     * @param int $customerId Customer ID.
     * @param array $appointmentIds Appointment IDs.
     * @param array $params Booking request parameters.
     * @return array|WP_Error
     */
    public function saveOrder(int $customerId, array $appointmentIds, array $params): array|WP_Error
    {
        // Server-computed from the just-created appointments' actual service/extra-service
        // prices — never trust the client's original_amount/amount for this. A tampered
        // client value here would otherwise become the authoritative order total charged
        // to Stripe/PayPal.
        $subtotal = $this->calculateServerSubtotal($appointmentIds);
        $depositBreakdown = $this->calculateServerDepositBreakdown($appointmentIds);

        // Deposit-enabled services require an online payment method (Amelia
        // model, DEPOSIT_PAYMENT_PLAN.md §7.1) — Pay Later is not a valid
        // choice once any line in this order needs a deposit.
        $paymentType = $params['payment_type'] ?? 'credit';
        if ($paymentType === 'later' && $depositBreakdown['requires_online_payment']) {
            return new WP_Error(
                'deposit_requires_online_payment',
                esc_html__('One or more selected services require a deposit and must be paid online. Pay Later is not available for this booking.', 'rox-appointment-booking'),
                ['status' => 400]
            );
        }

        // Coupon application is handled by the Pro plugin via this filter. The
        // appointment IDs go with it so restrictions can be judged per line —
        // a coupon limited to one service must not discount the rest of a cart.
        $coupon_result = apply_filters('rox_appointment_booking_apply_coupon', [
            'discount_amount' => 0.0,
            'coupon_id'       => null,
            'coupon_code'     => null,
        ], $params, $customerId, $subtotal, $appointmentIds);

        // The customer was shown a discounted total at checkout. If the coupon
        // stopped being valid in the meantime (expired, someone else took the
        // last use), charging them the full price without a word is worse than
        // failing — the booking is rolled back and they are told why.
        if (!empty($params['coupon_code']) && !empty($coupon_result['error'])) {
            return new WP_Error(
                'coupon_no_longer_valid',
                esc_html($coupon_result['error']),
                ['status' => 400]
            );
        }

        $discount_amount = (float) ($coupon_result['discount_amount'] ?? 0);
        $coupon_id       = $coupon_result['coupon_id'] ?? null;
        $coupon_code     = $coupon_result['coupon_code'] ?? null;

        $total_amount = max(0, round($subtotal - $discount_amount, 2));

        // The customer's explicit "pay in full" choice always wins — must be
        // read here too, not just in PaymentProcessingService, or the order's
        // OWN amount_due_now/amount_due_later would still record a deposit-only
        // split even though the full amount was actually charged, permanently
        // mislabeling a fully-paid order as partially_paid everywhere
        // (My Bookings, Appointment/Order detail views, etc.).
        $payFullNow = strtolower($params['payment_amount_choice'] ?? 'deposit') === 'full';

        // No line in this order has a deposit, OR the customer chose to pay
        // in full → unchanged normal behavior, the full total is due now.
        // Otherwise "due now" is ONLY the deposit lines' deposit sum — a
        // non-deposit line's price is not forced into today, it's deferred
        // to amount_due_later instead. Discount is applied against the
        // due-now portion first (most favorable to the customer);
        // amount_due_later is always just the remainder of total_amount so
        // the two can never drift apart.
        if ($depositBreakdown['requires_online_payment'] && !$payFullNow) {
            $amount_due_now = min($total_amount, max(0, round($depositBreakdown['due_now'] - $discount_amount, 2)));
        } else {
            $amount_due_now = $total_amount;
        }
        $amount_due_later = round($total_amount - $amount_due_now, 2);

        $order = new OrderModel();
        $order->customer_id    = $customerId;
        $order->booking_ids    = json_encode($appointmentIds);
        $order->subtotal       = $subtotal;
        $order->discount_amount = $discount_amount;
        $order->coupon_id      = $coupon_id;
        $order->coupon_code    = $coupon_code;
        $order->total_amount   = $total_amount;
        $order->deposit_amount = $depositBreakdown['deposit_amount'];
        $order->amount_due_now = $amount_due_now;
        $order->amount_due_later = $amount_due_later;
        // The store's configured currency, not a hardcoded USD — every amount
        // on this order is charged in whatever the payment settings say.
        $order->currency       = strtoupper(rox_appointment_booking_payment_settings('payment_currency') ?? 'USD');
        // An order with nothing to pay never reaches a gateway — free services,
        // or a coupon covering the lot; PaymentProcessingService settles it
        // directly — so don't stamp it with a method that was only whatever
        // card happened to be selected on screen. A discount that merely clears
        // the deposit is NOT free: total_amount is still owed, and that balance
        // will be collected through the chosen method.
        $order->payment_method = $total_amount <= 0
            ? 'free'
            : match ($paymentType) {
                'later' => 'pay_later',
                'credit' => 'stripe',
                default => $paymentType,
            };
        $order->payment_status = rox_appointment_booking_payment_settings('default_payment_status', 'unpaid');
        $order->order_status   = rox_appointment_booking_general_settings('default_order_status') ?? 'pending_payment';
        $order->order_date     = current_time('mysql');
        $order->save();

        // Fire action so Pro plugin can link coupon_id to appointments for usage tracking.
        if ($coupon_id) {
            do_action('rox_appointment_booking_after_order_saved', $coupon_id, $appointmentIds);
        }

        return [
            'order_id'         => $order->getID(),
            'order_number'     => $order->getOrderNumber(),
            'total_amount'     => $total_amount,
            'deposit_amount'   => $depositBreakdown['deposit_amount'],
            'amount_due_now'   => $amount_due_now,
            'amount_due_later' => $amount_due_later,
        ];
    }

    /**
     * Update appointment, order, and payment status after payment processing.
     *
     * @param array $appointmentIds Appointment IDs.
     * @param int $orderId Order ID.
     * @param array $paymentResult Payment processing result.
     * @return void
     */
    public function updatePaymentStatus(array $appointmentIds, int $orderId, array $paymentResult): void
    {
        $order = OrderModel::find($orderId);

        $paymentStatus = 'unpaid';
        if ($paymentResult['status'] === 'succeeded') {
            // A deposit-only charge leaves a balance due later — report that
            // as partially_paid rather than paid, so appointment/order/payment
            // all agree with what the customer actually still owes
            // (see dev-resources/DEPOSIT_PAYMENT_PLAN.md §7.7).
            $paymentStatus = ($order && (float) $order->amount_due_later > 0) ? 'partially_paid' : 'paid';
        }

        foreach ($appointmentIds as $appointmentId) {
            $appointment = AppointmentModel::find($appointmentId);
            if ($appointment) {
                $appointment->payment_status = $paymentStatus;
                $appointment->save();
            }
        }

        if ($order) {
            $order->payment_status = $paymentStatus;
            $order->save();

            // Update related payment records
            PaymentModel::where('order_id', $order->id)->update(['status' => $paymentStatus]);
        }
    }

    /**
     * Delete appointments by IDs.
     *
     * @param array $appointmentIds Appointment IDs.
     * @return void
     */
    public function deleteAppointments(array $appointmentIds): void
    {
        foreach ($appointmentIds as $id) {
            $appointment = AppointmentModel::find($id);
            if ($appointment) {
                $appointment->delete();
            }
        }
    }

    /**
     * Delete an order by ID.
     *
     * @param int $orderId Order ID.
     * @return void
     */
    public function deleteOrder(int $orderId): void
    {
        $order = OrderModel::find($orderId);
        if ($order) {
            $order->delete();
        }
    }

    /**
     * Check whether a service is valid for the selected category and location.
     *
     * @param int $serviceId Service ID.
     * @param mixed $categoryId Category ID.
     * @param mixed $locationId Location ID.
     * @return bool
     */
    private function isServiceValidForCategoryAndLocation(int $serviceId, $categoryId, $locationId): bool
    {
        $location_settings = get_option('rox_appointment_booking_location_settings', []);
        $location_module_enabled = isset($location_settings['location_module_enable'])
            ? filter_var($location_settings['location_module_enable'], FILTER_VALIDATE_BOOLEAN)
            : false;

        if (!empty($categoryId)) {
            $hasCategory = ServiceCategoryRelationModel::query()
                ->where('service_id', $serviceId)
                ->where('category_id', (int) $categoryId)
                ->exists();

            if (!$hasCategory) {
                return false;
            }
        }

        if ($location_module_enabled && !empty($locationId)) {
            $hasLocation = ServiceLocationRelationModel::query()
                ->where('service_id', $serviceId)
                ->where('location_id', (int) $locationId)
                ->exists();

            if (!$hasLocation) {
                return false;
            }
        }

        return true;
    }
}
