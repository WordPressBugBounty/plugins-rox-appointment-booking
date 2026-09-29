<?php

namespace RoxAppointmentBooking\Modules\Mcp;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Supports\Abstracts\AbstractLoader;

/**
 * Class Provider
 *
 * @package RoxAppointmentBooking\Modules\Mcp
 * @description Registers the MCP module REST endpoints, abilities and server.
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
            plugin_dir_path(__FILE__) . 'REST',
        ]);

        new McpBootstrap();
    }
}
