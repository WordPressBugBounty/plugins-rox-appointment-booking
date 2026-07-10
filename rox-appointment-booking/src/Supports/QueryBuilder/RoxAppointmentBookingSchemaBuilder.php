<?php

namespace RoxAppointmentBooking\Supports\QueryBuilder;

defined('ABSPATH') || exit;

class RoxAppointmentBookingSchemaBuilder
{
    /**
     * Get column names for a table.
     *
     * @param string $table Table name.
     * @return array
     */
    public function getColumnListing(string $table): array
    {
        global $wpdb;

        $table = $this->sanitizeTable($table);
        if ($table === '') {
            return [];
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT COLUMN_NAME AS Field
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s
                ORDER BY ORDINAL_POSITION ASC',
                DB_NAME,
                $table
            )
        );
        if (!is_array($results)) {
            return [];
        }

        $columns = [];
        foreach ($results as $column) {
            if (isset($column->Field)) {
                $columns[] = (string) $column->Field;
            }
        }

        return $columns;
    }

    /**
     * Check if a table contains a specific column.
     *
     * @param string $table Table name.
     * @param string $column Column name.
     * @return bool
     */
    public function hasColumn(string $table, string $column): bool
    {
        $columns = $this->getColumnListing($table);
        return in_array($column, $columns, true);
    }

    /**
     * Sanitize a table name before use.
     *
     * @param string $table Table name.
     * @return string
     */
    protected function sanitizeTable(string $table): string
    {
        return preg_replace('/[^A-Za-z0-9_]/', '', $table);
    }
}
