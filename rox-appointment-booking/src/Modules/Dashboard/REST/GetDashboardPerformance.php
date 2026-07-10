<?php

namespace RoxAppointmentBooking\Modules\Dashboard\REST;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\Order\Data\OrderModel;

/**
 * Class GetDashboardPerformance
 *
 * Returns daily revenue for the given date range.
 *
 * Revenue is stored at the order level. Each order references one or more
 * appointments via a JSON booking_ids column. Revenue is distributed evenly
 * across all bookings in an order, then aggregated per order date.
 *
 * Query params:
 *   start_date  (Y-m-d)  – start of range (default: 30 days ago)
 *   end_date    (Y-m-d)  – end of range (default: today)
 *   agent_id    (int)    – optional, restrict to a single agent
 *   service_id  (int)    – optional, restrict to a single service
 *
 * @package RoxAppointmentBooking\Modules\Dashboard\REST
 */
class GetDashboardPerformance extends AbstractREST
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
    public static string $route = '/dashboard/performance';

    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/dashboard/performance';

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
            // --- Resolve params ---------------------------------------------------
            $startDate = sanitize_text_field($request->get_param('start_date') ?? '');
            $endDate   = sanitize_text_field($request->get_param('end_date') ?? '');
            $agentId   = absint($request->get_param('agent_id') ?? 0);
            $serviceId = absint($request->get_param('service_id') ?? 0);

            if (!$startDate || !$this->isValidDate($startDate)) {
                $startDate = gmdate('Y-m-d', strtotime('-29 days'));
            }

            if (!$endDate || !$this->isValidDate($endDate)) {
                $endDate = gmdate('Y-m-d');
            }

            if ($startDate > $endDate) {
                [$startDate, $endDate] = [$endDate, $startDate];
            }

            // --- 1. Fetch appointments in the date range --------------------------
            $apptQuery = AppointmentModel::query()
                ->select(['id', 'agent_id', 'service_id'])
                ->whereRaw('DATE(`created_at`) BETWEEN %s AND %s', [$startDate, $endDate]);

            if ($agentId > 0) {
                $apptQuery->where('agent_id', '=', $agentId);
            }

            if ($serviceId > 0) {
                $apptQuery->where('service_id', '=', $serviceId);
            }

            $appointments = $apptQuery->get()->all();

            // Build lookup: appointment_id => { agent_id, service_id }
            $apptMap = [];
            foreach ($appointments as $appt) {
                $apptMap[(int) $appt->id] = [
                    'agent_id'   => (int) $appt->agent_id,
                    'service_id' => (int) $appt->service_id,
                ];
            }

            $emptyDaily = $this->buildEmptyDailyRange($startDate, $endDate);

            // Early-return when no appointments match the filters
            if (empty($apptMap)) {
                return rox_appointment_booking_rest_response(
                    data: [
                        'period'        => ['start' => $startDate, 'end' => $endDate],
                        'daily_revenue' => $emptyDaily,
                    ],
                    status: 200,
                    message: esc_html__('Dashboard performance data retrieved successfully.', 'rox-appointment-booking')
                );
            }

            // --- 2. Fetch orders that reference any of these appointments ----------
            $apptIds = array_keys($apptMap);

            $orderRows = OrderModel::query()
                ->select(['booking_ids', 'total_amount'])
                ->selectRaw('DATE(created_at) AS order_date')
                ->whereRaw('JSON_OVERLAPS(booking_ids, %s)', [json_encode(array_values($apptIds))])
                ->get()
                ->all();

            // --- 3. Distribute revenue across appointments ------------------------
            // Each booking in an order receives an equal share of the order's total.
            // Only the appointments in our filtered set are credited.
            $dailyBuckets = []; // date string => float

            foreach ($orderRows as $order) {
                $bookingIds = is_array($order->booking_ids)
                    ? $order->booking_ids
                    : (json_decode($order->booking_ids, true) ?? []);

                $totalInOrder = count($bookingIds);
                if ($totalInOrder === 0) {
                    continue;
                }

                $perBooking = (float) $order->total_amount / $totalInOrder;
                $orderDate  = $order->order_date; // already DATE() from selectRaw

                foreach ($bookingIds as $bid) {
                    if (!isset($apptMap[(int) $bid])) {
                        continue;
                    }
                    $dailyBuckets[$orderDate] = ($dailyBuckets[$orderDate] ?? 0.0) + $perBooking;
                }
            }

            // --- 4. Fill daily gaps -----------------------------------------------
            $dailyRevenue = $emptyDaily;
            foreach ($dailyRevenue as &$day) {
                $day['revenue'] = round($dailyBuckets[$day['date']] ?? 0.0, 2);
            }
            unset($day);

            return rox_appointment_booking_rest_response(
                data: [
                    'period'        => ['start' => $startDate, 'end' => $endDate],
                    'daily_revenue' => $dailyRevenue,
                ],
                status: 200,
                message: esc_html__('Dashboard performance data retrieved successfully.', 'rox-appointment-booking')
            );
        } catch (\Exception $e) {
            return new WP_Error(
                'dashboard_performance_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build a zero-filled daily revenue array covering every date in the range.
     *
     * @param string $start Y-m-d
     * @param string $end   Y-m-d
     * @return array<int, array{date: string, revenue: float}>
     */
    private function buildEmptyDailyRange(string $start, string $end): array
    {
        $result  = [];
        $current = new \DateTime($start);
        $endDt   = new \DateTime($end);
        while ($current <= $endDt) {
            $result[] = ['date' => $current->format('Y-m-d'), 'revenue' => 0.0];
            $current->modify('+1 day');
        }
        return $result;
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
