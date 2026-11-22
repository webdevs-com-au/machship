<?php

namespace WebdevsComAu\MachShip\Data\Responses;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use JsonSerializable;
use LogicException;

/**
 * Base for response objects. Commonly used fields are typed properties;
 * everything MachShip returned is still reachable via get('dot.path'),
 * array access, or toArray().
 *
 * @implements ArrayAccess<string, mixed>
 * @implements Arrayable<string, mixed>
 */
abstract class Data implements ArrayAccess, Arrayable, JsonSerializable
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(protected array $attributes = [])
    {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    abstract public static function fromArray(array $attributes): static;

    /**
     * @param  iterable<array<string, mixed>>|null  $rows
     * @return Collection<int, static>
     */
    public static function collect(?iterable $rows): Collection
    {
        return Collection::make($rows ?? [])->map(fn (array $row) => static::fromArray($row))->values();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->attributes, $key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function jsonSerialize(): mixed
    {
        return $this->attributes;
    }

    public function offsetExists(mixed $offset): bool
    {
        return Arr::has($this->attributes, $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('MachShip response objects are read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('MachShip response objects are read-only.');
    }

    protected static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    protected static function float(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
