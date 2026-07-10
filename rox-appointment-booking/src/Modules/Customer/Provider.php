<?php

namespace RoxAppointmentBooking\Modules\Customer;

use RoxAppointmentBooking\Supports\Abstracts\AbstractLoader;

/**
 * Class Provider
 * 
 * @package RoxAppointmentBooking\Modules\Customer
 * @description Registers the customer module.
 */
class Provider extends AbstractLoader
{
    /**
     * Provider constructor.
     *
     * Loads customer services and REST endpoint classes.
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
