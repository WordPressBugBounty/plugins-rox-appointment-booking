<?php

namespace RoxAppointmentBooking\Modules\FrontendBookingPanel\REST;

use WP_REST_Request;
use RoxAppointmentBooking\Modules\Appointment\REST\GetAppointmentSchedule as AppointmentGetAppointmentSchedule;

/**
 * Class GetAppointmentSchedule
 *
 * @package RoxAppointmentBooking\Modules\FrontendBookingPanel\REST
 * @description Public version of the appointment schedule endpoint for the frontend booking panel.
 */
class GetAppointmentSchedule extends AppointmentGetAppointmentSchedule
{
    /**
     * Whether the endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * REST route for the public appointment schedule data.
     *
     * @var string
     */
    public static string $route = '/public/appointment-schedule';

    /**
     * Check whether the current user can access this endpoint.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return bool
     */
    public function permissionCheck(WP_REST_Request $request): bool
    {
        return true;
    }
}
