<?php

namespace RoxAppointmentBooking\Modules\Dashboard\REST;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;
use RoxAppointmentBooking\Modules\Agent\Data\AgentModel;
use RoxAppointmentBooking\Modules\Service\Data\ServiceModel;

/**
 * Class GetDashboardTodayAppointments
 *
 * Returns the list of upcoming appointments for today, enriched with
 * customer avatar, service title, duration, and status.
 *
 * Query params:
 *   date  (Y-m-d)  – target date (default: today)
 *
 * @package RoxAppointmentBooking\Modules\Dashboard\REST
 */
class GetDashboardTodayAppointments extends AbstractREST
{
    /**
     * Whether this REST endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * REST route for this endpoint.
     *
     * @var string
     */
    public static string $route = '/dashboard/today';

    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/dashboard/today';

    /**
     * HTTP methods allowed for this route.
     *
     * @return string
     */
    protected function getMethods(): string
    {
        return 'GET';
    }

    /**
     * Permission check — admin only.
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public function permissionCheck(WP_REST_Request $request): bool
    {
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return false;
        }

        return is_user_logged_in() && current_user_can('manage_options');
    }

    /**
     * Handle the REST request.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $date = sanitize_text_field($request->get_param('date') ?? '');

            if (!$date || !$this->isValidDate($date)) {
                $date = gmdate('Y-m-d');
            }

            // Fetch all appointments for the given date, ordered by start_time
            $appointments = AppointmentModel::query()
                ->where('date', '=', $date)
                ->orderBy('start_time', 'asc')
                ->get();

            if ($appointments->isEmpty()) {
                return rox_appointment_booking_rest_response(
                    data: ['date' => $date, 'items' => []],
                    status: 200,
                    message: esc_html__('No appointments found for today.', 'rox-appointment-booking')
                );
            }

            // Collect IDs for batch lookups
            $customerIds = [];
            $serviceIds  = [];
            $agentIds    = [];

            foreach ($appointments->all() as $appt) {
                if ($appt->customer_id) {
                    $customerIds[] = (int) $appt->customer_id;
                }
                if ($appt->service_id) {
                    $serviceIds[] = (int) $appt->service_id;
                }
                if ($appt->agent_id) {
                    $agentIds[] = (int) $appt->agent_id;
                }
            }

            $customerMap = $this->buildCustomerMap(array_unique($customerIds));
            $serviceMap  = $this->buildServiceMap(array_unique($serviceIds));
            $agentMap    = $this->buildAgentMap(array_unique($agentIds));

            $items = [];
            foreach ($appointments->all() as $appt) {
                $customerId = (int) ($appt->customer_id ?? 0);
                $serviceId  = (int) ($appt->service_id ?? 0);
                $agentId    = (int) ($appt->agent_id ?? 0);

                // Resolve duration from service_details JSON or fallback to service model
                $duration = $this->resolveDuration($appt, $serviceMap, $serviceId);

                $items[] = [
                    'id'         => (int) $appt->id,
                    'date'       => $appt->date,
                    'start_time' => $appt->start_time,
                    'end_time'   => $appt->end_time,
                    'duration'   => $duration,
                    'status'     => $appt->status,
                    'customer'   => [
                        'id'        => $customerId,
                        'name'      => $customerMap[$customerId]['name'] ?? '',
                        'thumbnail' => $customerMap[$customerId]['thumbnail'] ?? null,
                    ],
                    'service'    => [
                        'id'        => $serviceId,
                        'title'     => $serviceMap[$serviceId]['title'] ?? '',
                        'thumbnail' => $serviceMap[$serviceId]['thumbnail'] ?? null,
                    ],
                    'agent'      => [
                        'id'        => $agentId,
                        'name'      => $agentMap[$agentId]['name'] ?? '',
                        'thumbnail' => $agentMap[$agentId]['thumbnail'] ?? null,
                    ],
                ];
            }

            return rox_appointment_booking_rest_response(
                data: ['date' => $date, 'items' => $items],
                status: 200,
                message: esc_html__("Today's appointments retrieved successfully.", 'rox-appointment-booking')
            );
        } catch (\Exception $e) {
            return new WP_Error(
                'dashboard_today_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build a map of customer_id => [name, thumbnail].
     *
     * @param int[] $ids
     * @return array<int, array{name: string, thumbnail: string|null}>
     */
    private function buildCustomerMap(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $customers = CustomerModel::query()->whereIn('id', $ids)->get();
        $map       = [];

        foreach ($customers->all() as $customer) {
            $thumbnailUrl = null;
            if ($customer->thumbnail_id) {
                $thumbnailUrl = wp_get_attachment_image_url((int) $customer->thumbnail_id, 'thumbnail') ?: null;
            }
            $map[(int) $customer->id] = [
                'name'      => trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')),
                'thumbnail' => $thumbnailUrl,
            ];
        }

        return $map;
    }

    /**
     * Build a map of service_id => [title, duration, thumbnail].
     *
     * @param int[] $ids
     * @return array<int, array{title: string, duration: int, thumbnail: string|null}>
     */
    private function buildServiceMap(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $services = ServiceModel::query()->whereIn('id', $ids)->get();
        $map      = [];

        foreach ($services->all() as $service) {
            $thumbnailUrl = null;
            if ($service->thumbnail_id) {
                $thumbnailUrl = wp_get_attachment_image_url((int) $service->thumbnail_id, 'thumbnail') ?: null;
            }
            $map[(int) $service->id] = [
                'title'     => $service->title ?? '',
                'duration'  => (int) ($service->duration ?? 0),
                'thumbnail' => $thumbnailUrl,
            ];
        }

        return $map;
    }

    /**
     * Build a map of agent_id => [name, thumbnail].
     *
     * @param int[] $ids
     * @return array<int, array{name: string, thumbnail: string|null}>
     */
    private function buildAgentMap(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $agents = AgentModel::query()->whereIn('id', $ids)->get();
        $map    = [];

        foreach ($agents->all() as $agent) {
            $thumbnailUrl = null;
            if ($agent->thumbnail_id) {
                $thumbnailUrl = wp_get_attachment_image_url((int) $agent->thumbnail_id, 'thumbnail') ?: null;
            }
            $map[(int) $agent->id] = [
                'name'      => trim(($agent->first_name ?? '') . ' ' . ($agent->last_name ?? '')),
                'thumbnail' => $thumbnailUrl,
            ];
        }

        return $map;
    }

    /**
     * Resolve appointment duration from service_details JSON or the service model.
     *
     * @param object $appt
     * @param array  $serviceMap
     * @param int    $serviceId
     * @return string Duration formatted as "1h10m", "2h", "45m", etc.
     */
    private function resolveDuration(object $appt, array $serviceMap, int $serviceId): string
    {
        // Try service_details JSON first
        $serviceDetails = $appt->service_details;
        if (is_array($serviceDetails) && isset($serviceDetails['duration'])) {
            return $this->formatDuration((int) $serviceDetails['duration']);
        }

        if (is_string($serviceDetails)) {
            $decoded = json_decode($serviceDetails, true);
            if (isset($decoded['duration'])) {
                return $this->formatDuration((int) $decoded['duration']);
            }
        }

        // Fallback to service model
        return $this->formatDuration((int) ($serviceMap[$serviceId]['duration'] ?? 0));
    }

    /**
     * Format a duration in minutes to a human-readable string.
     *
     * Examples: 70 => "1h10m", 120 => "2h", 45 => "45m", 0 => "0m"
     *
     * @param int $minutes
     * @return string
     */
    private function formatDuration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0m';
        }

        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        if ($h > 0 && $m > 0) {
            return "{$h}h{$m}m";
        }

        if ($h > 0) {
            return "{$h}h";
        }

        return "{$m}m";
    }

    /**
     * Validate a Y-m-d date string.
     *
     * @param string $date
     * @return bool
     */
    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
