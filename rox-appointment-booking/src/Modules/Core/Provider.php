<?php

namespace RoxAppointmentBooking\Modules\Core;

use RoxAppointmentBooking\Supports\Abstracts\AbstractLoader;

class Provider extends AbstractLoader
{
    /**
     * Provider constructor.
     *
     * Loads module service and REST structure classes.
     *
     * @return void
     */
    public function __construct()
    {
        $this->classLoader([
            plugin_dir_path(__FILE__) . 'Services',
            plugin_dir_path(__FILE__) . 'REST/Structure',
            plugin_dir_path(__FILE__) . 'REST/Onboarding',
        ]);
    }
}
