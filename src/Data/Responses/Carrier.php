<?php

namespace WebdevsComAu\MachShip\Data\Responses;

/**
 * A carrier available to a company, with its accounts and services.
 */
final class Carrier extends Data
{
    public int $id;
    public ?string $name;
    public ?string $abbreviation;

    public static function fromArray(array $attributes): static
    {
        $c = new self($attributes);
        $c->id = (int) ($attributes['id'] ?? 0);
        $c->name = $attributes['displayName'] ?? $attributes['name'] ?? null;
        $c->abbreviation = $attributes['abbreviation'] ?? null;

        return $c;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function accounts(): array
    {
        return $this->attributes['carrierAccounts'] ?? [];
    }
}
