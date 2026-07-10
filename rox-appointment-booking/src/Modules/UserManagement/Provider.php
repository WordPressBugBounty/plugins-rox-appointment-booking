<?php

namespace RoxAppointmentBooking\Modules\UserManagement;

use RoxAppointmentBooking\Supports\Abstracts\AbstractLoader;

use function WordPress\AI\plugin_action_links;

/**
 * Class Provider
 *
 * @package RoxAppointmentBooking\Modules\UserManagement
 * @description Registers the UserManagement module.
 */
class Provider extends AbstractLoader
{
    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $this->classLoader([
            plugin_dir_path(__FILE__) . 'Services',
            plugin_dir_path(__FILE__) . 'REST',
            plugin_dir_path(__FILE__) . 'Util',
        ]);
    }
}
