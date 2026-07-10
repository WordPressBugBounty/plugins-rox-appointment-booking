<?php

namespace RoxAppointmentBooking\Supports\QueryBuilder;

use RoxAppointmentBooking\Supports\Abstracts\AbstractModel;

defined('ABSPATH') || exit;

/**
 * Handles many-to-many pivot operations for model relations.
 */
class AbstractRelationModel extends RoxAppointmentBookingQueryBuilder
{
    protected AbstractModel $parent;
    protected string $relatedModel;
    protected ?string $pivotTable;
    protected ?string $foreignPivotKey;
    protected ?string $relatedPivotKey;

    /**
     * Initialize relation query with pivot join context.
     *
     * @param AbstractModel $parent Parent model instance.
     * @param string $relatedModel Related model class name.
     * @param string $pivotTable Pivot table name.
     * @param string $foreignPivotKey Parent key in pivot table.
     * @param string $relatedPivotKey Related key in pivot table.
     * @return void
     */
    public function __construct(
        AbstractModel $parent,
        string $relatedModel,
        string $pivotTable,
        string $foreignPivotKey,
        string $relatedPivotKey
    ) {
        $this->parent = $parent;
        $this->relatedModel = $relatedModel;
        $this->pivotTable = $pivotTable;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;

        $relatedInstance = new $relatedModel();
        $relatedTable = $relatedInstance->getTable();
        $relatedPrimaryKey = $relatedInstance->getPrimaryKeyName();

        parent::__construct($relatedTable, $relatedModel);

        $this->join(
            $pivotTable,
            $relatedTable . '.' . $relatedPrimaryKey,
            '=',
            $pivotTable . '.' . $relatedPivotKey
        )->where($pivotTable . '.' . $foreignPivotKey, '=', $parent->getID())
            ->usePivotContext($pivotTable, $foreignPivotKey, $relatedPivotKey, $parent->getID());
    }

    /**
     * Include pivot columns in relation query results.
     *
     * @param mixed ...$columns Pivot columns to include.
     * @return self
     */
    public function withPivot(...$columns): self
    {
        parent::withPivot(...$columns);

        $relatedTable = (new $this->relatedModel())->getTable();
        $selected = [$relatedTable . '.*'];

        foreach ($this->pivotColumns as $column) {
            $column = preg_replace('/[^A-Za-z0-9_]/', '', (string) $column);
            if ($column === '') {
                continue;
            }

            $selected[] = $this->pivotTable . '.' . $column . ' as pivot_' . $column;
        }

        $this->select($selected);

        return $this;
    }

    /**
     * Attach related IDs in the pivot table.
     *
     * @param mixed $ids Related IDs.
     * @param array $attributes Extra pivot attributes.
     * @return void
     */
    public function attach($ids, array $attributes = []): void
    {
        parent::attach($ids, $attributes);
    }

    /**
     * Detach related IDs from the pivot table.
     *
     * @param mixed $ids Related IDs to detach.
     * @return int
     */
    public function detach($ids = null): int
    {
        return parent::detach($ids);
    }

    /**
     * Sync related IDs in the pivot table.
     *
     * @param array $ids Related IDs to keep.
     * @return array
     */
    public function sync(array $ids): array
    {
        return parent::sync($ids);
    }
}
