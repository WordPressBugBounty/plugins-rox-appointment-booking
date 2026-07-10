<?php

namespace RoxAppointmentBooking\Supports\Abstracts;

use RoxAppointmentBooking\Supports\QueryBuilder\AbstractRelationModel;
use RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingCollection;
use RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingModelConnection;
use RoxAppointmentBooking\Supports\QueryBuilder\RoxAppointmentBookingQueryBuilder;

defined('ABSPATH') || exit;

/**
 * Base model replacing wp-orm model for plugin modules.
 */
abstract class AbstractModel
{
    /**
     * Model table name (without prefix).
     *
     * @var string
     */
    protected $table = '';

    /**
     * Primary key column name.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Mass-assignable attributes.
     *
     * @var array
     */
    protected $fillable = [];
    
    /**
     * Attribute cast rules.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * Whether the model exists in the database.
     *
     * @var bool
     */
    protected bool $exists = false;

    /**
     * Cached table columns by table name.
     *
     * @var array
     */
    protected static array $tableColumnsCache = [];

    /**
     * Cached JSON columns by table name.
     *
     * @var array
     */
    protected static array $tableJsonColumnsCache = [];

    /**
     * Raw attribute storage.
     *
     * @var array
     */
    protected array $attributes = [];
    
    /**
     * Loaded relation values.
     *
     * @var array
     */
    protected array $relations = [];

    /**
     * Initialize the model with raw attributes.
     *
     * @param array $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        $this->fillRaw($attributes);
    }

    /**
     * Begin a new query against the model's table.
     * 
     * @return RoxAppointmentBookingQueryBuilder
     */
    public static function query(): RoxAppointmentBookingQueryBuilder
    {
        $model = new static();

        return new RoxAppointmentBookingQueryBuilder($model->getTable(), static::class);
    }

    /**
     * Add a where clause to a new model query.
     *
     * @param mixed $column Column name.
     * @param mixed $operator Operator or value.
     * @param mixed $value Comparison value.
     * @param string $boolean Logical boolean.
     * @return RoxAppointmentBookingQueryBuilder
     */
    public static function where($column, $operator = null, $value = null, string $boolean = 'and'): RoxAppointmentBookingQueryBuilder
    {
        $query = static::query();
        $argumentCount = func_num_args();

        if ($argumentCount === 2) {
            return $query->where($column, $operator);
        }

        if ($argumentCount === 3) {
            return $query->where($column, $operator, $value);
        }

        return $query->where($column, $operator, $value, $boolean);
    }

    /**
     * Get all model records.
     *
     * @return RoxAppointmentBookingCollection
     */
    public static function all(): RoxAppointmentBookingCollection
    {
        return static::query()->get();
    }

    /**
     * Find a model by primary key.
     *
     * @param mixed $id Primary key value.
     * @return self|null
     */
    public static function find($id): ?self
    {
        $model = new static();
        $found = static::query()->where($model->primaryKey, '=', $id)->first();

        return $found instanceof self ? $found : null;
    }

    /**
     * Create and persist a new model.
     *
     * @param array $attributes Model attributes.
     * @return self
     */
    public static function create(array $attributes): self
    {
        $model = new static();
        $model->fill($attributes);
        $model->save();

        return $model;
    }

    /**
     * Hydrate a model from database row data.
     *
     * @param object $row Database row object.
     * @return self
     */
    public static function hydrateFromDatabaseRow(object $row): self
    {
        $model = new static((array) $row);
        $model->exists = true;

        return $model;
    }

    /**
     * Get model table name with WordPress prefix.
     *
     * @return string
     */
    public function getTable(): string
    {
        global $wpdb;

        if ($this->table === '') {
            return $wpdb->prefix;
        }

        if (str_starts_with($this->table, $wpdb->prefix)) {
            return $this->table;
        }

        return $wpdb->prefix . $this->table;
    }

    /**
     * Get model primary key name.
     *
     * @return string
     */
    public function getPrimaryKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Get fillable attributes list.
     *
     * @return array
     */
    public function getFillable(): array
    {
        return $this->fillable;
    }

    /**
     * Get raw model attributes.
     *
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Fill model using only fillable attributes.
     *
     * @param array $attributes Attributes to fill.
     * @return self
     */
    public function fill(array $attributes): self
    {
        // wp-orm base model uses guarded=[] behavior, so all attributes are mass assignable.
        return $this->fillRaw($attributes);
    }

    /**
     * Fill model using all provided attributes.
     *
     * @param array $attributes Attributes to set.
     * @return self
     */
    public function fillRaw(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute((string) $key, $value);
        }

        return $this;
    }

    /**
     * Persist the model to database.
     *
     * @return bool
     */
    public function save(): bool
    {
        $primaryKey = $this->primaryKey;

        if ($this->exists) {
            $id = $this->attributes[$primaryKey] ?? null;
            if ($id === null) {
                return false;
            }

            $payload = $this->attributes;
            unset($payload[$primaryKey]);

            if (array_key_exists('updated_at', $payload)) {
                $payload['updated_at'] = gmdate('Y-m-d H:i:s');
                $this->attributes['updated_at'] = $payload['updated_at'];
            }

            $payload = $this->filterPersistableAttributes($payload);
            $payload = $this->normalizePersistableAttributes($payload);
            if (empty($payload)) {
                return true;
            }

            $updated = static::query()
                ->where($primaryKey, '=', $id)
                ->update($payload);

            return $updated >= 0;
        }

        $payload = $this->attributes;

        if (!array_key_exists('created_at', $payload)) {
            $payload['created_at'] = gmdate('Y-m-d H:i:s');
            $this->attributes['created_at'] = $payload['created_at'];
        }

        if (!array_key_exists('updated_at', $payload)) {
            $payload['updated_at'] = gmdate('Y-m-d H:i:s');
            $this->attributes['updated_at'] = $payload['updated_at'];
        }

        $payload = $this->filterPersistableAttributes($payload);
        $payload = $this->normalizePersistableAttributes($payload);
        if (empty($payload)) {
            return false;
        }

        $inserted = static::query()->insert($payload);
        if (!$inserted) {
            return false;
        }

        global $wpdb;

        $this->attributes[$primaryKey] = (int) $wpdb->insert_id;
        $this->exists = true;

        return true;
    }

    /**
     * Update the current model with the provided attributes.
     *
     * @param array $attributes Attributes to update.
     * @return bool
     */
    public function update(array $attributes = []): bool
    {
        $primaryKey = $this->primaryKey;
        $id = $this->attributes[$primaryKey] ?? null;

        if ($id === null) {
            return false;
        }

        if (!empty($attributes)) {
            $this->fill($attributes);
        }

        $payload = $this->attributes;
        unset($payload[$primaryKey]);

        if (array_key_exists('updated_at', $payload)) {
            $payload['updated_at'] = gmdate('Y-m-d H:i:s');
            $this->attributes['updated_at'] = $payload['updated_at'];
        }

        $payload = $this->filterPersistableAttributes($payload);
        $payload = $this->normalizePersistableAttributes($payload);
        if (empty($payload)) {
            return true;
        }

        $updated = static::query()
            ->where($primaryKey, '=', $id)
            ->update($payload);

        $this->exists = true;

        return $updated >= 0;
    }

    /**
     * Delete the current model from database.
     *
     * @return bool
     */
    public function delete(): bool
    {
        $primaryKey = $this->primaryKey;
        $id = $this->attributes[$primaryKey] ?? null;

        if ($id === null) {
            return false;
        }

        $deleted = static::query()
            ->where($primaryKey, '=', $id)
            ->delete();

        if ($deleted > 0) {
            $this->exists = false;
            return true;
        }

        return false;
    }

    /**
     * Get current model primary key value.
     *
     * @return mixed
     */
    public function getID()
    {
        $id = $this->attributes[$this->primaryKey] ?? null;

        if ($id !== null && is_numeric($id)) {
            return (int) $id;
        }

        return $id;
    }

    /**
     * Get model connection helper.
     *
     * @return RoxAppointmentBookingModelConnection
     */
    public function getConnection(): RoxAppointmentBookingModelConnection
    {
        return new RoxAppointmentBookingModelConnection();
    }

    /**
     * Check if a relation is loaded.
     *
     * @param string $name Relation name.
     * @return bool
     */
    public function relationLoaded(string $name): bool
    {
        return array_key_exists($name, $this->relations);
    }

    /**
     * Set a loaded relation value.
     *
     * @param string $name Relation name.
     * @param mixed $value Relation value.
     * @return self
     */
    public function setRelation(string $name, $value): self
    {
        $this->relations[$name] = $value;
        return $this;
    }

    /**
     * Build a belongs-to-many relation handler.
     *
     * @param string $relatedModel Related model class name.
     * @param string $pivotTable Pivot table name.
     * @param string $foreignPivotKey Parent key in pivot table.
     * @param string $relatedPivotKey Related key in pivot table.
     * @return AbstractRelationModel
     */
    public function belongsToMany(string $relatedModel, string $pivotTable, string $foreignPivotKey, string $relatedPivotKey): AbstractRelationModel
    {
        return new AbstractRelationModel($this, $relatedModel, $pivotTable, $foreignPivotKey, $relatedPivotKey);
    }

    /**
     * Build a has-many query.
     *
     * @param string $relatedModel Related model class name.
     * @param string $foreignKey Foreign key column.
     * @return RoxAppointmentBookingQueryBuilder
     */
    public function hasMany(string $relatedModel, string $foreignKey): RoxAppointmentBookingQueryBuilder
    {
        return $relatedModel::query()->where($foreignKey, '=', $this->getID());
    }

    /**
     * Resolve belongs-to related model.
     *
     * @param string $relatedModel Related model class name.
     * @param string $foreignKey Foreign key column.
     * @return mixed
     */
    public function belongsTo(string $relatedModel, string $foreignKey)
    {
        $id = $this->attributes[$foreignKey] ?? null;
        if ($id === null) {
            return null;
        }

        return $relatedModel::find($id);
    }

    /**
     * Convert model attributes to array.
     *
     * @return array
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this->attributes as $key => $value) {
            if (array_key_exists($key, $this->casts)) {
                $result[$key] = $this->castValue($key, $value);
                continue;
            }

            if (($key === 'id' || str_ends_with($key, '_id')) && is_numeric($value)) {
                $result[$key] = (int) $value;
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Get dynamic attribute or relation value.
     *
     * @param string $name Property name.
     * @return mixed
     */
    public function __get(string $name)
    {
        if (array_key_exists($name, $this->attributes)) {
            return $this->castValue($name, $this->attributes[$name]);
        }

        if (array_key_exists($name, $this->relations)) {
            return $this->relations[$name];
        }

        $accessor = 'get' . $this->studly($name) . 'Attribute';
        if (method_exists($this, $accessor)) {
            return $this->{$accessor}();
        }

        // Support lazy-loaded relation access via property syntax, e.g. $model->services.
        if (method_exists($this, $name)) {
            $relation = $this->{$name}();

            if ($relation instanceof RoxAppointmentBookingQueryBuilder || $relation instanceof AbstractRelationModel) {
                $relation = $relation->get();
            }

            $this->relations[$name] = $relation;

            return $relation;
        }

        return null;
    }

    /**
     * Set dynamic attribute value.
     *
     * @param string $name Property name.
     * @param mixed $value Property value.
     * @return void
     */
    public function __set(string $name, $value): void
    {
        $this->setAttribute($name, $value);
    }

    /**
     * Determine whether a dynamic attribute or relation is set.
     *
     * @param string $name Property name.
     * @return bool
     */
    public function __isset(string $name): bool
    {
        if (array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name] !== null;
        }

        if (array_key_exists($name, $this->relations)) {
            return $this->relations[$name] !== null;
        }

        $accessor = 'get' . $this->studly($name) . 'Attribute';

        return method_exists($this, $accessor);
    }

    /**
     * Unset a dynamic attribute or relation.
     *
     * @param string $name Property name.
     * @return void
     */
    public function __unset(string $name): void
    {
        unset($this->attributes[$name], $this->relations[$name]);
    }

    /**
     * Handle dynamic getter and setter methods.
     *
     * @param string $method Method name.
     * @param array $parameters Method parameters.
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        if (preg_match('/^(get|set)([A-Z].*)$/', $method, $matches) === 1) {
            $type = $matches[1];
            $attribute = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $matches[2]));

            if ($type === 'get') {
                return $this->__get($attribute);
            }

            $this->setAttribute($attribute, $parameters[0] ?? null);
            return $this;
        }

        throw new \BadMethodCallException(sprintf('Method %s does not exist.', esc_html($method)));
    }

    /**
     * Handle dynamic static proxy calls to query builder.
     *
     * @param string $method Method name.
     * @param array $parameters Method parameters.
     * @return mixed
     */
    public static function __callStatic(string $method, array $parameters)
    {
        $builder = static::query();

        // Always delegate to the builder so dynamic scopes (scopeXxx) also work.
        return $builder->{$method}(...$parameters);
    }

    /**
     * Set a model attribute value.
     *
     * @param string $key Attribute name.
     * @param mixed $value Attribute value.
     * @return void
     */
    protected function setAttribute(string $key, $value): void
    {
        if (($this->casts[$key] ?? null) === 'json') {
            if ($value === '' || $value === null) {
                $this->attributes[$key] = wp_json_encode([]);
                return;
            }

            if (is_array($value)) {
                $this->attributes[$key] = wp_json_encode($value);
                return;
            }

            if (is_string($value)) {
                json_decode($value);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->attributes[$key] = $value;
                    return;
                }

                $this->attributes[$key] = wp_json_encode([$value]);
                return;
            }
        }

        if (is_array($value)) {
            $this->attributes[$key] = wp_json_encode($value);
            return;
        }

        $this->attributes[$key] = $value;
    }

    /**
     * Keep only attributes that exist as columns in the model table.
     *
     * @param array $attributes Attributes to persist.
     * @return array
     */
    protected function filterPersistableAttributes(array $attributes): array
    {
        $columns = $this->getTableColumns();
        if (empty($columns)) {
            return $attributes;
        }

        $allowedColumns = array_flip($columns);

        return array_intersect_key($attributes, $allowedColumns);
    }

    /**
     * Get and cache table columns for the current model.
     *
     * @return array
     */
    protected function getTableColumns(): array
    {
        $table = $this->getTable();
        if ($table === '') {
            return [];
        }

        if (!array_key_exists($table, self::$tableColumnsCache)) {
            $schema = $this->getConnection()->getSchemaBuilder();
            self::$tableColumnsCache[$table] = $schema->getColumnListing($table);
        }

        return self::$tableColumnsCache[$table];
    }

    /**
     * Normalize values for JSON-like columns before persistence.
     *
     * @param array $attributes Attributes to persist.
     * @return array
     */
    protected function normalizePersistableAttributes(array $attributes): array
    {
        if ($attributes === []) {
            return $attributes;
        }

        $jsonColumns = $this->getJsonColumnNames();
        if ($jsonColumns === []) {
            return $attributes;
        }

        $jsonColumnLookup = array_flip($jsonColumns);

        foreach ($attributes as $key => $value) {
            if (!isset($jsonColumnLookup[$key])) {
                continue;
            }

            if ($value === '' || $value === null) {
                $attributes[$key] = wp_json_encode([]);
                continue;
            }

            if (is_array($value)) {
                $attributes[$key] = wp_json_encode($value);
                continue;
            }

            if (is_string($value)) {
                json_decode($value);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $attributes[$key] = wp_json_encode([$value]);
                }
            }
        }

        return $attributes;
    }

    /**
     * Get JSON-related columns from casts and database schema.
     *
     * @return array
     */
    protected function getJsonColumnNames(): array
    {
        $castJsonColumns = [];

        foreach ($this->casts as $column => $cast) {
            if ($cast === 'json' || $cast === 'array') {
                $castJsonColumns[] = (string) $column;
            }
        }

        $schemaJsonColumns = $this->getTableJsonColumns();

        return array_values(array_unique(array_merge($castJsonColumns, $schemaJsonColumns)));
    }

    /**
     * Get and cache JSON column names from table schema.
     *
     * @return array
     */
    protected function getTableJsonColumns(): array
    {
        $table = $this->getTable();
        if ($table === '') {
            return [];
        }

        if (!array_key_exists($table, self::$tableJsonColumnsCache)) {
            global $wpdb;

            $safeTable = preg_replace('/[^A-Za-z0-9_]/', '', $table);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT COLUMN_NAME AS Field, DATA_TYPE AS Type
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s
                    ORDER BY ORDINAL_POSITION ASC',
                    DB_NAME,
                    $safeTable
                )
            );

            $jsonColumns = [];
            if (is_array($results)) {
                foreach ($results as $column) {
                    $type = strtolower((string) ($column->Type ?? ''));
                    if (str_contains($type, 'json') && isset($column->Field)) {
                        $jsonColumns[] = (string) $column->Field;
                    }
                }
            }

            self::$tableJsonColumnsCache[$table] = $jsonColumns;
        }

        return self::$tableJsonColumnsCache[$table];
    }

    /**
     * Cast attribute value by configured cast rules.
     *
     * @param string $key Attribute name.
     * @param mixed $value Attribute value.
     * @return mixed
     */
    protected function castValue(string $key, $value)
    {
        if (!array_key_exists($key, $this->casts)) {
            return $value;
        }

        $cast = $this->casts[$key];

        switch ($cast) {
            case 'boolean':
            case 'bool':
                return (bool) $value;
            case 'integer':
            case 'int':
                return $value === null ? null : (int) $value;
            case 'float':
            case 'double':
            case 'real':
                return $value === null ? null : (float) $value;
            case 'json':
            case 'array':
                if (is_array($value)) {
                    return $value;
                }
                return json_decode((string) $value, true);
            default:
                return $value;
        }
    }

    /**
     * Convert string value to StudlyCase.
     *
     * @param string $value Input value.
     * @return string
     */
    protected function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        return str_replace(' ', '', ucwords($value));
    }
}
