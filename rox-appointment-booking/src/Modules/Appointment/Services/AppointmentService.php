<?php

namespace RoxAppointmentBooking\Modules\Appointment\Services;

use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\Agent\Data\AgentModel;
use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;
use RoxAppointmentBooking\Modules\Customer\Services\CustomerService;
use RoxAppointmentBooking\Modules\Notification\Services\NotificationService;
use RoxAppointmentBooking\Modules\Service\Data\ServiceModel;

/**
 * Class AppointmentService
 * 
 * @package RoxAppointmentBooking\Modules\Appointment\Services
 * @description Handles appointment-related business logic.
 */
class AppointmentService
{
    /**
     * Whether the service should be loadable.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * Determine whether the current user has the agent role.
     *
     * @return bool
     */
    public static function isAgentUser(): bool
    {
        $user = wp_get_current_user();
        if (!$user || !$user->exists()) {
            return false;
        }

        return in_array('rox_appointment_booking_agent', (array) $user->roles, true);
    }

    /**
     * Resolve the current logged-in agent ID if available.
     *
     * The linked `wp_user_id` is authoritative. Email is only a fallback, for
     * agent rows created before the login link existed — and only when that row is
     * not already linked to a different WordPress user, otherwise a matching email
     * alone would hand one user another user's agent record.
     *
     * @return int|null
     */
    public static function getCurrentAgentId(): ?int
    {
        $user = wp_get_current_user();
        if (!$user || !$user->exists()) {
            return null;
        }

        $agent = AgentModel::query()
            ->where('wp_user_id', $user->ID)
            ->first();

        if (!$agent && $user->user_email) {
            $candidate = AgentModel::query()
                ->where('email', $user->user_email)
                ->first();

            // Accept the email match only while that row has no login link of its
            // own (covers both NULL and 0).
            if ($candidate && empty($candidate->wp_user_id)) {
                $agent = $candidate;
            }
        }

        return $agent ? (int) $agent->getID() : null;
    }

    /**
     * Resolve the customer record backing the current logged-in user, if any.
     *
     * Deliberately mirrors getCurrentAgentId(): the linked `wp_user_id` is
     * authoritative and email is only a fallback, for customer rows created
     * before the login link existed — accepted only while that row carries no
     * login link of its own, otherwise a shared email alone would hand one user
     * another user's bookings.
     *
     * One WordPress user can back BOTH an agent row and a customer row: an agent
     * who books through the public panel with their own email keeps their agent
     * record and additionally gets a customer row linked to the same user id (see
     * FrontendBookingPanel\Services\CustomerService::handleAutoUserCreation).
     * This resolves the customer side, which is what the panel's "My Bookings"
     * page lists.
     *
     * @return int|null
     */
    public static function getCurrentCustomerId(): ?int
    {
        $user = wp_get_current_user();
        if (!$user || !$user->exists()) {
            return null;
        }

        $customer = CustomerModel::query()
            ->where('wp_user_id', $user->ID)
            ->first();

        if (!$customer && $user->user_email) {
            $candidate = CustomerModel::query()
                ->where('email', $user->user_email)
                ->first();

            // Accept the email match only while that row has no login link of its
            // own (covers both NULL and 0).
            if ($candidate && empty($candidate->wp_user_id)) {
                $customer = $candidate;
            }
        }

        return $customer ? (int) $customer->getID() : null;
    }

    /**
     * Get all appointments with optional filtering
     * 
     * @param array $filters Filter parameters
     * @param int $page Page number
     * @param int $per_page Items per page
     * @return array Array containing appointments and pagination info
     */
    public function getAppointments(array $filters = [], int $page = 1, int $per_page = 10): array
    {
        $query = AppointmentModel::query();

        // Apply filters
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'LIKE', "%{$filters['search']}%")
                    ->orWhere('description', 'LIKE', "%{$filters['search']}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('appointment_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('appointment_date', '<=', $filters['date_to']);
        }

        $total = $query->count();

        $appointments = $query->offset(($page - 1) * $per_page)
            ->limit($per_page)
            ->orderBy('appointment_date', 'ASC')
            ->get();

        // Enhance appointments with customer information
        $customerService = new CustomerService();
        $appointmentsData = $appointments->toArray();
        foreach ($appointmentsData as &$appointment) {
            if (isset($appointment['customer_id'])) {
                $customer = $customerService->getCustomer($appointment['customer_id']);
                if ($customer) {
                    $appointment['customer_name'] = $customer->full_name;
                }
            }
        }

        return [
            'items' => $appointmentsData,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ];
    }

    /**
     * Get appointment by ID
     * 
     * @param int $id Appointment ID
     * @return array|null Appointment data with customer information
     */
    public function getAppointment(int $id): ?array
    {
        $appointment = AppointmentModel::find($id);
        if (!$appointment) {
            return null;
        }

        $appointmentData = $appointment->toArray();

        // Add customer information if available
        if (isset($appointmentData['customer_id'])) {
            $customerService = new CustomerService();
            $customer = $customerService->getCustomer($appointmentData['customer_id']);
            if ($customer) {
                $appointmentData['customer_name'] = $customer->full_name;
            }
        }

        return $appointmentData;
    }

    /**
     * Create or update an appointment
     * 
     * @param array $data Appointment data
     * @param int|null $id Appointment ID for updates
     * @return AppointmentModel
     * @throws \Exception If validation fails
     */
    public function saveAppointment(array $data, ?int $id = null): AppointmentModel
    {
        // Validate required fields
        $required = ['customer_id', 'appointment_date', 'title'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                // translators: %s = field name
                throw new \Exception(sprintf(esc_html__('%s is required', 'rox-appointment-booking'), esc_html($field)));
            }
        }

        // Validate customer exists
        $customerService = new CustomerService();
        $customer = $customerService->getCustomer($data['customer_id']);
        if (!$customer) {
            throw new \Exception(esc_html__('Customer not found', 'rox-appointment-booking'));
        }

        // Check for date conflicts
        $conflictQuery = AppointmentModel::query()
            ->where('appointment_date', $data['appointment_date'])
            ->when($id, function ($q) use ($id) {
                $q->where('id', '!=', $id);
            });

        if ($conflictQuery->exists()) {
            throw new \Exception(esc_html__('An appointment already exists at this time', 'rox-appointment-booking'));
        }

        // Get or create appointment
        $appointment = $id ? AppointmentModel::find($id) : new AppointmentModel();
        if ($id && !$appointment) {
            throw new \Exception(esc_html__('Appointment not found', 'rox-appointment-booking'));
        }

        // Fill and save data
        $appointment->fill($data);
        $appointment->save();

        if ($appointment) {
            NotificationService::createAppointmentNotification([
                'appointment_id' => $appointment->id,
                'customer_name'  => $customer->full_name,
                'service_name'   => $data['service_name'] ?? esc_html__('Service', 'rox-appointment-booking')
            ]);
        }

        return $appointment;
    }

    /**
     * Delete an appointment
     * 
     * @param int $id Appointment ID
     * @return bool
     * @throws \Exception If appointment not found or deletion fails
     */
    public function deleteAppointment(int $id): bool
    {
        $appointment = AppointmentModel::find($id);
        if (!$appointment) {
            throw new \Exception(esc_html__('Appointment not found', 'rox-appointment-booking'));
        }

        return $appointment->delete();
    }

    /**
     * Check appointment availability for a given date
     * 
     * @param string $date Date to check
     * @return bool
     */
    public function isDateAvailable(string $date): bool
    {
        return !AppointmentModel::query()
            ->where('appointment_date', $date)
            ->exists();
    }

    /**
     * Update appointment status
     * 
     * @param int $id Appointment ID
     * @param string $status New status
     * @return AppointmentModel
     * @throws \Exception If appointment not found or status is invalid
     */
    public function updateStatus(int $id, string $status): AppointmentModel
    {
        $validStatuses = ['scheduled', 'confirmed', 'cancelled', 'completed'];

        if (!in_array($status, $validStatuses)) {
            throw new \Exception(esc_html__('Invalid appointment status', 'rox-appointment-booking'));
        }

        $appointment = AppointmentModel::find($id);
        if (!$appointment) {
            throw new \Exception(esc_html__('Appointment not found', 'rox-appointment-booking'));
        }

        $appointment->status = $status;
        $appointment->save();

        return $appointment;
    }

    /**
     * Send notification for a new appointment created by admin
     * 
     * @param AppointmentModel $appointment
     * @return void
     */
    public function sendAdminBookingNotification(AppointmentModel $appointment): void
    {
        $notificationData = $this->getNotificationData($appointment);
        NotificationService::createAdminBookingNotification($notificationData);
    }

    /**
     * Send the booking-confirmation e-mails for an appointment created from the
     * admin panel — customer, agent and admin, each subject to its own toggle in
     * Settings → E-mail. The agent and customer are read off the appointment.
     *
     * No dashboard notification here: that is always handled separately by
     * sendAdminBookingNotification().
     *
     * @param AppointmentModel $appointment
     * @param int|null $orderId Order the appointment was booked under.
     * @return void
     */
    public function sendAppointmentNotification(AppointmentModel $appointment, ?int $orderId = null): void
    {
        do_action(
            'rox_appointment_booking_email_event',
            'booking_confirmed',
            [
                'appointment_ids' => [(int) $appointment->id],
                'customer_id'     => (int) $appointment->customer_id,
                'order_id'        => $orderId,
            ]
        );
    }

    /**
     * Send notification for an appointment reschedule (date/slot change via admin form)
     *
     * @param AppointmentModel $appointment
     * @param string $oldDate
     * @param string $oldStartTime
     * @param string $newDate
     * @param string $newStartTime
     * @return void
     */
    public function sendRescheduleNotification(AppointmentModel $appointment, string $oldDate, string $oldStartTime, string $newDate, string $newStartTime): void
    {
        $notificationData = $this->getNotificationData($appointment);
        $notificationData['old_date']       = $oldDate;
        $notificationData['old_start_time'] = $oldStartTime;
        $notificationData['new_date']       = $newDate;
        $notificationData['new_start_time'] = $newStartTime;
        NotificationService::createRescheduleNotification($notificationData);

        do_action(
            'rox_appointment_booking_email_event',
            'booking_rescheduled',
            [
                'appointment_ids' => [(int) $appointment->id],
                'customer_id'     => (int) $appointment->customer_id,
                'old_date'        => $oldDate,
                'old_time'        => $oldStartTime,
                'new_date'        => $newDate,
                'new_time'        => $newStartTime,
            ]
        );
    }

    /**
     * Send notification for an appointment status change
     *
     * @param AppointmentModel $appointment
     * @param string $newStatus
     * @return void
     */
    public function sendStatusChangeNotification(AppointmentModel $appointment, string $newStatus): void
    {
        $notificationData = $this->getNotificationData($appointment);
        NotificationService::createStatusChangeNotification($notificationData, $newStatus);

        // Cancellation always warrants its own wording; every other status
        // change shares the generic template.
        do_action(
            'rox_appointment_booking_email_event',
            $newStatus === 'cancelled' ? 'booking_cancelled' : 'booking_status_changed',
            [
                'appointment_ids'    => [(int) $appointment->id],
                'customer_id'        => (int) $appointment->customer_id,
                'appointment_status' => $newStatus,
            ]
        );
    }

    /**
     * Get data required for notifications
     * 
     * @param AppointmentModel $appointment
     * @return array
     */
    protected function getNotificationData(AppointmentModel $appointment): array
    {
        $customerService = new CustomerService();
        $customer = $customerService->getCustomer($appointment->customer_id);
        $service = ServiceModel::find($appointment->service_id);

        return [
            'admin_user_id'  => get_current_user_id(),
            'appointment_id' => $appointment->id,
            'customer_name'  => $customer ? $customer->full_name : __('Customer', 'rox-appointment-booking'),
            'service_name'   => $service ? $service->title : __('Service', 'rox-appointment-booking')
        ];
    }
}
