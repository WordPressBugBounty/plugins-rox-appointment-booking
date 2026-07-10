<?php

namespace RoxAppointmentBooking\Supports\QueryBuilder;

defined('ABSPATH') || exit;

/**
 * Query builder backed by $wpdb with wp-orm-like fluent API.
 */
class RoxAppointmentBookingQueryBuilder
{
    protected string $table = '';
    protected ?string $modelClass = null;
    protected array $selects = ['*'];
    protected array $selectRaw = [];
    protected array $joins = [];
    protected array $wheres = [];
    protected array $orders = [];
    protected array $eagerLoads = [];
    protected array $groups = [];
    protected ?int $limitValue = null;
    protected ?int $offsetValue = null;

    protected ?string $pivotTable = null;
    protected ?string $pivotParentKey = null;
    protected ?string $pivotRelatedKey = null;
    protected $pivotParentId = null;
    protected array $pivotColumns = [];

    /**
     * Initialize query builder state.
     *
     * @param string|null $table Table name.
     * @param string|null $modelClass Model class name.
     * @return void
     */
    public function __construct(?string $table = null, ?string $modelClass = null)
    {
        if ($table !== null) {
            $this->table = $table;
        }

        $this->modelClass = $modelClass;
    }

    /**
     * Set model class used for hydration and scopes.
     *
     * @param string $modelClass Model class name.
     * @return self
     */
    public function setModel(string $modelClass): self
    {
        $this->modelClass = $modelClass;
        return $this;
    }

    /**
     * Get a model instance for the configured model class.
     *
     * @return object|null
     */
    public function getModel()
    {
        if ($this->modelClass === null || !class_exists($this->modelClass)) {
            return null;
        }

        return new $this->modelClass();
    }

    /**
     * Set query table name.
     *
     * @param string $table Table name.
     * @return self
     */
    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Alias of table method.
     *
     * @param string $table Table name.
     * @return self
     */
    public function from(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Set select columns for query.
     *
     * @param mixed $columns Columns list.
     * @return self
     */
    public function select($columns = ['*']): self
    {
        $this->selects = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    /**
     * Add a raw select expression.
     *
     * @param string $expression Raw SQL expression.
     * @param array $bindings Expression bindings.
     * @return self
     */
    public function selectRaw(string $expression, array $bindings = []): self
    {
        $this->selectRaw[] = [
            'expression' => $expression,
            'bindings' => $bindings,
        ];

        return $this;
    }

    /**
     * Add a where clause.
     *
     * @param mixed $column Column name or callback.
     * @param mixed $operator Operator or value.
     * @param mixed $value Comparison value.
     * @param string $boolean Logical boolean.
     * @return self
     */
    public function where($column, $operator = null, $value = null, string $boolean = 'and'): self
    {
        if ($column instanceof \Closure) {
            $nested = new self($this->table, $this->modelClass);
            $column($nested);

            $this->wheres[] = [
                'type' => 'nested',
                'query' => $nested,
                'boolean' => strtolower($boolean),
            ];

            return $this;
        }

        if (func_num_args() === 2 || ($value === null && !$this->isSqlOperator($operator))) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => strtolower($boolean),
        ];

        return $this;
    }

    /**
     * Add an OR where clause.
     *
     * @param mixed $column Column name or callback.
     * @param mixed $operator Operator or value.
     * @param mixed $value Comparison value.
     * @return self
     */
    public function orWhere($column, $operator = null, $value = null): self
    {
        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * Add a where in clause.
     *
     * @param string $column Column name.
     * @param array $values Values list.
     * @param string $boolean Logical boolean.
     * @param bool $not Negation flag.
     * @return self
     */
    public function whereIn(string $column, array $values, string $boolean = 'and', bool $not = false): self
    {
        $this->wheres[] = [
            'type' => $not ? 'notIn' : 'in',
            'column' => $column,
            'values' => array_values($values),
            'boolean' => strtolower($boolean),
        ];

        return $this;
    }

    /**
     * Add a where not in clause.
     *
     * @param string $column Column name.
     * @param array $values Values list.
     * @param string $boolean Logical boolean.
     * @return self
     */
    public function whereNotIn(string $column, array $values, string $boolean = 'and'): self
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * Add a where null clause.
     *
     * @param string $column Column name.
     * @param string $boolean Logical boolean.
     * @param bool $not Negation flag.
     * @return self
     */
    public function whereNull(string $column, string $boolean = 'and', bool $not = false): self
    {
        $this->wheres[] = [
            'type' => $not ? 'notNull' : 'null',
            'column' => $column,
            'boolean' => strtolower($boolean),
        ];

        return $this;
    }

    /**
     * Add a where not null clause.
     *
     * @param string $column Column name.
     * @param string $boolean Logical boolean.
     * @return self
     */
    public function whereNotNull(string $column, string $boolean = 'and'): self
    {
        return $this->whereNull($column, $boolean, true);
    }

    /**
     * Add a raw where clause.
     *
     * @param string $sql Raw SQL expression.
     * @param array $bindings Expression bindings.
     * @param string $boolean Logical boolean.
     * @return self
     */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'and'): self
    {
        $this->wheres[] = [
            'type'    => 'raw',
            'sql'     => $sql,
            'bindings' => $bindings,
            'boolean' => strtolower($boolean),
        ];

        return $this;
    }

    /**
     * Add a where between clause.
     *
     * @param string $column Column name.
     * @param array $values Boundary values.
     * @param string $boolean Logical boolean.
     * @param bool $not Negation flag.
     * @return self
     */
    public function whereBetween(string $column, array $values, string $boolean = 'and', bool $not = false): self
    {
        $this->wheres[] = [
            'type' => $not ? 'notBetween' : 'between',
            'column' => $column,
            'values' => array_values($values),
            'boolean' => strtolower($boolean),
        ];

        return $this;
    }

    /**
     * Add an OR where between clause.
     *
     * @param string $column Column name.
     * @param array $values Boundary values.
     * @return self
     */
    public function orWhereBetween(string $column, array $values): self
    {
        return $this->whereBetween($column, $values, 'or');
    }

    /**
     * Add a JSON-contains style where clause.
     *
     * @param string $column Column name.
     * @param mixed $value JSON value.
     * @param string $boolean Logical boolean.
     * @param bool $not Negation flag.
     * @return self
     */
    public function whereJsonContains(string $column, $value, string $boolean = 'and', bool $not = false): self
    {
        $needle = is_array($value) ? wp_json_encode($value) : (string) $value;

        // Handle JSON path notation (e.g. "column->key" or "column->key->nested")
        if (strpos($column, '->') !== false) {
            [$baseColumn, $jsonPath] = explode('->', $column, 2);
            $jsonPath = '$.' . str_replace('->', '.', $jsonPath);
            $wrappedColumn = $this->wrap($baseColumn);
            $operator = $not ? '!=' : '=';
            $this->wheres[] = [
                'type'     => 'raw',
                'sql'      => "JSON_UNQUOTE(JSON_EXTRACT({$wrappedColumn}, %s)) {$operator} %s",
                'bindings' => [$jsonPath, $needle],
                'boolean'  => strtolower($boolean),
            ];
            return $this;
        }

        $operator = $not ? 'NOT LIKE' : 'LIKE';

        return $this->where($column, $operator, '%' . $needle . '%', $boolean);
    }

    /**
     * Add a where exists clause from subquery callback.
     *
     * @param callable $callback Subquery callback.
     * @param string $boolean Logical boolean.
     * @param bool $not Negation flag.
     * @return self
     */
    public function whereExists(callable $callback, string $boolean = 'and', bool $not = false): self
    {
        $subQuery = new self();
        $callback($subQuery);

        $this->wheres[] = [
            'type' => $not ? 'notExists' : 'exists',
            'query' => $subQuery,
            'boolean' => strtolower($boolean),
        ];

        return $this;
    }

    /**
     * Add a where column comparison.
     *
     * @param mixed $first First column.
     * @param mixed $operator Comparison operator.
     * @param mixed $second Second column.
     * @param string $boolean Logical boolean.
     * @return self
     */
    public function whereColumn($first, $operator = null, $second = null, string $boolean = 'and'): self
    {
        if (func_num_args() === 2) {
            $second = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'column',
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'boolean' => strtolower($boolean),
        ];

        return $this;
    }

    /**
     * Conditionally apply query callbacks.
     *
     * @param mixed $value Condition value.
     * @param callable $callback Callback when condition is truthy.
     * @param callable|null $default Callback when condition is falsy.
     * @return self
     */
    public function when($value, callable $callback, ?callable $default = null): self
    {
        if ($value) {
            $callback($this, $value);
            return $this;
        }

        if ($default !== null) {
            $default($this, $value);
        }

        return $this;
    }

    /**
     * Add order by clause.
     *
     * @param string $column Column name.
     * @param string $direction Sort direction.
     * @return self
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = [$column, $direction];
        return $this;
    }

    /**
     * Set limit clause.
     *
     * @param int $value Limit value.
     * @return self
     */
    public function limit(int $value): self
    {
        $this->limitValue = max(0, $value);
        return $this;
    }

    /**
     * Set offset clause.
     *
     * @param int $value Offset value.
     * @return self
     */
    public function offset(int $value): self
    {
        $this->offsetValue = max(0, $value);
        return $this;
    }

    /**
     * Add join clause.
     *
     * @param string $table Join table.
     * @param string $first First join column.
     * @param string|null $operator Join operator.
     * @param string|null $second Second join column.
     * @param string $type Join type.
     * @return self
     */
    public function join(string $table, string $first, ?string $operator = null, ?string $second = null, string $type = 'INNER'): self
    {
        if ($operator === null) {
            $operator = '=';
        }

        $this->joins[] = [
            'type' => strtoupper($type),
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    /**
     * Add left join clause.
     *
     * @param string $table Join table.
     * @param string $first First join column.
     * @param string|null $operator Join operator.
     * @param string|null $second Second join column.
     * @return self
     */
    public function leftJoin(string $table, string $first, ?string $operator = null, ?string $second = null): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    /**
     * Add right join clause.
     *
     * @param string $table Join table.
     * @param string $first First join column.
     * @param string|null $operator Join operator.
     * @param string|null $second Second join column.
     * @return self
     */
    public function rightJoin(string $table, string $first, ?string $operator = null, ?string $second = null): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    /**
     * Declare eager-load relations.
     *
     * @param mixed $relations Relations definition.
     * @return self
     */
    public function with($relations): self
    {
        if (is_string($relations)) {
            $relations = [$relations];
        }

        if (!is_array($relations)) {
            return $this;
        }

        foreach ($relations as $key => $relation) {
            $relationName = is_string($key) ? $key : $relation;
            if (!is_string($relationName) || $relationName === '') {
                continue;
            }

            if (!in_array($relationName, $this->eagerLoads, true)) {
                $this->eagerLoads[] = $relationName;
            }
        }

        return $this;
    }

    /**
     * Execute select query and get collection results.
     *
     * @param mixed $columns Selected columns.
     * @return RoxAppointmentBookingCollection
     */
    public function get($columns = ['*']): RoxAppointmentBookingCollection
    {
        if ($columns !== ['*']) {
            $this->select($columns);
        }

        [$sql, $bindings] = $this->compileSelect();
        $rows = $this->runSelect($sql, $bindings);

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->hydrateResult($row);
        }

        $this->eagerLoadRelations($items);

        return new RoxAppointmentBookingCollection($items);
    }

    /**
     * Execute query and return first result.
     *
     * @param mixed $columns Selected columns.
     * @return mixed
     */
    public function first($columns = ['*'])
    {
        $clone = clone $this;
        $clone->limit(1);
        return $clone->get($columns)->first();
    }

    /**
     * Find a row by primary key.
     *
     * @param mixed $id Primary key value.
     * @param string|null $idColumn Primary key column override.
     * @return mixed
     */
    public function find($id, ?string $idColumn = null)
    {
        $idColumn = $idColumn ?: $this->inferPrimaryKey();
        return $this->where($idColumn, '=', $id)->first();
    }

    /**
     * Count rows for the current query.
     *
     * @param string $column Count column.
     * @return int
     */
    public function count(string $column = '*'): int
    {
        [$sql, $bindings] = $this->compileSelect(['COUNT(' . ($column === '*' ? '*' : $this->wrap($column)) . ') as aggregate'], true);
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared
        $count = $wpdb->get_var($this->prepareSql($sql, $bindings));

        return (int) $count;
    }

    /**
     * Check whether query has at least one row.
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Pluck a single column from query results.
     *
     * @param string $column Column name.
     * @return RoxAppointmentBookingCollection
     */
    public function pluck(string $column): RoxAppointmentBookingCollection
    {
        $results = $this->get([$column]);
        return $results->pluck($column);
    }

    /**
     * Insert a row.
     *
     * @param array $values Row values.
     * @return bool
     */
    public function insert(array $values): bool
    {
        global $wpdb;

        $table = $this->resolveTable($this->table);
        $normalized = $this->normalizeData($values);

        if ($normalized === []) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert($table, $normalized, $this->inferFormats($normalized));
        return $result !== false;
    }

    /**
     * Insert a row and return created model or object.
     *
     * @param array $values Row values.
     * @return mixed
     */
    public function create(array $values)
    {
        if (!$this->insert($values)) {
            return null;
        }

        global $wpdb;

        $pk = $this->inferPrimaryKey();
        return $this->where($pk, '=', (int) $wpdb->insert_id)->first();
    }

    /**
     * Update rows matching current query.
     *
     * @param array $values Values to update.
     * @return int
     */
    public function update(array $values): int
    {
        global $wpdb;

        $table = $this->resolveTable($this->table);
        $normalized = $this->normalizeData($values);

        if ($normalized === []) {
            return 0;
        }

        [$whereSql, $whereBindings] = $this->compileWheres();

        $setSqlParts = [];
        $setBindings = [];
        foreach ($normalized as $column => $value) {
            $setSqlParts[] = $this->wrap($column) . ' = ' . $this->placeholderFor($value);
            $setBindings[] = $value;
        }

        $sql = 'UPDATE ' . $this->wrapTable($table) . ' SET ' . implode(', ', $setSqlParts) . $whereSql;
        $bindings = array_merge($setBindings, $whereBindings);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared
        $result = $wpdb->query($this->prepareSql($sql, $bindings));

        return $result === false ? 0 : (int) $result;
    }

    /**
     * Delete rows matching current query.
     *
     * @return int
     */
    public function delete(): int
    {
        global $wpdb;

        [$whereSql, $whereBindings] = $this->compileWheres();

        $sql = 'DELETE FROM ' . $this->wrapTable($this->resolveTable($this->table)) . $whereSql;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared
        $result = $wpdb->query($this->prepareSql($sql, $whereBindings));
        return $result === false ? 0 : (int) $result;
    }

    /**
     * Paginate current query results.
     *
     * @param int $perPage Items per page.
     * @param int $page Current page number.
     * @return array
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $total = $this->count();
        $items = (clone $this)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return [
            'data' => $items,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Add one or more columns to the GROUP BY clause.
     *
     * Column names are wrapped in backticks automatically.
     *
     * @param string ...$columns Column names.
     * @return self
     */
    public function groupBy(string ...$columns): self
    {
        foreach ($columns as $column) {
            $this->groups[] = $this->wrap($column);
        }

        return $this;
    }

    /**
     * Add a raw expression to the GROUP BY clause.
     *
     * Use this for expressions like DATE(`date`) that cannot be auto-wrapped.
     *
     * @param string $expression Raw SQL GROUP BY expression.
     * @return self
     */
    public function groupByRaw(string $expression): self
    {
        $this->groups[] = $expression;

        return $this;
    }

    /**
     * Execute a SUM aggregate for the given column.
     *
     * @param string $column Column to sum.
     * @return float
     */
    public function sum(string $column): float
    {
        [$sql, $bindings] = $this->compileSelect(['SUM(' . $this->wrap($column) . ') as aggregate'], true);

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared
        $result = $wpdb->get_var($this->prepareSql($sql, $bindings));

        return (float) ($result ?? 0);
    }

    /**
     * Set pivot metadata context.
     *
     * @param string $pivotTable Pivot table name.
     * @param string $parentKey Parent key in pivot.
     * @param string $relatedKey Related key in pivot.
     * @param mixed $parentId Parent id value.
     * @return self
     */
    public function usePivotContext(string $pivotTable, string $parentKey, string $relatedKey, $parentId): self
    {
        $this->pivotTable = $pivotTable;
        $this->pivotParentKey = $parentKey;
        $this->pivotRelatedKey = $relatedKey;
        $this->pivotParentId = $parentId;

        return $this;
    }

    /**
     * Set pivot columns to include in relation results.
     *
     * @param mixed ...$columns Pivot columns.
     * @return self
     */
    public function withPivot(...$columns): self
    {
        if (count($columns) === 1 && is_array($columns[0])) {
            $columns = $columns[0];
        }

        $this->pivotColumns = array_values($columns);
        return $this;
    }

    /**
     * Attach related ids to pivot table.
     *
     * @param mixed $ids Related ids.
     * @param array $attributes Extra pivot attributes.
     * @return void
     */
    public function attach($ids, array $attributes = []): void
    {
        global $wpdb;

        if ($this->pivotTable === null) {
            return;
        }

        $ids = is_array($ids) ? $ids : [$ids];
        $table = $this->resolveTable($this->pivotTable);

        foreach ($ids as $id) {
            if ($id === null || $id === '') {
                continue;
            }

            $data = array_merge($attributes, [
                $this->pivotParentKey => $this->pivotParentId,
                $this->pivotRelatedKey => $id,
            ]);

            $data = $this->normalizeData($data);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->insert($table, $data, $this->inferFormats($data));
        }
    }

    /**
     * Detach related ids from pivot table.
     *
     * @param mixed $ids Related ids.
     * @return int
     */
    public function detach($ids = null): int
    {
        if ($this->pivotTable === null) {
            return 0;
        }

        $query = new self($this->pivotTable);
        $query->where($this->pivotParentKey, '=', $this->pivotParentId);

        if ($ids !== null) {
            $ids = is_array($ids) ? $ids : [$ids];
            $query->whereIn($this->pivotRelatedKey, $ids);
        }

        return $query->delete();
    }

    /**
     * Sync related ids in pivot table.
     *
     * @param array $ids Related ids to keep.
     * @return array
     */
    public function sync(array $ids): array
    {
        if ($this->pivotTable === null) {
            return ['attached' => [], 'detached' => [], 'updated' => []];
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        $current = (new self($this->pivotTable))
            ->where($this->pivotParentKey, '=', $this->pivotParentId)
            ->pluck($this->pivotRelatedKey)
            ->toArray();

        $current = array_map('intval', $current);

        $toAttach = array_values(array_diff($ids, $current));
        $toDetach = array_values(array_diff($current, $ids));

        if ($toDetach !== []) {
            $this->detach($toDetach);
        }

        if ($toAttach !== []) {
            $this->attach($toAttach);
        }

        return [
            'attached' => $toAttach,
            'detached' => $toDetach,
            'updated' => [],
        ];
    }

    /**
     * Resolve dynamic scope calls on model.
     *
     * @param string $method Method name.
     * @param array $parameters Method parameters.
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        $model = $this->getModel();
        if ($model !== null) {
            $scope = 'scope' . ucfirst($method);
            if (method_exists($model, $scope)) {
                array_unshift($parameters, $this);
                return $model->{$scope}(...$parameters);
            }
        }

        throw new \BadMethodCallException(sprintf('Method %s does not exist.', esc_html($method)));
    }

    /**
     * Compile current select SQL and bindings.
     *
     * @param array|null $forcedColumns Forced select columns.
     * @param bool $withoutOrderAndLimit Skip order and pagination clauses.
     * @return array
     */
    protected function compileSelect(?array $forcedColumns = null, bool $withoutOrderAndLimit = false): array
    {
        $columns = $forcedColumns ?? $this->compileColumns();

        $sql = 'SELECT ' . implode(', ', $columns) . ' FROM ' . $this->wrapTable($this->resolveTable($this->table));
        $bindings = [];

        foreach ($this->joins as $join) {
            $sql .= ' ' . $join['type'] . ' JOIN ' . $this->wrapTable($this->resolveTable($join['table']));
            $sql .= ' ON ' . $this->wrap($join['first']) . ' ' . $join['operator'] . ' ' . $this->wrap($join['second']);
        }

        [$whereSql, $whereBindings] = $this->compileWheres();
        $sql .= $whereSql;
        $bindings = array_merge($bindings, $whereBindings);

        if (!empty($this->groups)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groups);
        }

        if (!$withoutOrderAndLimit) {
            if (!empty($this->orders)) {
                $orderSql = [];
                foreach ($this->orders as [$column, $direction]) {
                    $orderSql[] = $this->wrap($column) . ' ' . $direction;
                }
                $sql .= ' ORDER BY ' . implode(', ', $orderSql);
            }

            if ($this->limitValue !== null) {
                $sql .= ' LIMIT ' . (int) $this->limitValue;
            }

            if ($this->offsetValue !== null) {
                if ($this->limitValue === null) {
                    $sql .= ' LIMIT 18446744073709551615';
                }
                $sql .= ' OFFSET ' . (int) $this->offsetValue;
            }
        }

        return [$sql, $bindings];
    }

    /**
     * Compile select column list.
     *
     * @return array
     */
    protected function compileColumns(): array
    {
        $columns = [];

        foreach ($this->selects as $column) {
            if ($column === '*') {
                $columns[] = '*';
                continue;
            }

            $columns[] = $this->wrap($column);
        }

        foreach ($this->selectRaw as $raw) {
            $columns[] = $raw['expression'];
        }

        if ($columns === []) {
            return ['*'];
        }

        return $columns;
    }

    /**
     * Compile where SQL and bindings.
     *
     * @return array
     */
    protected function compileWheres(): array
    {
        if ($this->wheres === []) {
            return ['', []];
        }

        $segments = [];
        $bindings = [];

        foreach ($this->wheres as $index => $where) {
            $boolean = strtoupper($where['boolean'] ?? 'AND');
            $prefix = $index === 0 ? '' : ' ' . $boolean . ' ';

            switch ($where['type']) {
                case 'basic':
                    $segments[] = $prefix . $this->wrap($where['column']) . ' ' . $where['operator'] . ' ' . $this->placeholderFor($where['value']);
                    $bindings[] = $where['value'];
                    break;

                case 'in':
                case 'notIn':
                    $values = $where['values'];
                    if ($values === []) {
                        $segments[] = $prefix . ($where['type'] === 'in' ? '1 = 0' : '1 = 1');
                        break;
                    }

                    $placeholders = implode(', ', array_map([$this, 'placeholderFor'], $values));
                    $segments[] = $prefix . $this->wrap($where['column']) . ($where['type'] === 'notIn' ? ' NOT IN (' : ' IN (') . $placeholders . ')';
                    $bindings = array_merge($bindings, $values);
                    break;

                case 'between':
                case 'notBetween':
                    $values = array_pad($where['values'], 2, null);
                    $segments[] = $prefix . $this->wrap($where['column'])
                        . ($where['type'] === 'notBetween' ? ' NOT BETWEEN ' : ' BETWEEN ')
                        . $this->placeholderFor($values[0]) . ' AND ' . $this->placeholderFor($values[1]);
                    $bindings[] = $values[0];
                    $bindings[] = $values[1];
                    break;

                case 'null':
                    $segments[] = $prefix . $this->wrap($where['column']) . ' IS NULL';
                    break;

                case 'notNull':
                    $segments[] = $prefix . $this->wrap($where['column']) . ' IS NOT NULL';
                    break;

                case 'nested':
                    [$nestedSql, $nestedBindings] = $where['query']->compileWheres();
                    $nestedSql = preg_replace('/^\s*WHERE\s+/i', '', trim($nestedSql));
                    $segments[] = $prefix . '(' . $nestedSql . ')';
                    $bindings = array_merge($bindings, $nestedBindings);
                    break;

                case 'column':
                    $segments[] = $prefix . $this->wrap($where['first']) . ' ' . $where['operator'] . ' ' . $this->wrap($where['second']);
                    break;

                case 'exists':
                case 'notExists':
                    [$existsSql, $existsBindings] = $where['query']->compileSelect();
                    $segments[] = $prefix . ($where['type'] === 'notExists' ? 'NOT EXISTS (' : 'EXISTS (') . $existsSql . ')';
                    $bindings = array_merge($bindings, $existsBindings);
                    break;

                case 'raw':
                    $segments[] = $prefix . $where['sql'];
                    $bindings = array_merge($bindings, $where['bindings']);
                    break;
            }
        }

        return [' WHERE ' . implode('', $segments), $bindings];
    }

    /**
     * Run a select query and return raw rows.
     *
     * @param string $sql SQL string.
     * @param array $bindings SQL bindings.
     * @return array
     */
    protected function runSelect(string $sql, array $bindings): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared
        $result = $wpdb->get_results($this->prepareSql($sql, $bindings));

        return is_array($result) ? $result : [];
    }

    /**
     * Always prepare SQL before execution.
     *
     * Adds a trailing comment placeholder so queries without bindings are still
     * passed through $wpdb->prepare in a deterministic way.
     *
     * @param string $sql SQL string.
     * @param array $bindings SQL bindings.
     * @return string
     */
    protected function prepareSql(string $sql, array $bindings): string
    {
        global $wpdb;

        $bindings[] = 1;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic SQL is composed with placeholders and bound values before prepare.
        $preparedSql = $wpdb->prepare($sql . ' /* qb:%d */', ...$bindings);

        return is_string($preparedSql) ? $preparedSql : $sql;
    }

    /**
     * Hydrate raw row into model or stdClass.
     *
     * @param object $row Raw database row.
     * @return mixed
     */
    protected function hydrateResult(object $row)
    {
        if ($this->modelClass !== null && class_exists($this->modelClass)) {
            if (method_exists($this->modelClass, 'hydrateFromDatabaseRow')) {
                return call_user_func([$this->modelClass, 'hydrateFromDatabaseRow'], $row);
            }

            return new $this->modelClass((array) $row);
        }

        return $row;
    }

    /**
     * Eager load requested relations for hydrated model results.
     *
     * @param array $items Hydrated model rows.
     * @return void
     */
    protected function eagerLoadRelations(array $items): void
    {
        if ($this->eagerLoads === []) {
            return;
        }

        foreach ($items as $item) {
            if (!is_object($item)) {
                continue;
            }

            foreach ($this->eagerLoads as $relation) {
                if (!method_exists($item, $relation)) {
                    continue;
                }

                $related = $item->{$relation}();

                if ($related instanceof self) {
                    $related = $related->get();
                }

                if (method_exists($item, 'setRelation')) {
                    $item->setRelation($relation, $related);
                }
            }
        }
    }

    /**
     * Resolve table name with WordPress prefix.
     *
     * @param string $table Table name.
     * @return string
     */
    protected function resolveTable(string $table): string
    {
        global $wpdb;

        if ($table === '') {
            return '';
        }

        if (str_starts_with($table, $wpdb->prefix)) {
            return $table;
        }

        return $wpdb->prefix . $table;
    }

    /**
     * Wrap table identifier.
     *
     * @param string $table Table name.
     * @return string
     */
    protected function wrapTable(string $table): string
    {
        return $this->wrap($table);
    }

    /**
     * Wrap SQL identifiers with backticks.
     *
     * @param string $identifier Identifier value.
     * @return string
     */
    protected function wrap(string $identifier): string
    {
        if ($identifier === '*') {
            return '*';
        }

        $parts = explode('.', $identifier);
        $parts = array_map(
            function ($part) {
                if ($part === '*') {
                    return '*';
                }

                $part = preg_replace('/[^A-Za-z0-9_]/', '', $part);
                return '`' . $part . '`';
            },
            $parts
        );

        return implode('.', $parts);
    }

    /**
     * Resolve SQL placeholder for value type.
     *
     * @param mixed $value Placeholder value.
     * @return string
     */
    protected function placeholderFor($value): string
    {
        if (is_int($value) || is_bool($value)) {
            return '%d';
        }

        if (is_float($value)) {
            return '%f';
        }

        return '%s';
    }

    /**
     * Normalize data keys and values before writes.
     *
     * @param array $data Input data.
     * @return array
     */
    protected function normalizeData(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            $safeKey = preg_replace('/[^A-Za-z0-9_]/', '', (string) $key);
            if ($safeKey === '') {
                continue;
            }

            if (is_array($value)) {
                $normalized[$safeKey] = wp_json_encode($value);
                continue;
            }

            $normalized[$safeKey] = $value;
        }

        return $normalized;
    }

    /**
     * Infer $wpdb format specifiers for values.
     *
     * @param array $data Input data.
     * @return array
     */
    protected function inferFormats(array $data): array
    {
        $formats = [];

        foreach ($data as $value) {
            if (is_int($value) || is_bool($value)) {
                $formats[] = '%d';
                continue;
            }

            if (is_float($value)) {
                $formats[] = '%f';
                continue;
            }

            $formats[] = '%s';
        }

        return $formats;
    }

    /**
     * Infer model primary key name.
     *
     * @return string
     */
    protected function inferPrimaryKey(): string
    {
        $model = $this->getModel();
        if ($model !== null && method_exists($model, 'getPrimaryKeyName')) {
            return $model->getPrimaryKeyName();
        }

        return 'id';
    }

    /**
     * Check if value is a supported SQL operator.
     *
     * @param mixed $operator Operator candidate.
     * @return bool
     */
    protected function isSqlOperator($operator): bool
    {
        if (!is_string($operator)) {
            return false;
        }

        $operator = strtoupper(trim($operator));
        $validOperators = [
            '=', '!=', '<>', '<', '<=', '>', '>=',
            'LIKE', 'NOT LIKE',
            'IN', 'NOT IN',
            'BETWEEN', 'NOT BETWEEN',
            'IS', 'IS NOT',
        ];

        return in_array($operator, $validOperators, true);
    }
}
