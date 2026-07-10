<?php

namespace RoxAppointmentBooking\Modules\Dashboard\REST;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\Service\Data\ServiceModel;
use RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingQueryBuilder;

/**
 * Class GetDashboardTopServices
 *
 * Returns services ranked by total booking count (descending).
 * Includes the service thumbnail for display in the dashboard widget.
 *
 * Query params:
 *   start_date  (Y-m-d)  – start of range (default: no filter)
 *   end_date    (Y-m-d)  – end of range (default: no filter)
 *   limit       (int)    – max services to return (default: 10, max: 50)
 *
 * @package RoxAppointmentBooking\Modules\Dashboard\REST
 */
class GetDashboardTopServices extends AbstractREST
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
    public static string $route = '/dashboard/top-services';

    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/dashboard/top-services';

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
            $startDate = sanitize_text_field($request->get_param('start_date') ?? '');
            $endDate   = sanitize_text_field($request->get_param('end_date') ?? '');
            $limit     = min(50, max(1, (int) ($request->get_param('limit') ?? 10)));

            $bookingTable = (new AppointmentModel())->getTable();

            $query = (new RoxAppointmentBookingQueryBuilder($bookingTable))
                ->select(['service_id'])
                ->selectRaw('COUNT(*) AS booking_count')
                ->whereNotNull('service_id');

            // Apply optional date filters
            if ($startDate && $this->isValidDate($startDate) && $endDate && $this->isValidDate($endDate)) {
                if ($startDate > $endDate) {
                    [$startDate, $endDate] = [$endDate, $startDate];
                }
                $query->whereRaw('DATE(`created_at`) BETWEEN %s AND %s', [$startDate, $endDate]);
            } elseif ($startDate && $this->isValidDate($startDate)) {
                $query->whereRaw('DATE(`created_at`) >= %s', [$startDate]);
            } elseif ($endDate && $this->isValidDate($endDate)) {
                $query->whereRaw('DATE(`created_at`) <= %s', [$endDate]);
            }

            $rows = $query
                ->groupBy('service_id')
                ->orderBy('booking_count', 'desc')
                ->limit($limit)
                ->get()
                ->all();

            if (empty($rows)) {
                return rox_appointment_booking_rest_response(
                    data: ['items' => []],
                    status: 200,
                    message: esc_html__('No service booking data found.', 'rox-appointment-booking')
                );
            }

            // Batch-load service details
            $serviceIds = array_map(fn($r) => (int) $r->service_id, $rows);
            $serviceMap = $this->buildServiceMap($serviceIds);

            // Compute max count for percentage bar calculations
            $maxCount = (int) ($rows[0]->booking_count ?? 1);
            $maxCount = max(1, $maxCount);

            $items = [];
            foreach ($rows as $row) {
                $sid          = (int) $row->service_id;
                $bookingCount = (int) $row->booking_count;
                $serviceInfo  = $serviceMap[$sid] ?? null;

                $items[] = [
                    'service_id'    => $sid,
                    'title'         => $serviceInfo['title'] ?? 'Service #' . $sid,
                    'thumbnail'     => $serviceInfo['thumbnail'] ?? null,
                    'color'         => $serviceInfo['color'] ?? null,
                    'booking_count' => $bookingCount,
                    'percentage'    => round(($bookingCount / $maxCount) * 100, 1),
                ];
            }

            return rox_appointment_booking_rest_response(
                data: ['items' => $items],
                status: 200,
                message: esc_html__('Top services retrieved successfully.', 'rox-appointment-booking')
            );
        } catch (\Exception $e) {
            return new WP_Error(
                'dashboard_top_services_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build a map of service_id => [title, thumbnail, color].
     *
     * @param int[] $ids
     * @return array<int, array{title: string, thumbnail: string|null, color: string|null}>
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
                'thumbnail' => $thumbnailUrl,
                'color'     => $service->color ?? null,
            ];
        }

        return $map;
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
