<?php

namespace RoxAppointmentBooking\Modules\CustomerLogin;

use RoxAppointmentBooking\Supports\Abstracts\AbstractLoader;

/**
 * Customer Login module provider.
 *
 * Owns the standalone `[rox_appointment_login]` shortcode and the shared login
 * form config builder (`Services\LoginFormConfig`) consumed by all three
 * surfaces (shortcode, Gutenberg block, Elementor widget). The module imports
 * nothing from `BookingService/`; it is fully self-contained.
 *
 * @package RoxAppointmentBooking
 * @subpackage Modules\CustomerLogin
 * @since 1.0.0
 */
class Provider extends AbstractLoader
{
    /**
     * Provider constructor.
     *
     * Loads the module's service classes (shortcode + config builder).
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
