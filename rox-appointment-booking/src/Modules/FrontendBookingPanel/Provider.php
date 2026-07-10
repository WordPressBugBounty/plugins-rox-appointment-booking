<?php 

namespace RoxAppointmentBooking\Modules\FrontendBookingPanel;

use RoxAppointmentBooking\Supports\Abstracts\AbstractLoader;

/**
 * Class Provider
 *
 * @package RoxAppointmentBooking\Modules\FrontendBookingPanel
 * @description Registers the frontend booking panel module.
 */

class Provider extends AbstractLoader
{

    /**
     * Provider constructor.
     */
    public function __construct()
    {
        $this->classLoader([
            plugin_dir_path(__FILE__) . 'REST',
            plugin_dir_path(__FILE__) . 'Services',
        ]);
    }
}

