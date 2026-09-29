<?php

namespace RoxAppointmentBooking\Modules\Mcp\Services;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Mcp\McpBootstrap;

/**
 * Class McpSettings
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Services
 * @description Reads and writes the MCP settings option.
 */
class McpSettings
{
    /**
     * Option name holding the MCP toggles.
     */
    public const OPTION = 'rox_appointment_booking_mcp_settings';

    /**
     * Stored toggles and their defaults; everything is off until an admin opts in.
     */
    public const DEFAULTS = [
        'mcp_abilities_enable'        => false,
        'mcp_abilities_edit_enable'   => false,
        'mcp_abilities_delete_enable' => false,
        'mcp_server_enable'           => false,
    ];

    /**
     * Route of the dedicated MCP server, relative to the plugin REST namespace.
     */
    public const SERVER_ROUTE = 'mcp';

    /**
     * Stored toggles merged over the defaults, cast to booleans.
     *
     * @return array<string, bool>
     */
    public static function all(): array
    {
        $stored   = rox_appointment_booking_mcp_settings();
        $settings = [];

        foreach (self::DEFAULTS as $key => $default) {
            $settings[$key] = array_key_exists($key, $stored)
                ? rest_sanitize_boolean($stored[$key])
                : $default;
        }

        return $settings;
    }

    /**
     * Whether a toggle is effectively on; dependents are off while the master is off.
     *
     * @param string $key
     * @return bool
     */
    public static function isOn(string $key): bool
    {
        $settings = self::all();

        if ($key !== 'mcp_abilities_enable' && empty($settings['mcp_abilities_enable'])) {
            return false;
        }

        return !empty($settings[$key]);
    }

    /**
     * Save the whitelisted toggles and return what was stored.
     *
     * @param array $params
     * @return array<string, bool>
     */
    public static function save(array $params): array
    {
        $settings = self::all();

        foreach (array_keys(self::DEFAULTS) as $key) {
            if (array_key_exists($key, $params)) {
                $settings[$key] = rest_sanitize_boolean($params[$key]);
            }
        }

        if (empty($settings['mcp_abilities_enable'])) {
            $settings['mcp_abilities_edit_enable']   = false;
            $settings['mcp_abilities_delete_enable'] = false;
            $settings['mcp_server_enable']           = false;
        }

        update_option(self::OPTION, $settings);

        return $settings;
    }

    /**
     * Public URL of the dedicated MCP server.
     *
     * @return string
     */
    public static function endpoint(): string
    {
        return rest_url(ROX_APPOINTMENT_BOOKING_TEXT_DOMAIN . '/v1/' . self::SERVER_ROUTE);
    }

    /**
     * Number of tools per gating level, counted from the ability list rather than the registry.
     *
     * @return array{read: int, edit: int, delete: int}
     */
    public static function toolCounts(): array
    {
        $counts    = ['read' => 0, 'edit' => 0, 'delete' => 0];
        foreach (McpBootstrap::abilities() as $ability) {
            $level = $ability->level();
            if (isset($counts[$level])) {
                $counts[$level]++;
            }
        }

        return $counts;
    }

    /**
     * Stored toggles plus the derived, never-stored fields the MCP tab needs.
     *
     * @return array<string, mixed>
     */
    public static function forResponse(): array
    {
        $user = wp_get_current_user();

        return array_merge(self::all(), [
            'endpoint'                => self::endpoint(),
            'username'                => $user ? $user->user_login : '',
            'app_passwords_url'       => admin_url('profile.php#application-passwords-section'),
            'app_passwords_available' => function_exists('wp_is_application_passwords_available')
                && wp_is_application_passwords_available(),
            'tool_counts'             => self::toolCounts(),
        ]);
    }
}
