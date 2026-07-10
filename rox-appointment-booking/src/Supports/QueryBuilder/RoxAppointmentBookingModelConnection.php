<?php

namespace RoxAppointmentBooking\Supports\QueryBuilder;

defined('ABSPATH') || exit;

/**
 * Model connection wrapper for schema access.
 *
 * @package RoxAppointmentBooking
 * @since 1.0.0
 */
class RoxAppointmentBookingModelConnection
{
    /**
     * Get current WordPress table prefix.
     *
     * @return string
     */
    public function getTablePrefix(): string
    {
        global $wpdb;

        return $wpdb->prefix;
    }

    /**
     * Get schema builder instance.
     *
     * @return RoxAppointmentBookingSchemaBuilder
     */
    public function getSchemaBuilder(): RoxAppointmentBookingSchemaBuilder
    {
        return new RoxAppointmentBookingSchemaBuilder();
    }
}
