<?php

namespace WebdevsComAu\MachShip\Tests\Feature;

use InvalidArgumentException;
use WebdevsComAu\MachShip\Data\Requests\Address;
use WebdevsComAu\MachShip\Data\Requests\ConsignmentRequest;
use WebdevsComAu\MachShip\Data\Requests\DangerousGood;
use WebdevsComAu\MachShip\Data\Requests\Item;
use WebdevsComAu\MachShip\Enums\ItemType;
use WebdevsComAu\MachShip\Tests\TestCase;

class ConsignmentRequestTest extends TestCase
{
    private function request(): ConsignmentRequest
    {
        return ConsignmentRequest::make()
            ->from(Address::make('Warehouse', '1 Main St', 'SYDNEY', '2000', contact: 'Sam', phone: '0290000000'))
            ->to(Address::make('Jane Citizen', '5 High St', 'MELBOURNE', '3000', email: 'jane@example.com'))
            ->item(Item::carton('Widgets', weight: 5, length: 30, width: 20, height: 15, quantity: 2))
            ->reference('ORDER-1001')
            ->despatchAt(new \DateTimeImmutable('2026-11-24 09:00:00'))
            ->questions([13]);
    }

    public function test_it_builds_a_create_consignment_payload(): void
    {
        $payload = $this->request()->toPayload();

        $this->assertSame('Warehouse', $payload['fromName']);
        $this->assertSame('Sam', $payload['fromContact']);
        $this->assertSame(['suburb' => 'SYDNEY', 'postcode' => '2000'], $payload['fromLocation']);
        $this->assertSame(['suburb' => 'MELBOURNE', 'postcode' => '3000'], $payload['toLocation']);
        $this->assertSame('jane@example.com', $payload['toEmail']);
        $this->assertSame('ORDER-1001', $payload['customerReference']);
        $this->assertSame('2026-11-24T09:00:00', $payload['despatchDateTimeLocal']);
        $this->assertSame([13], $payload['questionIds']);
        $this->assertSame(0, $payload['defaultRouteSelection'], 'cheapest when no carrier given');
        $this->assertSame([
            'itemType' => ItemType::Carton->value,
            'name' => 'Widgets',
            'quantity' => 2,
            'height' => 15,
            'weight' => 5,
            'length' => 30,
            'width' => 20,
        ], $payload['items'][0]);
        $this->assertArrayNotHasKey('carrierId', $payload);
    }

    public function test_saved_company_location_omits_suburb_and_postcode(): void
    {
        $payload = $this->request()->from(Address::companyLocation(77))->toPayload();

        $this->assertSame(77, $payload['fromCompanyLocationId']);
        $this->assertArrayNotHasKey('fromLocation', $payload);
        $this->assertArrayNotHasKey('fromName', $payload);
    }

    public function test_specifying_a_carrier_drops_default_route_selection(): void
    {
        $payload = $this->request()->carrier(11, 101, 8001, 222)->toPayload();

        $this->assertSame(11, $payload['carrierId']);
        $this->assertSame(101, $payload['carrierServiceId']);
        $this->assertSame(8001, $payload['carrierAccountId']);
        $this->assertSame(222, $payload['companyCarrierAccountId']);
        $this->assertArrayNotHasKey('defaultRouteSelection', $payload);
    }

    public function test_dangerous_goods_use_complex_item_shape(): void
    {
        $request = $this->request();
        $request->items[0]->withDangerousGood(new DangerousGood(
            unNumber: 1263, dgClassType: 5, packingGroup: 3, containerType: 2, aggregateQuantity: 20,
        ));

        $this->assertTrue($request->requiresComplexItems());

        $payload = $request->toPayload();

        $this->assertTrue($payload['dgsDeclaration']);
        $this->assertSame(['height' => 15, 'weight' => 5, 'length' => 30, 'width' => 20], $payload['items'][0]['standardItem']);
        $this->assertSame(1263, $payload['items'][0]['consignmentItemDgItems'][0]['unNumber']);
        $this->assertArrayNotHasKey('weight', $payload['items'][0]);
    }

    public function test_from_array(): void
    {
        $request = ConsignmentRequest::fromArray([
            'from' => ['name' => 'A', 'suburb' => 'SYDNEY', 'postcode' => '2000'],
            'to' => ['companyLocationId' => 5],
            'items' => [['itemType' => 'Pallet', 'name' => 'Pallet', 'weight' => 300, 'length' => 120, 'width' => 120, 'height' => 100]],
            'customerReference' => 'X1',
            'defaultRouteSelection' => 'fastest',
            'someNewField' => true,
        ]);

        $payload = $request->toPayload();

        $this->assertSame(3, $payload['items'][0]['itemType']);
        $this->assertSame(1, $payload['defaultRouteSelection']);
        $this->assertSame(5, $payload['toCompanyLocationId']);
        $this->assertTrue($payload['someNewField']);
    }

    public function test_it_requires_addresses_and_items(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConsignmentRequest::make()->from(Address::make('A'))->toPayload();
    }
}
