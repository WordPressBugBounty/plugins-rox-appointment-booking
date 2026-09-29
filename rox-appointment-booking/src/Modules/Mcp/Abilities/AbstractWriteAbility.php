<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities;

defined('ABSPATH') || exit;

use WP_Error;

/**
 * Class AbstractWriteAbility
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities
 * @description Base for abilities that create or change records.
 */
abstract class AbstractWriteAbility extends AbstractAbility
{
    protected bool $readOnly = false;

    protected bool $idempotent = false;

    /**
     * Stored fillable values of a record, used as the base of an update payload.
     *
     * The save handlers reset absent fields (login links, flags, notes), so an
     * update always resends what is stored and changes only what was asked.
     *
     * @param string $modelClass
     * @param int    $id
     * @return array|null
     */
    protected function stored(string $modelClass, int $id): ?array
    {
        $model = $modelClass::find($id);
        if (!$model) {
            return null;
        }

        $values = array_intersect_key($model->toArray(), array_flip($model->getFillable()));
        unset($values['created_at'], $values['updated_at']);

        return $values;
    }

    /**
     * Confirm the caller may read a record before changing it.
     *
     * @param string $route e.g. 'appointment/12'
     * @return true|WP_Error
     */
    protected function readable(string $route)
    {
        $result = $this->dispatch('GET', $route);

        return is_wp_error($result) ? $result : true;
    }

    /**
     * Input values present for the given keys, sanitised as text.
     *
     * @param array    $input
     * @param string[] $keys
     * @return array
     */
    protected function texts(array $input, array $keys): array
    {
        $values = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                $values[$key] = sanitize_text_field((string) $input[$key]);
            }
        }

        return $values;
    }

    /**
     * Positive integer ids from an input list.
     *
     * @param mixed $value
     * @return int[]
     */
    protected function ids($value): array
    {
        return array_values(array_filter(array_map('absint', (array) $value)));
    }

    /**
     * Id list input property.
     *
     * @param string $description
     * @return array
     */
    protected function idsProperty(string $description): array
    {
        return ['type' => 'array', 'items' => ['type' => 'integer', 'minimum' => 1], 'description' => $description];
    }
}
