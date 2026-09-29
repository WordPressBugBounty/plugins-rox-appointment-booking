<?php

namespace RoxAppointmentBooking\Modules\Compatibility;

use RoxAppointmentBooking\Supports\Abstracts\AbstractLoader;

/**
 * Compatibility module provider.
 *
 * Holds the fixes that keep the plugin working alongside third-party
 * plugins (cache plugins, CDNs and the like).
 *
 * @package RoxAppointmentBooking
 * @subpackage Modules\Compatibility
 * @since 1.0.0
 */
class Provider extends AbstractLoader
{
    /**
     * Provider constructor.
     *
     * Loads the module's service classes.
     *
     * @return void
     */
    public function __construct()
    {
        $this->classLoader([
            plugin_dir_path(__FILE__) . 'Services',
        ]);
    }
}
