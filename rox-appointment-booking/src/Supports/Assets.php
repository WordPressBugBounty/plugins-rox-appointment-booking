<?php

/**
 * Configuration Manager  Booking Engine
 *
 * This class manages configuration settings for theBooking Engine plugin
 * using a singleton pattern to ensure only one instance exists.
 *
 * @package RoxAppointmentBooking
 * @subpackage Supports
 * @since 1.0.0
 */

namespace RoxAppointmentBooking\Supports;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

// warper for JG\Config\Config
/**
 * Asset enqueue helpers for scripts and styles.
 *
 * @package RoxAppointmentBooking
 * @since 1.0.0
 */
class Assets
{
    protected static $instance = null;

    /**
     * Enqueues a stylesheet.
     *
     * @param string $handle
     * @param string $src
     * @param array $deps
     * @param string|bool|null $ver
     * @param string $media
     * @return void
     */
    public static function enqueueStyle(
        $handle,
        $src = '',
        $deps = array(),
        $ver = false,
        $media = 'all',
    ): void {
        wp_enqueue_style($handle, $src, $deps, $ver, $media);
    }

    /**
     * Enqueues a script.
     *
     * @param string $handle
     * @param string $src
     * @param array $deps
     * @param string|bool|null $ver
     * @param array $args
     * @param string|null $hmrSrc
     * @return void
     */
    public static function enqueueScript(
        $handle,
        $src = '',
        $deps = array(),
        $ver = false,
        $args = array(),
        $hmrSrc = null,
    ): void {
        wp_enqueue_script($handle, $src, $deps, $ver, $args);
    }

    /**
     * Registers the shared webpack `runtime` and `vendors` chunks (plus the
     * shared vendors stylesheet) that every entry bundle depends on.
     *
     * The production build splits the dependencies shared between the admin,
     * onboarding and frontend entries (antd, dayjs, lodash, react-router, etc.)
     * into a single `vendors` chunk and a small `runtime` chunk instead of
     * duplicating them into each entry. Those chunks must therefore be loaded
     * before any entry script, in the order runtime -> vendors -> entry. The
     * entry's external dependencies (react, wp-*) are attached to `vendors`
     * because library code inside the vendors chunk references them at
     * evaluation time, before the entry script runs.
     *
     * Idempotent: safe to call from multiple enqueue sites on the same request.
     *
     * @param string $build_url    Public URL to the build directory (trailing slash).
     * @param string $build_path   Filesystem path to the build directory (trailing slash).
     * @param array  $externals    External deps (react, wp-*) from the entry asset file.
     * @param string $fallback_ver Version used when a chunk asset file is missing.
     * @return array{script:string,style:string} Handles for the entry to depend on.
     */
    public static function enqueueSharedChunks(
        string $build_url,
        string $build_path,
        array $externals = array(),
        string $fallback_ver = '',
    ): array {
        $runtime_handle = 'rox-appointment-booking-runtime';
        $vendors_handle = 'rox-appointment-booking-vendors';

        $runtime_asset = file_exists($build_path . 'runtime.asset.php')
            ? require($build_path . 'runtime.asset.php')
            : array('version' => $fallback_ver);
        $vendors_asset = file_exists($build_path . 'vendors.asset.php')
            ? require($build_path . 'vendors.asset.php')
            : array('version' => $fallback_ver);

        if (!wp_script_is($runtime_handle, 'registered')) {
            wp_register_script(
                $runtime_handle,
                $build_url . 'runtime.js',
                array(),
                $runtime_asset['version'] ?? $fallback_ver,
                true,
            );
        }

        if (!wp_script_is($vendors_handle, 'registered')) {
            wp_register_script(
                $vendors_handle,
                $build_url . 'vendors.js',
                array_merge(array($runtime_handle), $externals),
                $vendors_asset['version'] ?? $fallback_ver,
                true,
            );
        }

        $style_handle = '';
        if (file_exists($build_path . 'vendors.css')) {
            $style_handle = $vendors_handle;
            if (!wp_style_is($vendors_handle, 'registered')) {
                wp_register_style(
                    $vendors_handle,
                    $build_url . 'vendors.css',
                    array(),
                    $vendors_asset['version'] ?? $fallback_ver,
                );
            }
        }

        return array('script' => $vendors_handle, 'style' => $style_handle);
    }
}
