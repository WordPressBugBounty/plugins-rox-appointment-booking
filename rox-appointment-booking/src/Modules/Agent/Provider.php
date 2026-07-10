<?php

namespace RoxAppointmentBooking\Modules\Agent;

use RoxAppointmentBooking\Supports\Abstracts\AbstractLoader;

/**
 * Class Provider
 * 
 * @package RoxAppointmentBooking\Modules\Agent
 * @description Registers the agent module.
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