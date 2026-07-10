<?php

namespace RoxAppointmentBooking\Modules\Filter;

use RoxAppointmentBooking\Supports\Abstracts\AbstractLoader;

/**
 * Class Provider
 *
 * @package RoxAppointmentBooking\Modules\Filter
 * @description Registers the filter module.
 */
class Provider extends AbstractLoader
{
    /**
     * Provider constructor.
     *
     * Loads filter REST endpoint classes.
     *
     * @return void
     */
    public function __construct()
    {
        $this->classLoader([
            plugin_dir_path(__FILE__) . 'REST',
        ]);
    }
}
