<?php

namespace RoxAppointmentBooking\Supports\QueryBuilder;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use Traversable;

defined('ABSPATH') || exit;

/**
 * Lightweight collection compatible with common wp-orm usage in this plugin.
 */
class RoxAppointmentBookingCollection implements IteratorAggregate, Countable, ArrayAccess
{
    protected array $items = [];

    /**
     * Initialize collection items.
     *
     * @param array $items Initial items.
     * @return void
     */
    public function __construct(array $items = [])
    {
        $this->items = array_values($items);
    }

    /**
     * Get all items.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Add an item to the collection.
     *
     * @param mixed $item Item value.
     * @return self
     */
    public function push($item): self
    {
        $this->items[] = $item;
        return $this;
    }

    /**
     * Map each item using a callback.
     *
     * @param callable $callback Mapping callback.
     * @return self
     */
    public function map(callable $callback): self
    {
        return new self(array_map($callback, $this->items));
    }

    /**
     * Filter items using a callback.
     *
     * @param callable|null $callback Filter callback.
     * @return self
     */
    public function filter(?callable $callback = null): self
    {
        if ($callback === null) {
            return new self(array_values(array_filter($this->items)));
        }

        return new self(array_values(array_filter($this->items, $callback)));
    }

    /**
     * Extract values from items by key.
     *
     * @param string $key Key to extract.
     * @return self
     */
    public function pluck(string $key): self
    {
        $items = [];

        foreach ($this->items as $item) {
            if (is_array($item) && array_key_exists($key, $item)) {
                $items[] = $this->normalizePluckedValue($key, $item[$key]);
                continue;
            }

            if (is_object($item) && isset($item->{$key})) {
                $items[] = $this->normalizePluckedValue($key, $item->{$key});
            }
        }

        return new self($items);
    }

    /**
     * Normalize plucked ID-like values to integers when numeric.
     *
     * @param string $key Plucked key.
     * @param mixed $value Plucked value.
     * @return mixed
     */
    protected function normalizePluckedValue(string $key, $value)
    {
        if (($key === 'id' || str_ends_with($key, '_id')) && is_numeric($value)) {
            return (int) $value;
        }

        return $value;
    }

    /**
     * Get the first matching item.
     *
     * @param callable|null $callback Match callback.
     * @param mixed $default Default value when not found.
     * @return mixed
     */
    public function first(callable $callback = null, $default = null)
    {
        if ($callback === null) {
            return $this->items[0] ?? $default;
        }

        foreach ($this->items as $item) {
            if ($callback($item)) {
                return $item;
            }
        }

        return $default;
    }

    /**
     * Convert collection items to array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_map(
            function ($item) {
                if (is_object($item) && method_exists($item, 'toArray')) {
                    return $item->toArray();
                }

                if (is_object($item)) {
                    return (array) $item;
                }

                return $item;
            },
            $this->items
        );
    }

    /**
     * Get iterator for foreach support.
     *
     * @return Traversable
     */
    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * Get total number of items.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Check whether the collection has no items.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * Check if an offset exists.
     *
     * @param mixed $offset Item offset.
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * Get an item by offset.
     *
     * @param mixed $offset Item offset.
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    /**
     * Set an item by offset.
     *
     * @param mixed $offset Item offset.
     * @param mixed $value Item value.
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
            return;
        }

        $this->items[$offset] = $value;
    }

    /**
     * Remove an item by offset.
     *
     * @param mixed $offset Item offset.
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }
}
