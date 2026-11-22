<?php

namespace WebdevsComAu\MachShip\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use WebdevsComAu\MachShip\Data\Requests\Address;
use WebdevsComAu\MachShip\Data\Requests\ConsignmentRequest;
use WebdevsComAu\MachShip\Data\Requests\DangerousGood;
use WebdevsComAu\MachShip\Data\Requests\Item;
use WebdevsComAu\MachShip\Enums\TrackingStatus;
use WebdevsComAu\MachShip\Exceptions\AuthenticationException;
use WebdevsComAu\MachShip\Exceptions\ConfigurationException;
use WebdevsComAu\MachShip\Exceptions\RateLimitException;
use WebdevsComAu\MachShip\Exceptions\ValidationException;
use WebdevsComAu\MachShip\Facades\MachShip;
use WebdevsComAu\MachShip\Tests\TestCase;

class MachShipApiTest extends TestCase
{
    private const BASE = 'https://live.machship.com/apiv2/';

    private function request(): ConsignmentRequest
    {
        return ConsignmentRequest::make()
            ->from(Address::make('Warehouse', '1 Main St', 'SYDNEY', '2000'))
            ->to(Address::make('Jane Citizen', '5 High St', 'MELBOURNE', '3000'))
            ->item(Item::carton('Widgets', 5, 30, 20, 15, 2))
            ->reference('ORDER-1001');
    }

    public function test_ping_sends_token_header(): void
    {
        Http::fake([self::BASE.'authenticate/ping' => Http::response(['object' => true, 'errors' => null])]);

        $this->assertTrue(MachShip::ping());

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->header('token') === ['test-token']);
    }

    public function test_ping_command(): void
    {
        Http::fake([self::BASE.'authenticate/ping' => Http::response(['object' => true, 'errors' => null])]);

        $this->artisan('machship:ping')->assertSuccessful();
    }

    public function test_routes_are_returned_cheapest_first(): void
    {
        Http::fake([self::BASE.'routes/returnroutes' => Http::response($this->fixture('routes'))]);

        $routes = MachShip::routes()->get($this->request());

        $this->assertCount(2, $routes);
        $this->assertSame(11, $routes[0]->carrierId);
        $this->assertSame(18.2, $routes[0]->totalSellPrice);
        $this->assertSame('Road Express', $routes[0]->carrierServiceName);
        $this->assertSame(22, MachShip::routes()->fastest($this->request())->carrierId);

        Http::assertSent(function (Request $r) {
            return $r->url() === self::BASE.'routes/returnroutes'
                && $r['fromLocation'] === ['suburb' => 'SYDNEY', 'postcode' => '2000']
                && $r['items'][0]['quantity'] === 2
                && ! isset($r['fromContact']);
        });
    }

    public function test_quote_then_create_with_selected_route(): void
    {
        Http::fake([
            self::BASE.'routes/returnroutes' => Http::response($this->fixture('routes')),
            self::BASE.'consignments/createConsignment' => Http::response($this->fixture('create-consignment')),
        ]);

        $request = $this->request();
        $route = MachShip::routes()->cheapest($request);
        $consignment = MachShip::consignments()->create($request->useRoute($route));

        $this->assertSame(123456, $consignment->id);
        $this->assertSame('MS00123456', $consignment->consignmentNumber);
        $this->assertSame('ABC000123456', $consignment->carrierConsignmentId);
        $this->assertSame(TrackingStatus::Unmanifested, $consignment->status());
        $this->assertTrue($consignment->isUnmanifested());
        $this->assertTrue($consignment->isTest);
        $this->assertSame('Widgets', $consignment['items.0.name']);

        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'consignments/createConsignment'
            && $r['carrierId'] === 11
            && $r['carrierServiceId'] === 101
            && $r['companyCarrierAccountId'] === 222
            && $r['despatchDateTimeUtc'] === '2026-11-23T22:00:00Z'
            && $r['customerReference'] === 'ORDER-1001');
    }

    public function test_default_company_is_applied(): void
    {
        config()->set('machship.company_id', 999);
        $this->app->forgetInstance(\WebdevsComAu\MachShip\MachShip::class);
        MachShip::clearResolvedInstances();

        Http::fake([self::BASE.'*' => Http::response($this->fixture('create-consignment'))]);

        MachShip::consignments()->create($this->request());

        Http::assertSent(fn (Request $r) => $r['companyId'] === 999);
    }

    public function test_dangerous_goods_go_to_complex_endpoint(): void
    {
        Http::fake([self::BASE.'consignments/createConsignmentwithComplexItems' => Http::response($this->fixture('create-consignment'))]);

        $request = $this->request();
        $request->items[0]->withDangerousGood(new DangerousGood(1263, 5, 3, 2, 20));

        MachShip::consignments()->create($request);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'createConsignmentwithComplexItems')
            && $r['items'][0]['standardItem']['weight'] === 5);
    }

    public function test_validation_errors_throw(): void
    {
        Http::fake([self::BASE.'*' => Http::response([
            'object' => null,
            'errors' => [['validationType' => 'Error', 'memberNames' => ['FromLocation'], 'errorMessage' => 'From Location is invalid']],
        ])]);

        try {
            MachShip::consignments()->create($this->request());
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('From Location is invalid', $e->getMessage());
            $this->assertSame(['From Location is invalid'], $e->messages());
        }
    }

    public function test_warnings_do_not_throw(): void
    {
        Http::fake([self::BASE.'*' => Http::response([
            'object' => $this->fixture('create-consignment')['object'],
            'errors' => [['validationType' => 3, 'memberNames' => [], 'errorMessage' => 'Heads up']],
        ])]);

        $this->assertSame(123456, MachShip::consignments()->create($this->request())->id);
    }

    public function test_400_with_errors_throws_validation_exception(): void
    {
        Http::fake([self::BASE.'*' => Http::response([
            'object' => null,
            'errors' => [['validationType' => 1, 'errorMessage' => 'Item quantity is required and must be greater than 0']],
        ], 400)]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Item quantity is required');

        MachShip::consignments()->create($this->request());
    }

    public function test_401_throws_authentication_exception(): void
    {
        Http::fake([self::BASE.'*' => Http::response('', 401)]);

        $this->expectException(AuthenticationException::class);

        MachShip::ping();
    }

    public function test_429_throws_rate_limit_exception(): void
    {
        Http::fake([self::BASE.'*' => Http::response('', 429, ['Retry-After' => '30'])]);

        try {
            MachShip::consignments()->find(1);
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame(30, $e->retryAfter());
        }

        Http::assertSentCount(3); // GETs retry (2 retries by default)
    }

    public function test_posts_are_never_retried(): void
    {
        Http::fake([self::BASE.'*' => Http::response('', 503)]);

        try {
            MachShip::consignments()->create($this->request());
        } catch (\WebdevsComAu\MachShip\Exceptions\MachShipException) {
        }

        Http::assertSentCount(1);
    }

    public function test_missing_token(): void
    {
        $this->expectException(ConfigurationException::class);

        MachShip::client()->withToken('')->get('consignments/getConsignment', ['id' => 1]);
    }

    public function test_with_token_overrides_header(): void
    {
        Http::fake([self::BASE.'*' => Http::response(['object' => true, 'errors' => null])]);

        MachShip::withToken('tenant-token')->ping();

        Http::assertSent(fn (Request $r) => $r->header('token') === ['tenant-token']);
    }

    public function test_find_consignment_and_query_booleans(): void
    {
        Http::fake([self::BASE.'consignments/getConsignment*' => Http::response($this->fixture('create-consignment'))]);

        $consignment = MachShip::consignments()->find(123456, includeDeleted: true);

        $this->assertSame(123456, $consignment->id);
        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'consignments/getConsignment?id=123456&includeDeleted=true');
    }

    public function test_find_many_batches_by_ten(): void
    {
        Http::fake([self::BASE.'consignments/returnConsignments' => Http::response(['object' => [['id' => 1]], 'errors' => []])]);

        $result = MachShip::consignments()->findMany(range(1, 12));

        $this->assertCount(2, $result);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => $r['ids'] === range(1, 10));
    }

    public function test_delete_and_cancel(): void
    {
        Http::fake([self::BASE.'*' => Http::response(['object' => null, 'errors' => []])]);

        MachShip::consignments()->delete([123, 456]);
        MachShip::consignments()->cancel(789, 'Customer cancelled', '2026-11-23T10:00:00');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'deleteUnmanifestedConsignments') && $r->data() === [123, 456]);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'updateConsignmentStatuses') && $r->data() === [[
            'consignmentId' => 789,
            'status' => 10,
            'statusDateTimeLocal' => '2026-11-23T10:00:00',
            'extraInformation' => 'Customer cancelled',
        ]]);
    }

    public function test_download_label_for_consignment(): void
    {
        $pdf = "%PDF-1.4 fake label";

        Http::fake([self::BASE.'labels/getItemPdfFileInfo*' => Http::response([
            'object' => ['fileName' => 'MS00123456.pdf', 'contentType' => 'application/pdf', 'content' => base64_encode($pdf)],
            'errors' => [],
        ])]);

        $label = MachShip::labels()->forConsignment(123456);

        $this->assertSame($pdf, $label->contents);
        $this->assertSame('MS00123456.pdf', $label->fileName);
        $this->assertTrue($label->isPdf());
        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'labels/getItemPdfFileInfo?consignmentId=123456&printA4=false');

        Storage::fake('local');
        $path = $label->store(disk: 'local');
        $this->assertSame('machship/labels/MS00123456.pdf', $path);
        Storage::disk('local')->assertExists($path);

        $response = $label->download();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=MS00123456.pdf', $response->headers->get('Content-Disposition'));
        $this->assertSame($pdf, $response->getContent());
    }

    public function test_download_labels_for_many_consignments(): void
    {
        Http::fake([self::BASE.'labels/getItemPdfsForConsignments*' => Http::response('%PDF merged', 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="labels.pdf"',
        ])]);

        $doc = MachShip::labels()->forConsignments([1, 2]);

        $this->assertSame('%PDF merged', $doc->contents);
        $this->assertSame('labels.pdf', $doc->fileName);
        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'labels/getItemPdfsForConsignments?consignmentIds=1&consignmentIds=2&printA4=false&singlePdf=true');
    }

    public function test_file_endpoint_returning_json_errors_throws(): void
    {
        Http::fake([self::BASE.'*' => Http::response([
            'object' => null,
            'errors' => [['validationType' => 'Error', 'errorMessage' => 'Consignment not found']],
        ])]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Consignment not found');

        MachShip::labels()->forConsignments([1]);
    }

    public function test_manifest_groups_then_books(): void
    {
        Http::fake([
            self::BASE.'manifests/groupConsignmentsForManifest' => Http::response(['object' => [
                ['consignmentIds' => [1, 2], 'companyId' => 5, 'carrierName' => 'Road Freight A', 'pickupAlreadyBooked' => false],
            ], 'errors' => []]),
            self::BASE.'manifests/manifest' => Http::response(['object' => [
                ['id' => 42, 'consignmentIds' => [1, 2], 'companyId' => 5, 'carrierReference' => 'PU123', 'bookingSuccessful' => true, 'errorMessage' => null],
            ], 'errors' => []]),
        ]);

        $manifests = MachShip::manifests()->manifest([1, 2], pickupAt: '2026-11-24T13:00:00', closingAt: '2026-11-24T16:00:00');

        $this->assertSame(42, $manifests[0]->id);
        $this->assertTrue($manifests[0]->bookingSuccessful);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'manifests/manifest')
            && $r->data()[0]['pickupDateTime'] === '2026-11-24T13:00:00'
            && $r->data()[0]['consignmentIds'] === [1, 2]);
    }

    public function test_location_search(): void
    {
        Http::fake([self::BASE.'locations/returnLocationsWithSearchOptions*' => Http::response(['object' => [
            ['id' => 1, 'suburb' => 'SYDNEY', 'postcode' => '2000', 'state' => ['code' => 'NSW'], 'country' => ['code2' => 'AU']],
        ], 'errors' => []])]);

        $locations = MachShip::locations()->search('Sydney 2000');

        $this->assertSame('NSW', $locations[0]->state);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '?s=Sydney%202000') && $r['retrievalSize'] === 10);
    }

    public function test_companies_carriers_and_locations(): void
    {
        Http::fake([
            self::BASE.'companies/getAll*' => Http::response(['object' => [['id' => 5, 'name' => 'Acme', 'displayName' => 'Acme Pty Ltd']], 'errors' => []]),
            self::BASE.'companies/getAvailableCarriersAccountsAndServices*' => Http::response(['object' => [['id' => 11, 'name' => 'Road Freight A', 'carrierAccounts' => [['id' => 8001]]]], 'errors' => []]),
            self::BASE.'companyLocations/getAll*' => Http::response(['object' => [['id' => 77, 'name' => 'DC', 'location' => ['suburb' => 'SYDNEY', 'postcode' => '2000']]], 'errors' => [], 'totalItems' => 1]),
        ]);

        $this->assertSame('Acme Pty Ltd', MachShip::companies()->all()->first()->name);
        $this->assertSame([['id' => 8001]], MachShip::companies()->carriers(5)->first()->accounts());
        $this->assertSame('SYDNEY', MachShip::companies()->locations(5)->first()->suburb);

        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'companies/getAvailableCarriersAccountsAndServices?companyId=5');
    }
}
