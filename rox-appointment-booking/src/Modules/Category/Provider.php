<?php

namespace RoxAppointmentBooking\Modules\Category;

use RoxAppointmentBooking\Supports\Abstracts\AbstractLoader;

/**
 * Class Provider
 * 
 * @package RoxAppointmentBooking\Modules\Category
 * @description Registers the category module.
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
