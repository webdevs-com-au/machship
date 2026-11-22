<?php

namespace WebdevsComAu\MachShip\Data\Requests;

/**
 * A pickup (from) or delivery (to) party on a route or consignment.
 *
 * Identify the place in ONE of these ways:
 *  - suburb + postcode           Address::make(...)
 *  - a saved company location    Address::companyLocation($id)
 *  - a saved location code       Address::abbreviation('SYD-DC')
 *  - a MachShip location id      Address::make(...)->locationId($id)
 */
final class Address
{
    public ?string $name = null;
    public ?string $contact = null;
    public ?string $phone = null;
    public ?string $email = null;
    public ?string $addressLine1 = null;
    public ?string $addressLine2 = null;
    public ?string $suburb = null;
    public ?string $postcode = null;
    public ?int $locationId = null;
    public ?int $companyLocationId = null;
    public ?string $abbreviation = null;

    public static function make(
        ?string $name = null,
        ?string $addressLine1 = null,
        ?string $suburb = null,
        ?string $postcode = null,
        ?string $addressLine2 = null,
        ?string $contact = null,
        ?string $phone = null,
        ?string $email = null,
    ): self {
        $address = new self();
        $address->name = $name;
        $address->addressLine1 = $addressLine1;
        $address->addressLine2 = $addressLine2;
        $address->suburb = $suburb;
        $address->postcode = $postcode;
        $address->contact = $contact;
        $address->phone = $phone;
        $address->email = $email;

        return $address;
    }

    /**
     * Use a location saved against the company in MachShip. Address details
     * come from the saved location, so suburb/postcode are not sent.
     */
    public static function companyLocation(int $companyLocationId): self
    {
        $address = new self();
        $address->companyLocationId = $companyLocationId;

        return $address;
    }

    public static function abbreviation(string $abbreviation): self
    {
        $address = new self();
        $address->abbreviation = $abbreviation;

        return $address;
    }

    /**
     * @param  array<string, mixed>  $data  keys: name, contact, phone, email, addressLine1, addressLine2, suburb, postcode, locationId, companyLocationId, abbreviation
     */
    public static function fromArray(array $data): self
    {
        $address = new self();

        foreach ($data as $key => $value) {
            if (property_exists($address, $key)) {
                $address->{$key} = $value;
            }
        }

        return $address;
    }

    public function name(?string $name): self { $this->name = $name; return $this; }
    public function contact(?string $contact): self { $this->contact = $contact; return $this; }
    public function phone(?string $phone): self { $this->phone = $phone; return $this; }
    public function email(?string $email): self { $this->email = $email; return $this; }
    public function line1(?string $line): self { $this->addressLine1 = $line; return $this; }
    public function line2(?string $line): self { $this->addressLine2 = $line; return $this; }
    public function locationId(?int $id): self { $this->locationId = $id; return $this; }

    /**
     * Location fields only, as accepted by the routes endpoints.
     *
     * @return array<string, mixed>
     */
    public function toRoutePayload(string $prefix): array
    {
        return array_filter([
            $prefix.'CompanyLocationId' => $this->companyLocationId,
            $prefix.'Abbreviation' => $this->abbreviation,
            $prefix.'LocationId' => $this->locationId,
            $prefix.'Name' => $this->name,
            $prefix.'AddressLine1' => $this->addressLine1,
            $prefix.'AddressLine2' => $this->addressLine2,
            $prefix.'Location' => $this->locationPayload(),
        ], fn ($value) => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(string $prefix): array
    {
        return array_filter([
            ...$this->toRoutePayload($prefix),
            $prefix.'Contact' => $this->contact,
            $prefix.'Phone' => $this->phone,
            $prefix.'Email' => $this->email,
        ], fn ($value) => $value !== null);
    }

    /**
     * @return array{suburb: ?string, postcode: ?string}|null
     */
    private function locationPayload(): ?array
    {
        if ($this->usesSavedLocation() || $this->locationId !== null) {
            return null;
        }

        if ($this->suburb === null && $this->postcode === null) {
            return null;
        }

        return ['suburb' => $this->suburb, 'postcode' => $this->postcode];
    }

    private function usesSavedLocation(): bool
    {
        return $this->companyLocationId !== null || $this->abbreviation !== null;
    }
}
