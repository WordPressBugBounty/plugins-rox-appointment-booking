<?php

namespace RoxAppointmentBooking\Modules\Multilingual;

use RoxAppointmentBooking\Supports\Abstracts\AbstractLoader;
use RoxAppointmentBooking\Modules\Multilingual\Services\StringBackfillService;

/**
 * Class Provider
 *
 * @package RoxAppointmentBooking\Modules\Multilingual
 * @description Registers the multilingual compatibility layer. Only
 *              MultilingualService is $loadable here; TranslatableRegistry is a
 *              static helper and the drivers are constructed by the service.
 *
 *              Boots early so the translation filters are attached before any
 *              module registers a REST route or renders a view.
 */
class Provider extends AbstractLoader
{
    /**
     * Provider constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $this->classLoader([
            plugin_dir_path(__FILE__) . 'Services',
            plugin_dir_path(__FILE__) . 'REST',
        ]);

        // Runs on the first admin page load after a driver becomes available.
        // Not an activation worker: those fire during the free plugin's own
        // plugins_loaded, before Pro has declared its entities, so a worker
        // would silently skip every Pro entity.
        add_action('admin_init', [StringBackfillService::class, 'maybeRunOnce']);
    }
}
