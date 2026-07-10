<?php

namespace RoxAppointmentBooking\Supports\Traits;

use WP_REST_Request;

/**
 * Filter helpers for REST query handling.
 *
 * @package RoxAppointmentBooking
 * @since 1.0.0
 */
trait RoxAppointmentBookingFilter
{
    /**
     * Sanitize user input to prevent SQL injection
     */
	/**
	 * Sanitizes scalar input before use in queries.
	 *
	 * @param mixed $input
	 * @return mixed
	 */
    protected function sanitizeInput($input)
    {
        if (is_string($input)) {
            return htmlspecialchars(wp_strip_all_tags(trim($input)), ENT_QUOTES, 'UTF-8');
        }
        return $input;
    }

    /**
     * Validate column name against allowed columns
     */
	/**
	 * Checks whether a column exists for the model behind the query.
	 *
	 * @param object $query
	 * @param string $columnName
	 * @return bool
	 */
    protected function isValidColumn($query, $columnName)
    {
        try {
            $model = $query->getModel();
            $allowedColumns = $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable());
            return in_array($columnName, $allowedColumns);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Sanitize column name to prevent SQL injection
     */
    /**
     * Strips unsafe characters from column names.
     *
     * @param string $columnName
     * @return string
     */
    protected function sanitizeColumnName($columnName)
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);
    }

    /**
     * Apply common filters to query from request parameters
     *
     * @param WP_REST_Request $request
     * @param object $query
     * @return object
     */
    protected function applyFilters(WP_REST_Request $request, $query)
    {
        $filterConfig = [];

        // Get table structure filters if available
        $structureClass = str_replace('Get', '', get_class($this)) . 'Table';

        if (class_exists($structureClass)) {
            try {
                $structureInstance = new $structureClass();
                $mockRequest = new WP_REST_Request('GET');
                $response = $structureInstance->handleRequest($mockRequest);
                $structureData = $response->get_data();

                if (isset($structureData['data']['fields']['filters'])) {
                    $filterConfig = $structureData['data']['fields']['filters'];
                    unset($filterConfig['search']);
                }
            } catch (\Exception $e) {
                // Silently fail if structure class not found
            }
        }

        // Merge filter metadata from request if available
        $filtersMeta = $request->get_param('filtersMeta');
        if ($filtersMeta && is_array($filtersMeta)) {
            foreach ($filtersMeta as $key => $meta) {
                if (!isset($filterConfig[$key])) {
                    $filterConfig[$key] = $meta;
                } else {
                    $filterConfig[$key] = array_merge($filterConfig[$key], $meta);
                }
            }
        }

        // Auto-detect filters from request params if not in filterConfig
        $allParams = $request->get_params();
        $excludeParams = ['search', 'page', 'per_page', 'mode', 'with_avatar', 'filtersMeta', '_locale'];
        foreach ($allParams as $key => $value) {
            if (!in_array($key, $excludeParams) && !isset($filterConfig[$key]) && $value !== null && $value !== '') {
                // Auto-detect filter type based on value
                if (is_string($value) && strpos($value, '[') === 0) {
                    $filterConfig[$key] = ['type' => 'dateRange'];
                } else {
                    $filterConfig[$key] = ['type' => 'select'];
                }
            }
        }

        // Handle search across multiple fields
        $search = $request->get_param('search') ?? '';

        if (!empty($search) && method_exists($this, 'getSearchableFields')) {
            $searchFields = $this->getSearchableFields();
            $sanitizedSearch = $this->sanitizeInput($search);
            
            $query->where(function ($q) use ($sanitizedSearch, $searchFields) {
                foreach ($searchFields as $field) {
                    $field = $this->sanitizeColumnName($field);
                    if ($this->isValidColumn($q, $field)) {
                        $q->orWhere($field, 'LIKE', '%' . $sanitizedSearch . '%');
                    }
                }
            });
        }

        // Handle specific filters from filter config
        foreach ($filterConfig as $filterKey => $filterDef) {
            $filterValue = $request->get_param($filterKey);

            // Check if filter value exists and is not empty
            if ($filterValue === null || $filterValue === '' || !isset($filterDef['type'])) {
                continue;
            }

            // Use targetField if provided, otherwise use dataIndex or convert filterKey
            $dataIndex = $filterDef['targetField'] ?? $filterDef['dataIndex'] ?? $this->camelToSnake($filterKey);
            $dataIndex = $this->sanitizeColumnName($dataIndex);

            // Validate column exists in table to prevent SQL errors
            if (!$this->isValidColumn($query, $dataIndex)) {
                continue;
            }

            switch ($filterDef['type']) {
                case 'input':
                    $sanitizedValue = $this->sanitizeInput($filterValue);
                    $query->where($dataIndex, 'LIKE', '%' . $sanitizedValue . '%');
                    break;

                case 'select':
                    if (isset($filterDef['customFilter']) && method_exists($this, $filterDef['customFilter'])) {
                        // Handle filterByRelation with parameters
                        if ($filterDef['customFilter'] === 'filterByRelation' && isset($filterDef['relationTable'])) {
                            $query = $this->filterByRelation(
                                $query,
                                $filterValue,
                                $filterDef['relationTable'],
                                $filterDef['foreignKey'],
                                $filterDef['filterField']
                            );
                        } else {
                            $query = $this->{$filterDef['customFilter']}($query, $filterValue, $filterKey);
                        }
                    } elseif (isset($filterDef['dateRangeFilter']) && $filterDef['dateRangeFilter']) {
                        // Handle date range filter with predefined labels
                        $dateRange = $this->getDateRangeFromLabel($filterValue);
                        if ($dateRange) {
                            $startDate = $dateRange['start'] . ' 00:00:00';
                            $endDate = $dateRange['end'] . ' 23:59:59';
                            $query->whereBetween($dataIndex, [$startDate, $endDate]);
                        }
                    } else {
                        // If filterValue is numeric, use it directly as ID
                        if (is_numeric($filterValue)) {
                            $query->where($dataIndex, '=', intval($filterValue));
                        } elseif (str_ends_with($dataIndex, '_id')) {
                            // Handle case where filtering by ID field but value is not numeric
                            $lookupTable = str_replace('_id', '', $dataIndex);
                            $modelClass = 'RoxAppointmentBooking\\Modules\\' . ucfirst($lookupTable) . '\\Data\\' . ucfirst($lookupTable) . 'Model';
                            if (class_exists($modelClass)) {
                                $sanitizedValue = $this->sanitizeInput($filterValue);
                                // Try to find by title field first
                                $record = $modelClass::where('title', $sanitizedValue)->first();

                                // If not found by title, try first_name and last_name
                                if (!$record) {
                                    $nameParts = explode(' ', $sanitizedValue, 2);
                                    $firstName = $this->sanitizeInput($nameParts[0] ?? '');
                                    $lastName = $this->sanitizeInput($nameParts[1] ?? '');

                                    $record = $modelClass::where('first_name', $firstName)
                                        ->where('last_name', $lastName)
                                        ->first();
                                }

                                if (!$record) {
                                    return $query->where('id', 0);
                                }

                                $filterValue = $record->id;
                            }
                            $query->where($dataIndex, '=', intval($filterValue));
                        } else {
                            $sanitizedValue = $this->sanitizeInput($filterValue);
                            $query->where($dataIndex, '=', $sanitizedValue);
                        }
                    }
                    break;

                case 'dateRange':
                    if (is_string($filterValue)) {
                        $decodedValue = urldecode(html_entity_decode($filterValue, ENT_QUOTES | ENT_HTML5));

                        if (strpos($decodedValue, ',') !== false && strpos($decodedValue, '[') === false) {
                            $dates = array_map('trim', explode(',', $decodedValue));
                        } else {
                            $dates = json_decode($decodedValue, true);
                        }

                        if (is_array($dates) && count($dates) >= 2) {
                            $start = substr($dates[0], 0, 10) . ' 00:00:00';
                            $end   = substr($dates[1], 0, 10) . ' 23:59:59';
                            $query->whereBetween($dataIndex, [$start, $end]);
                        }
                    }
                    break;
            }
        }

        return $query;
    }

    /**
     * Validate date format
     */
	/**
	 * Validates a date string against supported formats.
	 *
	 * @param string $date
	 * @return string|false
	 */
    protected function validateDate($date)
    {
        $formats = ['Y-m-d', 'Y-m-d H:i:s'];
        foreach ($formats as $format) {
            $d = \DateTime::createFromFormat($format, $date);
            if ($d && $d->format($format) === $date) {
                return $date;
            }
        }
        return false;
    }

    /**
     * Filters a query by created_at using a date label.
     *
     * @param object $query
     * @param mixed $filterValue
     * @param string $filterKey
     * @return object
     */
    protected function filterByCreatedAt($query, $filterValue, $filterKey)
    {
        $sanitizedValue = $this->sanitizeInput($filterValue);
        $dateRange = $this->getDateRangeFromLabel($sanitizedValue);

        if ($dateRange) {
            $startDate = $this->validateDate($dateRange['start']);
            $endDate = $this->validateDate($dateRange['end']);
            if ($startDate && $endDate) {
                $query->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            }
        }

        return $query;
    }

    /**
     * Check if column exists in the query's table
     *
     * @param object $query
     * @param string $columnName
     * @return bool
     */
    protected function columnExists($query, $columnName)
    {
        try {
            $model = $query->getModel();
            return $model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), $columnName);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Generic filter by relation table
     * 
     * @param object $query
     * @param mixed $filterValue
     * @param string $relationTable Relation table name (without prefix)
     * @param string $foreignKey Foreign key in relation table (e.g., 'agent_id')
     * @param string $filterField Field to filter in relation table (e.g., 'service_id')
     * @return object
     */
    protected function filterByRelation($query, $filterValue, $relationTable, $foreignKey, $filterField)
    {
        global $wpdb;
        $relationTable = $this->sanitizeColumnName($relationTable);
        $foreignKey = $this->sanitizeColumnName($foreignKey);
        $filterField = $this->sanitizeColumnName($filterField);
        
        $relationTableName = $wpdb->prefix . ROX_APPOINTMENT_BOOKING_PREFIX . '_' . $relationTable;
        $mainTableName = $query->getModel()->getTable();

        // Convert title to ID using ORM if filtering by ID field and value is not numeric
        if (str_ends_with($filterField, '_id') && !is_numeric($filterValue)) {
            $lookupTable = str_replace('_id', '', $filterField);
            $modelClass = 'RoxAppointmentBooking\\Modules\\' . ucfirst($lookupTable) . '\\Data\\' . ucfirst($lookupTable) . 'Model';

            if (class_exists($modelClass)) {
                $record = $modelClass::where('title', $filterValue)->first();
                if (!$record) {
                    return $query->where('id', 0);
                }
                $filterValue = $record->id;
            }
        }

        $query->whereExists(function ($q) use ($relationTableName, $mainTableName, $foreignKey, $filterField, $filterValue) {
            $q->selectRaw('1')
                ->from($relationTableName)
                ->whereColumn($relationTableName . '.' . $foreignKey, '=', $mainTableName . '.id')
                ->where($relationTableName . '.' . $filterField, '=', $filterValue);
        });

        return $query;
    }

    /**
     * Convert date range label to actual date range
     * 
     * @param string $label
     * @return array|null
     */
    protected function getDateRangeFromLabel(string $label): ?array
    {
        $today = gmdate('Y-m-d');

        switch ($label) {
            case 'today':
                return ['start' => $today, 'end' => $today];

            case 'yesterday':
                $yesterday = gmdate('Y-m-d', strtotime('-1 day'));
                return ['start' => $yesterday, 'end' => $yesterday];

            case 'this_week':
                return [
                    'start' => gmdate('Y-m-d', strtotime('monday this week')),
                    'end' => gmdate('Y-m-d', strtotime('sunday this week'))
                ];

            case 'this_month':
                return [
                    'start' => gmdate('Y-m-01'),
                    'end' => gmdate('Y-m-t')
                ];

            case 'last_month':
                return [
                    'start' => gmdate('Y-m-01', strtotime('first day of last month')),
                    'end' => gmdate('Y-m-t', strtotime('last day of last month'))
                ];

            case 'this_year':
                return [
                    'start' => gmdate('Y-01-01'),
                    'end' => gmdate('Y-12-31')
                ];

            case 'last_year':
                $lastYear = gmdate('Y', strtotime('-1 year'));
                return [
                    'start' => $lastYear . '-01-01',
                    'end' => $lastYear . '-12-31'
                ];

            default:
                return null;
        }
    }

    /**
     * Convert camelCase to snake_case
     * 
     * @param string $input
     * @return string
     */
    private function camelToSnake(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    /**
     * Get pagination parameters from request
     * 
     * @param WP_REST_Request $request
     * @return array ['page' => int, 'per_page' => int]
     */
    protected function getPaginationParams(WP_REST_Request $request): array
    {
        return [
            'page' => $request->get_param('page') ?? 1,
            'per_page' => $request->get_param('per_page') ?? 10,
        ];
    }

    /**
     * Build pagination metadata
     * 
     * @param int $total Total records
     * @param int $page Current page
     * @param int $per_page Records per page
     * @return array Pagination metadata
     */
    protected function buildPaginationMeta(int $total, int $page, int $per_page): array
    {
        return [
            'total' => $total,
            'page' => (int)$page,
            'per_page' => (int)$per_page,
            'total_pages' => ceil($total / $per_page)
        ];
    }
}
