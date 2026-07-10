<?php

namespace RoxAppointmentBooking\Supports\Traits;

/**
 * Singleton helper trait.
 *
 * @package RoxAppointmentBooking
 * @since 1.0.0
 */
trait Singleton
{
    /**
     * Holds the singleton instance.
     *
     * @var static|null
     */
    protected static $instance = null;
    /**
     * Returns the singleton instance.
     *
     * @return static
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new static();
        }
        return self::$instance;
    }
}
