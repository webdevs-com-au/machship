<?php

namespace WebdevsComAu\MachShip\Data\Responses;

final class Company extends Data
{
    public int $id;
    public ?string $name;
    public ?string $accountCode;
    public ?int $parentCompanyId;

    public static function fromArray(array $attributes): static
    {
        $c = new self($attributes);
        $c->id = (int) ($attributes['id'] ?? 0);
        $c->name = $attributes['displayName'] ?? $attributes['name'] ?? null;
        $c->accountCode = $attributes['accountCode'] ?? null;
        $c->parentCompanyId = self::int($attributes['parentCompanyId'] ?? null);

        return $c;
    }
}
