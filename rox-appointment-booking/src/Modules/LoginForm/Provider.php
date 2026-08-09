<?php

namespace RoxAppointmentBooking\Modules\LoginForm;

use RoxAppointmentBooking\Supports\Abstracts\AbstractLoader;

/**
 * Login form module provider.
 *
 * Owns the standalone `[rox_appointment_login]` shortcode and the shared login
 * form config builder (`Services\LoginFormConfig`) consumed by all three
 * surfaces (shortcode, Gutenberg block, Elementor widget). The module imports
 * nothing from `BookingService/`; it is fully self-contained.
 *
 * @package RoxAppointmentBooking
 * @subpackage Modules\LoginForm
 * @since 1.0.0
 */
class Provider extends AbstractLoader
{
    /**
     * Provider constructor.
     *
     * Loads the module's service classes (shortcode + config builder) and the
     * role-agnostic login REST endpoint.
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
