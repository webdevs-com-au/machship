<?php

namespace WebdevsComAu\MachShip\Data\Requests;

use WebdevsComAu\MachShip\Enums\ItemType;

/**
 * A line of freight. Dimensions are in centimetres, weight in kilograms
 * (per item, not total).
 */
final class Item
{
    /** @var array<int, DangerousGood> */
    public array $dangerousGoods = [];

    /** @var array<string, mixed>|null */
    public ?array $customFields = null;

    public function __construct(
        public ItemType|int|null $itemType = ItemType::Carton,
        public ?string $name = null,
        public ?int $quantity = 1,
        public float|int|null $weight = null,
        public float|int|null $length = null,
        public float|int|null $width = null,
        public float|int|null $height = null,
        public ?string $sku = null,
        public ?int $companyItemId = null,
        public float|int|null $palletSpaces = null,
    ) {
    }

    public static function make(
        string $name,
        float|int $weight,
        float|int $length,
        float|int $width,
        float|int $height,
        int $quantity = 1,
        ItemType|int $type = ItemType::Carton,
    ): self {
        return new self($type, $name, $quantity, $weight, $length, $width, $height);
    }

    public static function carton(string $name, float|int $weight, float|int $length, float|int $width, float|int $height, int $quantity = 1): self
    {
        return self::make($name, $weight, $length, $width, $height, $quantity, ItemType::Carton);
    }

    public static function pallet(string $name, float|int $weight, float|int $length = 120, float|int $width = 120, float|int $height = 120, int $quantity = 1): self
    {
        return self::make($name, $weight, $length, $width, $height, $quantity, ItemType::Pallet);
    }

    public static function satchel(string $name, float|int $weight, float|int $length, float|int $width, float|int $height, int $quantity = 1): self
    {
        return self::make($name, $weight, $length, $width, $height, $quantity, ItemType::Satchel);
    }

    /**
     * Reference an item saved in MachShip, optionally overriding the quantity.
     */
    public static function saved(int $companyItemId, int $quantity = 1): self
    {
        return new self(itemType: null, quantity: $quantity, companyItemId: $companyItemId);
    }

    /**
     * Reference an item saved in MachShip by SKU.
     */
    public static function bySku(string $sku, int $quantity = 1): self
    {
        return new self(itemType: null, quantity: $quantity, sku: $sku);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $type = $data['itemType'] ?? ItemType::Carton;

        $item = new self(
            itemType: is_string($type) ? constant(ItemType::class.'::'.$type) : $type,
            name: $data['name'] ?? null,
            quantity: $data['quantity'] ?? 1,
            weight: $data['weight'] ?? null,
            length: $data['length'] ?? null,
            width: $data['width'] ?? null,
            height: $data['height'] ?? null,
            sku: $data['sku'] ?? null,
            companyItemId: $data['companyItemId'] ?? null,
            palletSpaces: $data['palletSpaces'] ?? null,
        );

        foreach ($data['dangerousGoods'] ?? [] as $dg) {
            $item->withDangerousGood($dg instanceof DangerousGood ? $dg : DangerousGood::fromArray($dg));
        }

        return $item;
    }

    public function quantity(int $quantity): self { $this->quantity = $quantity; return $this; }
    public function sku(?string $sku): self { $this->sku = $sku; return $this; }
    public function palletSpaces(float|int|null $spaces): self { $this->palletSpaces = $spaces; return $this; }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function customFields(array $fields): self
    {
        $this->customFields = $fields;

        return $this;
    }

    public function withDangerousGood(DangerousGood $dangerousGood): self
    {
        $this->dangerousGoods[] = $dangerousGood;

        return $this;
    }

    public function hasDangerousGoods(): bool
    {
        return $this->dangerousGoods !== [];
    }

    /**
     * Payload for createConsignment / returnroutes (flat dimensions).
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return array_filter([
            'companyItemId' => $this->companyItemId,
            'itemType' => $this->itemTypeValue(),
            'name' => $this->name,
            'sku' => $this->sku,
            'quantity' => $this->quantity,
            'height' => $this->height,
            'weight' => $this->weight,
            'length' => $this->length,
            'width' => $this->width,
            'palletSpaces' => $this->palletSpaces,
            'customFields' => $this->customFields,
        ], fn ($value) => $value !== null);
    }

    /**
     * Payload for the *WithComplexItems endpoints (dimensions nested in
     * standardItem, plus dangerous goods lines).
     *
     * @return array<string, mixed>
     */
    public function toComplexPayload(): array
    {
        $standard = array_filter([
            'height' => $this->height,
            'weight' => $this->weight,
            'length' => $this->length,
            'width' => $this->width,
        ], fn ($value) => $value !== null);

        return array_filter([
            'companyItemId' => $this->companyItemId,
            'itemType' => $this->itemTypeValue(),
            'name' => $this->name,
            'sku' => $this->sku,
            'quantity' => $this->quantity,
            'palletSpaces' => $this->palletSpaces,
            'standardItem' => $standard ?: null,
            'consignmentItemDgItems' => $this->dangerousGoods
                ? array_map(fn (DangerousGood $dg) => $dg->toPayload(), $this->dangerousGoods)
                : null,
            'customFields' => $this->customFields,
        ], fn ($value) => $value !== null);
    }

    private function itemTypeValue(): ?int
    {
        return $this->itemType instanceof ItemType ? $this->itemType->value : $this->itemType;
    }
}
