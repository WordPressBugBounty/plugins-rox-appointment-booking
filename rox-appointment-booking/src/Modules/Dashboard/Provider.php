<?php

namespace RoxAppointmentBooking\Modules\Dashboard;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Supports\Abstracts\AbstractLoader;

/**
 * Class Provider
 *
 * @package RoxAppointmentBooking\Modules\Dashboard
 * @description Registers the dashboard module REST endpoints.
 */
class Provider extends AbstractLoader
{
    /**
     * Provider constructor.
     *
     * Loads module REST classes.
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
