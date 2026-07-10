<?php

namespace RoxAppointmentBooking\Modules\RelationshipModel;
use RoxAppointmentBooking\Supports\Abstracts\AbstractLoader;

defined('ABSPATH') || exit;

/**
 * Class Provider
 *
 * @package RoxAppointmentBooking\Modules\RelationshipModel
 * @description Registers the service-location relation module.
 */
class Provider extends AbstractLoader
{

    /**
     * Provider constructor.
     */
    public function __construct()
    {
        
        $this->classLoader([

            plugin_dir_path(__FILE__) . 'Data',

        ]);
    }
}

