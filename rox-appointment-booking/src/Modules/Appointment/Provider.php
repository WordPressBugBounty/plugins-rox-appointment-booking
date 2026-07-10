<?php

namespace RoxAppointmentBooking\Modules\Appointment;

use RoxAppointmentBooking\Supports\Abstracts\AbstractLoader;

/**
 * Class Provider
 * 
 * @package RoxAppointmentBooking\Modules\Appointment
 * @description Registers the appointment module.
 */
class Provider extends AbstractLoader
{
    /**
     * Provider constructor.
    *
    * Loads module service and REST classes.
    *
    * @return void
     */
    public function __construct()
    {
        $this->classLoader([
            plugin_dir_path(__FILE__) . 'Services',
            plugin_dir_path(__FILE__) . 'REST',
        ]);
    }
}
