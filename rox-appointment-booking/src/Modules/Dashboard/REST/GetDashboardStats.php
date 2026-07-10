<?php

namespace RoxAppointmentBooking\Modules\Dashboard\REST;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\Order\Data\OrderModel;
use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;
use RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingQueryBuilder;

/**
 * Class GetDashboardStats
 *
 * Returns aggregated stats for the dashboard overview cards:
 *   1. Booking/appointment count + % change vs previous period.
 *   2. Revenue (total_amount from orders) + % change vs previous period.
 *   3. Total customer count (new vs returning) + % change vs previous period.
 *   4. Pending approval count and emergency appointment count.
 *
 * Query params:
 *   start_date  (Y-m-d)  – beginning of current period (default: start of current week)
 *   end_date    (Y-m-d)  – end of current period (default: today)
 *   agent_id    (int)    – optional: restrict all stats to a specific agent
 *   service_id  (int)    – optional: restrict all stats to a specific service
 *
 * @package RoxAppointmentBooking\Modules\Dashboard\REST
 */
class GetDashboardStats extends AbstractREST
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
    public static string $route = '/dashboard/stats';

    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/dashboard/stats';

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
            // --- Resolve date range ------------------------------------------------
            $startDate = sanitize_text_field($request->get_param('start_date') ?? '');
            $endDate   = sanitize_text_field($request->get_param('end_date') ?? '');

            // --- Resolve optional agent / service filters -------------------------
            $agentId   = absint($request->get_param('agent_id') ?? 0);
            $serviceId = absint($request->get_param('service_id') ?? 0);

            $filters = [];
            if ($agentId > 0) {
                $filters['agent_id'] = $agentId;
            }
            if ($serviceId > 0) {
                $filters['service_id'] = $serviceId;
            }

            if (!$startDate || !$this->isValidDate($startDate)) {
                // Default: start of current week (Monday)
                $startDate = gmdate('Y-m-d', strtotime('monday this week'));
            }

            if (!$endDate || !$this->isValidDate($endDate)) {
                $endDate = gmdate('Y-m-d');
            }

            // Swap if inverted
            if ($startDate > $endDate) {
                [$startDate, $endDate] = [$endDate, $startDate];
            }

            // --- Compute previous period of same length ----------------------------
            $currentStart = new \DateTime($startDate);
            $currentEnd   = new \DateTime($endDate);
            $diffDays     = (int) $currentStart->diff($currentEnd)->days;

            $prevEnd   = (clone $currentStart)->modify('-1 day');
            $prevStart = (clone $prevEnd)->modify('-' . $diffDays . ' days');

            $prevStartStr = $prevStart->format('Y-m-d');
            $prevEndStr   = $prevEnd->format('Y-m-d');

            // --- 1. Booking counts ------------------------------------------------
            $currentBookings  = $this->countBookings($startDate, $endDate, $filters);
            $previousBookings = $this->countBookings($prevStartStr, $prevEndStr, $filters);
            $bookingChange    = $this->percentChange($previousBookings, $currentBookings);

            // --- 2. Revenue -------------------------------------------------------
            $currentRevenue  = $this->sumRevenue($startDate, $endDate, $filters);
            $previousRevenue = $this->sumRevenue($prevStartStr, $prevEndStr, $filters);
            $revenueChange   = $this->percentChange($previousRevenue, $currentRevenue);

            // --- 3. Customers -----------------------------------------------------
            $customerData    = $this->getCustomerStats($startDate, $endDate, $filters);
            $prevCustomers   = $this->countCustomers($prevStartStr, $prevEndStr, $filters);
            $customerChange  = $this->percentChange($prevCustomers, $customerData['total']);

            // --- 4. Pending approvals + emergency ---------------------------------
            $pendingCount   = $this->countByStatus('pending', $startDate, $endDate, $filters);
            $rescheduledCount = $this->countByStatus('rescheduled', $startDate, $endDate, $filters);
            $awaitingCount  = $this->countByStatus('awaiting', $startDate, $endDate, $filters);
            $emergencyCount = $this->countByStatus('emergency', $startDate, $endDate, $filters);

            // --- 5. Graph data (daily breakdown) ----------------------------
            $dailyRevenue   = $this->getDailyRevenue($startDate, $endDate, $filters);
            $dailyBookings  = $this->getDailyBookings($startDate, $endDate, $filters);

            // --- Build response ---------------------------------------------------
            $data = [
                'bookings' => [
                    'count'           => $currentBookings,
                    'change_percent'  => $bookingChange,
                    'trend'           => $bookingChange >= 0 ? 'up' : 'down',
                    'period'          => ['start' => $startDate, 'end' => $endDate],
                    'graph'  => $dailyBookings,
                ],
                'revenue' => [
                    'total'           => round((float) $currentRevenue, 2),
                    'change_percent'  => $revenueChange,
                    'trend'           => $revenueChange >= 0 ? 'up' : 'down',
                    'period'          => ['start' => $startDate, 'end' => $endDate],
                    'graph'           => $dailyRevenue,
                ],
                'customers' => [
                    'total'          => $customerData['total'],
                    'new'            => $customerData['new'],
                    'returning'      => $customerData['returning'],
                    'change_percent' => $customerChange,
                    'trend'          => $customerChange >= 0 ? 'up' : 'down',
                    'period'         => ['start' => $startDate, 'end' => $endDate],
                ],
                'pending_approvals' => [
                    'total'     => $pendingCount + $awaitingCount + $emergencyCount + $rescheduledCount,
                    'emergency' => $emergencyCount,
                    'others'    => $pendingCount + $awaitingCount + $rescheduledCount,
                ],
            ];

            return rox_appointment_booking_rest_response(
                data: $data,
                status: 200,
                message: esc_html__('Dashboard stats retrieved successfully.', 'rox-appointment-booking')
            );
        } catch (\Exception $e) {
            return new WP_Error(
                'dashboard_stats_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Apply agent_id and/or service_id filters to an appointment query.
     *
     * @param RoxAppointmentBookingQueryBuilder $query   Query builder instance (from Model::query()).
     * @param array                             $filters  Keys: agent_id, service_id.
     * @return void
     */
    private function applyAppointmentFilters(RoxAppointmentBookingQueryBuilder $query, array $filters): void
    {
        if (!empty($filters['agent_id'])) {
            $query->where('agent_id', '=', (int) $filters['agent_id']);
        }
        if (!empty($filters['service_id'])) {
            $query->where('service_id', '=', (int) $filters['service_id']);
        }
    }

    /**
     * Return daily revenue totals for each date in the range.
     *
     * Excludes orders linked to cancelled and rejected appointments.
     * Revenue is grouped by appointment date, not order date.
     *
     * @param string $start   Y-m-d
     * @param string $end     Y-m-d
     * @param array  $filters Optional agent_id / service_id filter.
     * @return array<int, array{date: string, revenue: float}>
     */
    private function getDailyRevenue(string $start, string $end, array $filters = []): array
    {
        // Get valid appointment IDs with their dates, excluding cancelled and rejected
        $validAppointmentsQuery = AppointmentModel::query()
            ->select(['id', 'date'])
            ->whereRaw('DATE(`created_at`) BETWEEN %s AND %s', [$start, $end])
            ->whereNotIn('status', ['cancelled', 'rejected']);
        $this->applyAppointmentFilters($validAppointmentsQuery, $filters);
        $validAppointmentRows = $validAppointmentsQuery->get()->all();
        $validAppointmentIds = array_map(fn($row) => (int) $row->id, $validAppointmentRows);

        if (empty($validAppointmentIds)) {
            // No valid appointments — revenue is zero for every day.
            $result  = [];
            $current = new \DateTime($start);
            $endDt   = new \DateTime($end);
            while ($current <= $endDt) {
                $result[] = ['date' => $current->format('Y-m-d'), 'revenue' => 0.0];
                $current->modify('+1 day');
            }
            return $result;
        }

        // Build a map of appointment_id => date for grouping
        $appointmentDates = [];
        foreach ($validAppointmentRows as $row) {
            $appointmentDates[(int) $row->id] = (new \DateTime($row->created_at))->format('Y-m-d');
        }

        // Fetch orders linked to valid appointments
        $qb = OrderModel::query()
            ->select(['booking_ids', 'total_amount'])
            ->whereRaw(
                'JSON_OVERLAPS(booking_ids, %s)',
                [json_encode(array_values($validAppointmentIds))]
            );

        $rows = $qb->get()->all();

        // Group revenue by appointment date
        $indexed = [];
        foreach ($rows as $row) {
            $bookingIds = is_array($row->booking_ids) ? $row->booking_ids : (json_decode($row->booking_ids, true) ?? []);
            foreach ($bookingIds as $bookingId) {
                if (isset($appointmentDates[$bookingId])) {
                    $date = $appointmentDates[$bookingId];
                    if (!isset($indexed[$date])) {
                        $indexed[$date] = 0.0;
                    }
                    $indexed[$date] += (float) $row->total_amount;
                }
            }
        }

        // Round all values
        foreach ($indexed as $date => $revenue) {
            $indexed[$date] = round($revenue, 2);
        }

        $result  = [];
        $current = new \DateTime($start);
        $endDt   = new \DateTime($end);
        while ($current <= $endDt) {
            $dateStr  = $current->format('Y-m-d');
            $result[] = [
                'date'    => $dateStr,
                'revenue' => $indexed[$dateStr] ?? 0.0,
            ];
            $current->modify('+1 day');
        }

        return $result;
    }

    /**
     * Return daily booking counts for each date in the range.
     *
     * Excludes cancelled and rejected appointments.
     *
     * @param string $start   Y-m-d
     * @param string $end     Y-m-d
     * @param array  $filters Optional agent_id / service_id filter.
     * @return array<int, array{date: string, bookings: int}>
     */
    private function getDailyBookings(string $start, string $end, array $filters = []): array
    {
        $qb = AppointmentModel::query()
            ->selectRaw('DATE(`created_at`) as booking_date, COUNT(*) as count')
            ->whereRaw('DATE(`created_at`) BETWEEN %s AND %s', [$start, $end])
            ->whereNotIn('status', ['cancelled', 'rejected']);

        $this->applyAppointmentFilters($qb, $filters);

        $rows = $qb->groupByRaw('DATE(`created_at`)')
            ->get()
            ->all();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row->booking_date] = (int) $row->count;
        }

        $result  = [];
        $current = new \DateTime($start);
        $endDt   = new \DateTime($end);
        while ($current <= $endDt) {
            $dateStr  = $current->format('Y-m-d');
            $result[] = [
                'date'     => $dateStr,
                'bookings' => $indexed[$dateStr] ?? 0,
            ];
            $current->modify('+1 day');
        }

        return $result;
    }

    /**
     * Count bookings within a date range (inclusive).
     *
     * Excludes cancelled and rejected appointments.
     *
     * @param string $start   Y-m-d
     * @param string $end     Y-m-d
     * @param array  $filters Optional agent_id / service_id filter.
     * @return int
     */
    private function countBookings(string $start, string $end, array $filters = []): int
    {
        $query = AppointmentModel::query()
            ->whereRaw('DATE(`created_at`) BETWEEN %s AND %s', [$start, $end])
            ->whereNotIn('status', ['cancelled', 'rejected']);
        $this->applyAppointmentFilters($query, $filters);
        return $query->count();
    }

    /**
     * Sum order total_amount for orders whose linked appointments fall in the date range.
     *
     * Excludes orders linked to cancelled and rejected appointments.
     * Revenue is filtered by appointment date, not order date.
     *
     * @param string $start   Y-m-d
     * @param string $end     Y-m-d
     * @param array  $filters Optional agent_id / service_id filter.
     * @return float
     */
    private function sumRevenue(string $start, string $end, array $filters = []): float
    {
        // Get valid appointment IDs with their dates, excluding cancelled and rejected
        $validAppointmentsQuery = AppointmentModel::query()
            ->select(['id'])
            ->whereRaw('DATE(`created_at`) BETWEEN %s AND %s', [$start, $end])
            ->whereNotIn('status', ['cancelled', 'rejected']);
        $this->applyAppointmentFilters($validAppointmentsQuery, $filters);
        $validAppointmentRows = $validAppointmentsQuery->get()->all();
        $validAppointmentIds = array_map(fn($row) => (int) $row->id, $validAppointmentRows);

        if (empty($validAppointmentIds)) {
            return 0.0;
        }

        $query = OrderModel::query()
            ->selectRaw('SUM(total_amount) as total')
            ->whereRaw(
                'JSON_OVERLAPS(booking_ids, %s)',
                [json_encode(array_values($validAppointmentIds))]
            );

        $result = $query->get()->first();
        return (float) ($result->total ?? 0.0);
    }

    /**
     * Return total, new and returning customer counts for the period.
     *
     * A customer is "new" if their record was created within the range;
     * "returning" if created before the range but had a booking in the range.
     *
     * @param string $start   Y-m-d
     * @param string $end     Y-m-d
     * @param array  $filters Optional agent_id / service_id filter.
     * @return array{total: int, new: int, returning: int}
     */
    private function getCustomerStats(string $start, string $end, array $filters = []): array
    {
        // Distinct customers who had at least one booking in the period
        $qb = AppointmentModel::query()
            ->select(['customer_id'])
            ->whereRaw('DATE(`created_at`) BETWEEN %s AND %s', [$start, $end])
            ->whereNotNull('customer_id');

        $this->applyAppointmentFilters($qb, $filters);

        $rows = $qb->groupBy('customer_id')->get()->all();

        $customerIds = array_filter(
            array_map(fn($row) => (int) $row->customer_id, $rows)
        );

        $total = count($customerIds);

        if ($total === 0) {
            return ['total' => 0, 'new' => 0, 'returning' => 0];
        }

        // "New" customers: account was created within the period
        $newCount = CustomerModel::query()
            ->whereIn('id', array_values($customerIds))
            ->whereRaw('DATE(created_at) BETWEEN %s AND %s', [$start, $end])
            ->count();

        return [
            'total'     => $total,
            'new'       => $newCount,
            'returning' => $total - $newCount,
        ];
    }

    /**
     * Count distinct customers who had at least one booking in the previous period.
     *
     * Scoped by the same agent/service filters as the current period so that
     * the percentage-change calculation remains comparable.
     *
     * @param string $start   Y-m-d
     * @param string $end     Y-m-d
     * @param array  $filters Optional agent_id / service_id filter.
     * @return int
     */
    private function countCustomers(string $start, string $end, array $filters = []): int
    {
        if (!empty($filters)) {
            // When filters are active, count customers via bookings (same scope).
            $qb = AppointmentModel::query()
                ->select(['customer_id'])
                ->whereRaw('DATE(`created_at`) BETWEEN %s AND %s', [$start, $end])
                ->whereNotNull('customer_id');
            $this->applyAppointmentFilters($qb, $filters);
            $rows = $qb->groupBy('customer_id')->get()->all();
            return count($rows);
        }

        return CustomerModel::query()
            ->whereRaw('DATE(created_at) BETWEEN %s AND %s', [$start, $end])
            ->count();
    }

    /**
     * Count appointments with a given status within an optional date range.
     *
     * @param string $status
     * @param string $startDate Y-m-d (optional)
     * @param string $endDate   Y-m-d (optional)
     * @param array  $filters   Optional agent_id / service_id filter.
     * @return int
     */
    private function countByStatus(string $status, string $startDate = '', string $endDate = '', array $filters = []): int
    {
        $query = AppointmentModel::query()->where('status', '=', $status);

        // Filter by date range if provided
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        $this->applyAppointmentFilters($query, $filters);
        return $query->count();
    }

    /**
     * Compute percentage change from previous to current value.
     *
     * Returns 0 when there is no previous value to compare against.
     *
     * @param float|int $previous
     * @param float|int $current
     * @return float
     */
    private function percentChange($previous, $current): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
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
