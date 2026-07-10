<?php

namespace RoxAppointmentBooking\Modules\Payment;
use RoxAppointmentBooking\Supports\Abstracts\AbstractLoader;

defined('ABSPATH') || exit;

/**
 * Class Provider
 *
 * @package RoxAppointmentBooking\Modules\Payment
 * @description Registers the payment module.
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
            plugin_dir_path(__FILE__) . 'Data',
            plugin_dir_path(__FILE__) . 'Services',
            plugin_dir_path(__FILE__) . 'REST',
        ]);
    }
}

