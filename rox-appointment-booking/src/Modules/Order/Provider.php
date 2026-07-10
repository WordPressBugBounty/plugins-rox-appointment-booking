<?php

namespace RoxAppointmentBooking\Modules\Order;

use RoxAppointmentBooking\Supports\Abstracts\AbstractLoader;

/**
 * Class Provider
 * 
 * @package RoxAppointmentBooking\Modules\Order
 * @description Registers the order module.
 */
class Provider extends AbstractLoader
{
    
    /**
     * Provider constructor.
     */
    public function __construct()
    {
        $this->classLoader([
            plugin_dir_path(__FILE__) . 'Services',
            plugin_dir_path(__FILE__) . 'REST',
        ]);
    }
}