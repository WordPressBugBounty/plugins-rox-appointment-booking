<?php

namespace RoxAppointmentBooking\Modules\Blocks;

use RoxAppointmentBooking\Supports\Abstracts\AbstractLoader;

/**
 * Blocks module provider.
 *
 * Houses the PHP source for all Gutenberg blocks shipped by the plugin
 * (registration, render callbacks, asset wiring). Add new block classes under
 * `Services/` and they are auto-loaded here.
 *
 * @package RoxAppointmentBooking
 * @subpackage Modules\Blocks
 * @since 1.0.0
 */
class Provider extends AbstractLoader
{
    /**
     * Provider constructor.
     *
     * Loads the block registration classes.
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
