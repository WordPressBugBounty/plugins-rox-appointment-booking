<?php

namespace RoxAppointmentBooking\Modules\Dashboard\REST;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Notification\Data\NotificationModel;

/**
 * Class GetDashboardRecentActivity
 *
 * Returns the 5 most recent activity items sourced from the notifications table.
 *
 * @package RoxAppointmentBooking\Modules\Dashboard\REST
 */
class GetDashboardRecentActivity extends AbstractREST
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
    public static string $route = '/dashboard/recent-activity';

    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/dashboard/recent-activity';

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
            $notifications = NotificationModel::query()
                ->orderBy('notification_time', 'desc')
                ->limit(5)
                ->get();

            $items = [];
            foreach ($notifications->all() as $notification) {
                $items[] = $this->formatActivity($notification);
            }

            return rox_appointment_booking_rest_response(
                data: $items,
                status: 200,
                message: esc_html__('Recent activity retrieved successfully.', 'rox-appointment-booking')
            );
        } catch (\Exception $e) {
            return new WP_Error(
                'dashboard_recent_activity_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Format a single notification into an activity item.
     *
     * @param NotificationModel $notification
     * @return array
     */
    private function formatActivity(NotificationModel $notification): array
    {
        $actionLink = $notification->action_link;
        if (is_string($actionLink)) {
            $actionLink = json_decode($actionLink, true) ?? [];
        }

        return [
            'id'          => (int) $notification->id,
            'title'       => $notification->title ?? '',
            'description' => $notification->description ?? '',
            'type'        => $notification->type ?? '',
            'time'        => $notification->notification_time ?? '',
            'time_ago'    => $notification->getTimeAgo(),
            'action_link' => $actionLink,
        ];
    }
}
