<?php

namespace WebdevsComAu\MachShip\Data\Requests;

/**
 * A dangerous goods line attached to an item (CreateConsignmentItemDgItemV2).
 *
 * Integer codes follow the MachShip enums:
 *  - packingGroup: 1 = I, 2 = II, 3 = III, 4 = None
 *  - containerType: 1 Drum, 2 Carton, 3 IBC, 4 Pail, 5 DemountableTank, 6 Bottle,
 *    7 PressureDrums, 8 Tubes, 9 MEGCs, 10 Aerosol, 11 Bag
 *  - dgClassType: 2 FlammableGas, 3 OtherGas, 4 ToxicGas, 5 FlammableLiquid,
 *    6 FlammableSolid, 7 SpontaneouslyCombustible, 8 DangerousWhenWet,
 *    9 OxidizingAgent, 10 OrganicPeroxide, 11 ToxicSubstance, 12 InfectiousSubstance,
 *    13 Radioactive, 14 Corrosive, 15 Miscellaneous, 16+ explosives
 */
final class DangerousGood
{
    /**
     * @param  array<string, mixed>  $extra  any other CreateConsignmentItemDgItemV2 fields
     */
    public function __construct(
        public int $unNumber,
        public int $dgClassType,
        public int $packingGroup,
        public int $containerType,
        public float|int $aggregateQuantity,
        public int $numberOfContainers = 1,
        public bool $isAggregateQuantityWeight = false,
        public ?string $properShippingName = null,
        public bool $isMarinePollutant = false,
        public bool $isTemperatureControlled = false,
        public array $extra = [],
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $known = ['unNumber', 'dgClassType', 'packingGroup', 'containerType', 'aggregateQuantity', 'numberOfContainers',
            'isAggregateQuantityWeight', 'properShippingName', 'isMarinePollutant', 'isTemperatureControlled'];

        return new self(
            unNumber: (int) $data['unNumber'],
            dgClassType: (int) $data['dgClassType'],
            packingGroup: (int) $data['packingGroup'],
            containerType: (int) $data['containerType'],
            aggregateQuantity: $data['aggregateQuantity'],
            numberOfContainers: (int) ($data['numberOfContainers'] ?? 1),
            isAggregateQuantityWeight: (bool) ($data['isAggregateQuantityWeight'] ?? false),
            properShippingName: $data['properShippingName'] ?? null,
            isMarinePollutant: (bool) ($data['isMarinePollutant'] ?? false),
            isTemperatureControlled: (bool) ($data['isTemperatureControlled'] ?? false),
            extra: array_diff_key($data, array_flip($known)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return array_filter([
            'unNumber' => $this->unNumber,
            'dgClassType' => $this->dgClassType,
            'packingGroup' => $this->packingGroup,
            'containerType' => $this->containerType,
            'aggregateQuantity' => $this->aggregateQuantity,
            'isAggregateQuantityWeight' => $this->isAggregateQuantityWeight,
            'numberOfContainers' => $this->numberOfContainers,
            'isMarinePollutant' => $this->isMarinePollutant,
            'isTemperatureControlled' => $this->isTemperatureControlled,
            'properShippingName' => $this->properShippingName,
        ], fn ($value) => $value !== null) + $this->extra;
    }
}
