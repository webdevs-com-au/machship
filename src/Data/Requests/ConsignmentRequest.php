<?php

namespace WebdevsComAu\MachShip\Data\Requests;

use DateTimeInterface;
use InvalidArgumentException;
use WebdevsComAu\MachShip\Data\Responses\Route;
use WebdevsComAu\MachShip\Enums\RouteSelection;
use WebdevsComAu\MachShip\Support\Dates;

/**
 * Fluent builder for a MachShip consignment. The same object can be sent to
 * the routes endpoint to get prices, then to createConsignment.
 *
 *     $request = ConsignmentRequest::make()
 *         ->from(Address::make('Warehouse', '1 Main St', 'SYDNEY', '2000'))
 *         ->to(Address::make('Jane Citizen', '5 High St', 'MELBOURNE', '3000'))
 *         ->item(Item::carton('Widgets', weight: 5, length: 30, width: 20, height: 15))
 *         ->reference('ORDER-1001');
 */
final class ConsignmentRequest
{
    public ?int $companyId = null;
    public ?Address $from = null;
    public ?Address $to = null;

    /** @var array<int, Item> */
    public array $items = [];

    public ?string $despatchDateTimeLocal = null;
    public ?string $despatchDateTimeUtc = null;
    public ?string $customerReference = null;
    public ?string $customerReference2 = null;
    public ?string $customerReference3 = null;
    public ?string $specialInstructions = null;

    public ?int $carrierId = null;
    public ?int $carrierServiceId = null;
    public ?int $subServiceId = null;
    public ?int $carrierAccountId = null;
    public ?int $companyCarrierAccountId = null;
    public ?RouteSelection $defaultRouteSelection = null;

    /** @var array<int, int> */
    public array $questionIds = [];

    /** @var array<int, int> */
    public array $electiveSurchargeIds = [];

    public ?bool $sendingTrackingEmail = null;
    public ?bool $dgsDeclaration = null;
    public ?string $receiverAccountCode = null;
    public ?string $staffMemberName = null;
    public ?string $printerToken = null;

    /** @var array<string, mixed> */
    public array $customFields = [];

    /**
     * Raw fields merged into the payload last, for anything not modelled here.
     *
     * @var array<string, mixed>
     */
    public array $extra = [];

    public static function make(): self
    {
        return new self();
    }

    /**
     * Build from a plain array, e.g. validated request input.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $request = new self();

        if (isset($data['from'])) {
            $request->from($data['from'] instanceof Address ? $data['from'] : Address::fromArray($data['from']));
        }

        if (isset($data['to'])) {
            $request->to($data['to'] instanceof Address ? $data['to'] : Address::fromArray($data['to']));
        }

        foreach ($data['items'] ?? [] as $item) {
            $request->item($item instanceof Item ? $item : Item::fromArray($item));
        }

        unset($data['from'], $data['to'], $data['items']);

        foreach ($data as $key => $value) {
            if ($key === 'defaultRouteSelection' && ! $value instanceof RouteSelection && $value !== null) {
                $value = is_string($value) ? constant(RouteSelection::class.'::'.ucfirst($value)) : RouteSelection::from($value);
            }

            if (in_array($key, ['despatchDateTimeLocal', 'despatchDateTimeUtc'], true)) {
                $key === 'despatchDateTimeLocal' ? $request->despatchAt($value) : $request->despatchAtUtc($value);

                continue;
            }

            if (property_exists($request, $key) && $key !== 'extra') {
                $request->{$key} = $value;
            } else {
                $request->extra[$key] = $value;
            }
        }

        return $request;
    }

    public function company(?int $companyId): self { $this->companyId = $companyId; return $this; }
    public function from(Address $from): self { $this->from = $from; return $this; }
    public function to(Address $to): self { $this->to = $to; return $this; }

    public function item(Item $item): self
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * @param  iterable<Item>  $items
     */
    public function items(iterable $items): self
    {
        foreach ($items as $item) {
            $this->item($item);
        }

        return $this;
    }

    /**
     * Despatch date-time in the pickup location's local time.
     */
    public function despatchAt(DateTimeInterface|string|null $local): self
    {
        $this->despatchDateTimeLocal = Dates::local($local);

        return $this;
    }

    public function despatchAtUtc(DateTimeInterface|string|null $utc): self
    {
        $this->despatchDateTimeUtc = Dates::utc($utc);

        return $this;
    }

    public function reference(?string $reference, ?string $reference2 = null): self
    {
        $this->customerReference = $reference;
        $this->customerReference2 = $reference2 ?? $this->customerReference2;

        return $this;
    }

    public function reference3(?string $reference): self { $this->customerReference3 = $reference; return $this; }
    public function instructions(?string $instructions): self { $this->specialInstructions = $instructions; return $this; }

    /**
     * Ship with a specific carrier / service / account. With only a carrier,
     * MachShip picks that carrier's cheapest service.
     */
    public function carrier(int $carrierId, ?int $carrierServiceId = null, ?int $carrierAccountId = null, ?int $companyCarrierAccountId = null): self
    {
        $this->carrierId = $carrierId;
        $this->carrierServiceId = $carrierServiceId;
        $this->carrierAccountId = $carrierAccountId;
        $this->companyCarrierAccountId = $companyCarrierAccountId;

        return $this;
    }

    public function subService(?int $subServiceId): self { $this->subServiceId = $subServiceId; return $this; }

    /**
     * Lock the consignment to a route returned from MachShip::routes()->get().
     */
    public function useRoute(Route $route): self
    {
        $this->carrier($route->carrierId, $route->carrierServiceId, $route->carrierAccountId, $route->companyCarrierAccountId);

        if ($this->despatchDateTimeLocal === null && $this->despatchDateTimeUtc === null && $route->despatchDateUtc) {
            $this->despatchDateTimeUtc = $route->despatchDateUtc;
        }

        return $this;
    }

    public function cheapest(): self { $this->defaultRouteSelection = RouteSelection::Cheapest; return $this; }
    public function fastest(): self { $this->defaultRouteSelection = RouteSelection::Fastest; return $this; }

    /**
     * Delivery questions, e.g. 13 residential, 7 tailgate, 14 authority to leave.
     *
     * @param  array<int, int>  $questionIds
     */
    public function questions(array $questionIds): self { $this->questionIds = array_values($questionIds); return $this; }

    /**
     * @param  array<int, int>  $surchargeIds
     */
    public function electiveSurcharges(array $surchargeIds): self { $this->electiveSurchargeIds = array_values($surchargeIds); return $this; }

    public function sendTrackingEmail(bool $send = true): self { $this->sendingTrackingEmail = $send; return $this; }
    public function declareDangerousGoods(bool $declare = true): self { $this->dgsDeclaration = $declare; return $this; }
    public function receiverAccount(?string $code): self { $this->receiverAccountCode = $code; return $this; }
    public function staffMember(?string $name): self { $this->staffMemberName = $name; return $this; }
    public function printer(?string $printerToken): self { $this->printerToken = $printerToken; return $this; }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function customFields(array $fields): self { $this->customFields = $fields; return $this; }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function with(array $fields): self { $this->extra = array_merge($this->extra, $fields); return $this; }

    /**
     * Complex items are needed for dangerous goods (and give access to
     * nested item data); MachShip uses a separate endpoint for them.
     */
    public function requiresComplexItems(): bool
    {
        foreach ($this->items as $item) {
            if ($item->hasDangerousGoods()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Payload for POST /apiv2/consignments/createConsignment(withComplexItems).
     *
     * @return array<string, mixed>
     */
    public function toPayload(?bool $complex = null): array
    {
        $this->validate();
        $complex ??= $this->requiresComplexItems();

        if ($complex && $this->requiresComplexItems() && $this->dgsDeclaration === null) {
            $this->dgsDeclaration = true;
        }

        $payload = [
            'companyId' => $this->companyId,
            'despatchDateTimeUtc' => $this->despatchDateTimeUtc,
            'despatchDateTimeLocal' => $this->despatchDateTimeLocal,
            'customerReference' => $this->customerReference,
            'customerReference2' => $this->customerReference2,
            'customerReference3' => $this->customerReference3,
            'carrierId' => $this->carrierId,
            'carrierServiceId' => $this->carrierServiceId,
            'subServiceId' => $this->subServiceId,
            'carrierAccountId' => $this->carrierAccountId,
            'companyCarrierAccountId' => $this->companyCarrierAccountId,
            'defaultRouteSelection' => $this->carrierId === null
                ? ($this->defaultRouteSelection ?? RouteSelection::Cheapest)->value
                : $this->defaultRouteSelection?->value,
            ...$this->from->toPayload('from'),
            ...$this->to->toPayload('to'),
            'specialInstructions' => $this->specialInstructions,
            'questionIds' => $this->questionIds ?: null,
            'electiveSurchargeIds' => $this->electiveSurchargeIds ?: null,
            'receiverAccountCode' => $this->receiverAccountCode,
            'staffMemberName' => $this->staffMemberName,
            'sendingTrackingEmail' => $this->sendingTrackingEmail,
            'dgsDeclaration' => $this->dgsDeclaration,
            'printerToken' => $this->printerToken,
            'customFields' => $this->customFields ?: null,
            'items' => array_map(
                fn (Item $item) => $complex ? $item->toComplexPayload() : $item->toPayload(),
                $this->items,
            ),
        ];

        return array_merge(array_filter($payload, fn ($value) => $value !== null), $this->extra);
    }

    /**
     * Payload for POST /apiv2/routes/returnroutes(withcomplexitems).
     *
     * @return array<string, mixed>
     */
    public function toRoutePayload(?bool $complex = null): array
    {
        $this->validate();
        $complex ??= $this->requiresComplexItems();

        $payload = [
            'companyId' => $this->companyId,
            'despatchDateTimeUtc' => $this->despatchDateTimeUtc,
            'despatchDateTimeLocal' => $this->despatchDateTimeLocal,
            'customerReference' => $this->customerReference,
            'customerReference2' => $this->customerReference2,
            'carrierId' => null,
            'carrierIds' => $this->carrierId !== null ? [$this->carrierId] : null,
            'carrierServiceId' => $this->carrierServiceId,
            'carrierAccountId' => $this->carrierAccountId,
            'companyCarrierAccountId' => $this->companyCarrierAccountId,
            ...$this->from->toRoutePayload('from'),
            ...$this->to->toRoutePayload('to'),
            'questionIds' => $this->questionIds ?: null,
            'items' => array_map(
                fn (Item $item) => $complex ? $item->toComplexPayload() : $item->toPayload(),
                $this->items,
            ),
        ];

        return array_filter($payload, fn ($value) => $value !== null);
    }

    private function validate(): void
    {
        if ($this->from === null || $this->to === null) {
            throw new InvalidArgumentException('A MachShip consignment needs both a from() and a to() address.');
        }

        if ($this->items === []) {
            throw new InvalidArgumentException('A MachShip consignment needs at least one item.');
        }
    }
}
