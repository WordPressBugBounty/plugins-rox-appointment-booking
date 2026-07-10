<?php 

namespace RoxAppointmentBooking\Modules\Settings;

use RoxAppointmentBooking\Supports\Abstracts\AbstractLoader;

/**
 * Class Provider
 *
 * @package RoxAppointmentBooking\Modules\Settings
 * @description Registers the settings module.
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
            plugin_dir_path(__FILE__) . 'REST/Menueapi',
        ]);
    }
}

