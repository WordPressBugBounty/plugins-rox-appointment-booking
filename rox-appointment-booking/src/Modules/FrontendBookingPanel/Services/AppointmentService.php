<?php
namespace RoxAppointmentBooking\Modules\FrontendBookingPanel\Services;

use WP_Error;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\Order\Data\OrderModel;
use RoxAppointmentBooking\Modules\Payment\Data\PaymentModel;
use RoxAppointmentBooking\Modules\Notification\Services\NotificationService;
use RoxAppointmentBooking\Modules\Customer\Services\CustomerService;
use RoxAppointmentBooking\Modules\Service\Data\ServiceModel;
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

        $appointmentIds = [];
        
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

            if (!$this->isServiceValidForCategoryAndLocation($service->getID(), $appointmentData['category_id'] ?? null, $appointmentData['location_id'] ?? null)) {
                return new WP_Error(
                    'service_scope_mismatch',
                    esc_html__('Selected service is not available for the chosen category or location.', 'rox-appointment-booking'),
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

            if ($allow_without_agent) {
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
                    ->where('status', '!=', 'cancelled')
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
                    ->where('status', '!=', 'cancelled')
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

            $appointment = new AppointmentModel();
            $appointment->fill([
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
                'total_attendees' => $appointmentData['total_attendees'] ?? 1,
            ]);
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
     * Save an order for frontend booking appointments.
     *
     * @param int $customerId Customer ID.
     * @param array $appointmentIds Appointment IDs.
     * @param array $params Booking request parameters.
     * @return array|WP_Error
     */
    public function saveOrder(int $customerId, array $appointmentIds, array $params): array|WP_Error
    {
        $subtotal     = floatval($params['original_amount'] ?? $params['amount'] ?? 0);
        $total_amount = floatval($params['amount'] ?? 0);

        // Coupon application is handled by the Pro plugin via this filter.
        $coupon_result = apply_filters('rox_appointment_booking_apply_coupon', [
            'discount_amount' => 0.0,
            'coupon_id'       => null,
            'coupon_code'     => null,
        ], $params, $customerId, $subtotal);

        $discount_amount = (float) ($coupon_result['discount_amount'] ?? 0);
        $coupon_id       = $coupon_result['coupon_id'] ?? null;
        $coupon_code     = $coupon_result['coupon_code'] ?? null;

        $order = new OrderModel();
        $order->customer_id    = $customerId;
        $order->booking_ids    = json_encode($appointmentIds);
        $order->subtotal       = $subtotal;
        $order->discount_amount = $discount_amount;
        $order->coupon_id      = $coupon_id;
        $order->coupon_code    = $coupon_code;
        $order->total_amount   = $total_amount;
        $order->currency       = 'USD';
        $order->payment_method = $params['payment_type'] === 'later' ? 'pay_later' : 'stripe';
        $order->payment_status = rox_appointment_booking_payment_settings('default_payment_status', 'unpaid');
        $order->order_status   = rox_appointment_booking_general_settings('default_order_status') ?? 'pending_payment';
        $order->order_date     = current_time('mysql');
        $order->save();

        // Fire action so Pro plugin can link coupon_id to appointments for usage tracking.
        if ($coupon_id) {
            do_action('rox_appointment_booking_after_order_saved', $coupon_id, $appointmentIds);
        }

        return [
            'order_id'     => $order->getID(),
            'order_number' => $order->getOrderNumber()
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
        $paymentStatus = $paymentResult['status'] === 'succeeded' ? 'paid' : 'unpaid';

        foreach ($appointmentIds as $appointmentId) {
            $appointment = AppointmentModel::find($appointmentId);
            if ($appointment) {
                $appointment->payment_status = $paymentStatus;
                $appointment->save();
            }
        }

        $order = OrderModel::find($orderId);
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
