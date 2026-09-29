<?php

namespace RoxAppointmentBooking\Modules\Mcp\Abilities;

defined('ABSPATH') || exit;

/**
 * Class AbstractDeleteAbility
 *
 * @package RoxAppointmentBooking\Modules\Mcp\Abilities
 * @description Base for abilities that permanently delete one record.
 */
abstract class AbstractDeleteAbility extends AbstractAbility
{
    protected bool $readOnly = false;

    protected bool $destructive = true;

    protected bool $idempotent = false;

    /**
     * REST route of the record, without the id, e.g. 'customer'.
     *
     * @var string
     */
    protected string $route = '';

    protected function properties(): array
    {
        return $this->idProperty('Id of the record to delete.');
    }

    protected function required(): array
    {
        return ['id'];
    }

    protected function run(array $input)
    {
        $id     = absint($input['id'] ?? 0);
        $result = $this->dispatch('DELETE', $this->route . '/' . $id);

        return is_wp_error($result) ? $result : ['deleted' => true, 'id' => $id];
    }
}
