<?php

namespace RoxAppointmentBooking\Modules\Calendar\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Supports\Security;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\Appointment\Services\AppointmentService;
use RoxAppointmentBooking\Modules\Agent\Services\AgentService;
use RoxAppointmentBooking\Modules\Service\Services\ServiceService;

/**
 * Class GetAppointmentsGrouped
 *
 * @package RoxAppointmentBooking\Modules\Calendar\REST
 * @description Returns appointments grouped by date for the calendar sidebar eventsList section.
 */
class GetAppointmentsGrouped extends AbstractREST
{
    /**
     * Whether the endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for grouped appointments.
     *
     * @var string
     */
    public static string $route = '/appointments/grouped';
    /**
     * Usable route template for docs.
     *
     * @var string
     */
    public static string $usableRoute = '/appointments/grouped';

    /**
     * Get the methods allowed for this route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'GET';
    }

    /**
     * Handle the REST API request.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $start      = $request->get_param('start');
        $end        = $request->get_param('end');
        $agentId    = $request->get_param('agent_id');
        $locationId = $request->get_param('location_id');
        $serviceId  = $request->get_param('service_id');
        $status     = $request->get_param('status');

        try {
            $query = AppointmentModel::query();

            if (!Security::canManageBookings()) {
                if (AppointmentService::isCustomerUser()) {
                    $currentCustomerId = AppointmentService::getCurrentCustomerId();
                    if ($currentCustomerId) {
                        $query->where('customer_id', $currentCustomerId);
                    } else {
                        $query->where('id', 0);
                    }
                } elseif (AppointmentService::isAgentUser()) {
                    $currentAgentId = AppointmentService::getCurrentAgentId();
                    if ($currentAgentId) {
                        $query->where('agent_id', $currentAgentId);
                    } else {
                        $query->where('id', 0);
                    }
                }
            }

            if (!empty($start)) {
                $query->where('date', '>=', $start);
            }

            if (!empty($end)) {
                $query->where('date', '<=', $end);
            }

            if (!empty($agentId)) {
                $query->where('agent_id', $agentId);
            }

            if (!empty($locationId)) {
                $query->where('location_id', $locationId);
            }

            if (!empty($serviceId)) {
                $query->where('service_id', $serviceId);
            }

            if (!empty($status)) {
                $query->where('status', $status);
            }

            $appointments = $query->orderBy('date', 'ASC')
                                  ->orderBy('start_time', 'ASC')
                                  ->get();

            $agentService   = new AgentService();
            $serviceService = new ServiceService();

            // Group appointments by date
            $grouped = [];

            foreach ($appointments as $appointment) {
                $apptData = $appointment->toArray();
                $date     = $apptData['date'] ?? null;

                if (!$date) {
                    continue;
                }

                // Resolve agent name
                $agentName = '';
                if (!empty($apptData['agent_id'])) {
                    $agent = $agentService->getAgent((int) $apptData['agent_id']);
                    if ($agent) {
                        $agentName = $agent->full_name ?? '';
                    }
                }

                // Resolve service/title name
                $titleName = '';
                if (!empty($apptData['service_id'])) {
                    $service = $serviceService->getService((int) $apptData['service_id']);
                    if ($service) {
                        $titleName = $service->title ?? '';
                    }
                }

                // Format time as "10:00 AM"
                $startTime     = $apptData['start_time'] ?? '';
                $timeFormatted = $startTime ? gmdate('g:i A', strtotime($startTime)) : '';

                $event = [
                    'id'    => $apptData['id'],
                    'agent' => $agentName,
                    'title' => $titleName,
                    'time'  => $timeFormatted,
                ];

                if (!isset($grouped[$date])) {
                    $grouped[$date] = [];
                }

                $grouped[$date][] = $event;
            }

            // Build final array sorted by date
            $result = [];
            ksort($grouped);
            foreach ($grouped as $date => $events) {
                $result[] = [
                    'date'   => $date,
                    'events' => $events,
                ];
            }

            return rox_appointment_booking_rest_response(
                data: $result,
                message: esc_html__('Appointments grouped by date retrieved successfully', 'rox-appointment-booking')
            );

        } catch (\Exception $e) {
            return rox_appointment_booking_rest_response(
                data: [],
                message: esc_html__('Failed to retrieve grouped appointments', 'rox-appointment-booking')
            );
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

        if (!is_user_logged_in() || !Security::canAccessPanel()) {
            return false;
        }

        return true;
    }
}
