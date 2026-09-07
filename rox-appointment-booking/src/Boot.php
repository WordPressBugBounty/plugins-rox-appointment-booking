<?php

namespace RoxAppointmentBooking;

use \RoxAppointmentBooking\Modules;

/**
 * Boots the plugin by registering all module providers.
 *
 * @package RoxAppointmentBooking
 * @since 1.0.0
 */
class Boot
{
    /**
     * Registers core services and module providers.
     *
     * @return void
     */
    public function __construct()
    {

	    if (!defined('ROX_APPOINTMENT_BOOKING_SRC_PATH')) {
			define('ROX_APPOINTMENT_BOOKING_SRC_PATH', __DIR__);
		}

        new Modules\Core\Provider();
        // Boots before every content module so translation filters are in place
        // before any REST route is registered or any view is rendered.
        new Modules\Multilingual\Provider();
        new Modules\CustomerPanel\Provider();
        new Modules\Agent\Provider();
        new Modules\Appointment\Provider();
        new Modules\Calendar\Provider();
        new Modules\Customer\Provider();
        new Modules\Category\Provider();
        new Modules\Filter\Provider();
        new Modules\Service\Provider();
        new Modules\Order\Provider();
        new Modules\RelationshipModel\Provider();
        new Modules\Settings\Provider();
        new Modules\UserManagement\Provider();
        new Modules\Payment\Provider();
        new Modules\FrontendBookingPanel\Provider();
        new Modules\Notification\Provider();
        new Modules\Email\Provider();
        new Modules\Dashboard\Provider();
        new Modules\Blocks\Provider();
        new Modules\LoginForm\Provider();

        // Booking panel Elementor widget — only when Elementor (>= 3.5, register() API) is active.
        if (defined('ELEMENTOR_VERSION') && version_compare(ELEMENTOR_VERSION, '3.5.0', '>=')) {
            new Modules\Elementor\Provider();
        }
    }
}
