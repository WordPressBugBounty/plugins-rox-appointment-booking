<?php

/**
 * Abstract Loader Class  Booking Engine
 *
 * This class provides base functionality for loading and initializing
 * classes dynamically based on directory scanning.
 *
 * @package RoxAppointmentBooking
 * @subpackage Supports\Abstracts
 * @since 1.0.0
 */

namespace RoxAppointmentBooking\Supports\Abstracts;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

class AbstractLoader
{
    /**
     * Loads classes from directories and instantiates loadable ones.
     *
     * @param string[] $dirs
     * @return void
     */
    function classLoader($dirs)
    {
        $src_path = \ROX_APPOINTMENT_BOOKING_SRC_PATH;

        foreach ($dirs as $dir) {
            foreach (glob($dir . '/*.php') as $filename) {
                $parts = explode($src_path, $filename);
                $relative_path = array_pop($parts);

                $classname = str_replace(
                    '.php',
                    '',
                    str_replace(
                        '/',
                        '\\',
                        $relative_path
                    )
                );

                $classname = '\\RoxAppointmentBooking\\' . ltrim($classname, '\\');

                /** @var class-string $classname */
                if (class_exists($classname) && isset($classname::$loadable) && $classname::$loadable === true) {
                    new $classname;
                }
            }
        }
    }
}
