# Laravel MachShip

A Laravel package for the [MachShip](https://developers.live.machship.com/api/api-overview) freight API (v2). It covers what you need to ship: look up suburbs, get prices, create consignments, download labels, manifest, and track.

- Fluent `ConsignmentRequest` builder. Build a request once, use it to get prices, then create the consignment from it.
- Dangerous goods requests go to MachShip's `…WithComplexItems` endpoints automatically.
- Labels come back as a `Document` you can store, save, or return from a controller.
- Typed exceptions for validation errors, auth failures, and rate limits.
- Only GET requests are retried, so a create call is never sent twice (no duplicate consignments).
- You can switch tokens per call for multi-tenant apps.

Requires PHP 8.2+ and Laravel 12 or 13.

## Installation

```bash
composer require webdevs-com-au/machship
php artisan vendor:publish --tag=machship-config   # optional
```

```dotenv
MACHSHIP_TOKEN=your-api-token
# MACHSHIP_COMPANY_ID=12345        # optional default company
# MACHSHIP_LABEL_DISK=s3           # optional disk for Document::store()
```

```bash
php artisan machship:ping           # checks the token
```

> **Testing:** MachShip has no sandbox host. Create your API token on a MachShip user that is in **test mode**. Consignments made with that token are flagged `isTest` and are never sent to a carrier.

## Quick start

```php
use WebdevsComAu\MachShip\Facades\MachShip;
use WebdevsComAu\MachShip\Data\Requests\{Address, ConsignmentRequest, Item};

$request = ConsignmentRequest::make()
    ->from(Address::make('Acme Warehouse', '1 Main St', 'SYDNEY', '2000', contact: 'Sam', phone: '0290000000'))
    ->to(Address::make('Jane Citizen', '5 High St', 'MELBOURNE', '3000', email: 'jane@example.com'))
    ->item(Item::carton('Widgets', weight: 5, length: 30, width: 20, height: 15, quantity: 2))
    ->reference('ORDER-1001')
    ->despatchAt(now()->addDay()->setTime(9, 0));

// 1. Price it: returns a collection of routes, cheapest first
$routes = MachShip::routes()->get($request);
$route  = $routes->first();   // or MachShip::routes()->cheapest($request) / ->fastest($request)

echo "{$route->carrierName} {$route->carrierServiceName}: \${$route->totalSellPrice}, ETA {$route->etaLocal}";

// 2. Book it on that route
$consignment = MachShip::consignments()->create($request->useRoute($route));

$consignment->id;                    // MachShip ID: store this
$consignment->consignmentNumber;     // "MS00123456"
$consignment->carrierConsignmentId;  // carrier connote / tracking number

// 3. Labels
$label = MachShip::labels()->forConsignment($consignment->id);
$path  = $label->store();            // storage: machship/labels/MS00123456.pdf
```

If you skip step 1, MachShip picks the route itself: cheapest by default, or call `->fastest()` on the request, or `->carrier($carrierId, $serviceId, $accountId)` to set one.

## Addresses

```php
Address::make('Name', 'Line 1', 'SUBURB', '2000', addressLine2: 'Unit 4', contact: 'Sam', phone: '...', email: '...');
Address::companyLocation(77);      // a location saved in MachShip (see companies()->locations())
Address::abbreviation('SYD-DC');   // saved location by code
```

Check an address before quoting:

```php
MachShip::locations()->search('Sydney 2000');   // Collection<Location>
MachShip::locations()->find('SYDNEY', '2000');  // ?Location
```

## Items

Dimensions are in **cm** and weight is in **kg per item**.

```php
Item::carton('Box', 5, 30, 20, 15, quantity: 2);
Item::pallet('Pallet', weight: 300, height: 140);        // 120×120 by default
Item::make('Tyre', 12, 70, 70, 25, 4, ItemType::PassengerTyre);
Item::saved(companyItemId: 999, quantity: 3);             // saved item
Item::bySku('SKU-123', quantity: 3);
```

### Dangerous goods

```php
use WebdevsComAu\MachShip\Data\Requests\DangerousGood;

$item = Item::carton('Paint', 20, 40, 30, 30)
    ->withDangerousGood(new DangerousGood(
        unNumber: 1263, dgClassType: 5, packingGroup: 3, containerType: 2, aggregateQuantity: 20,
    ));

MachShip::consignments()->create($request->item($item));
// → sent to createConsignmentwithComplexItems with dgsDeclaration: true
```

## Other request options

```php
$request
    ->company(12345)
    ->carrier(carrierId: 11, carrierServiceId: 101, carrierAccountId: 8001)
    ->questions([13, 7])                // e.g. 13 residential, 7 tailgate
    ->instructions('Leave at reception')
    ->reference('PO-1', 'INV-9')->reference3('Batch 4')
    ->sendTrackingEmail()
    ->receiverAccount('ACC123')
    ->customFields(['po_number' => 'PO-1'])
    ->with(['isReturn' => true]);       // any other CreateConsignmentV2 field
```

You can also build a request from an array, for example validated input:

```php
ConsignmentRequest::fromArray([
    'from'  => ['name' => 'Warehouse', 'addressLine1' => '1 Main St', 'suburb' => 'SYDNEY', 'postcode' => '2000'],
    'to'    => ['companyLocationId' => 77],
    'items' => [['itemType' => 'Pallet', 'name' => 'Stock', 'weight' => 300, 'length' => 120, 'width' => 120, 'height' => 100]],
    'customerReference' => 'ORDER-1001',
]);
```

## Consignments

```php
$c = MachShip::consignments()->find($id);
$c->status();               // TrackingStatus enum, e.g. TrackingStatus::InTransit
$c->isManifested();
$c['statusHistory'];        // every API field is available via array access / get('dot.path')

MachShip::consignments()->findMany([1, 2, 3]);                 // sent in batches of 10
MachShip::consignments()->findByReference(['ORDER-1001']);
MachShip::consignments()->findByCarrierConsignmentId(['ABC000123']);
MachShip::consignments()->unmanifested();
MachShip::consignments()->recentlyChanged(now()->subHour());   // for syncing statuses

MachShip::consignments()->delete([$id]);   // only works before manifesting
MachShip::consignments()->cancel($id, 'Customer cancelled');  // after manifesting; this does NOT notify the carrier
MachShip::consignments()->attachments($id); // PODs etc.
```

## Labels and documents

Every method returns a `WebdevsComAu\MachShip\Data\Responses\Document`.

```php
MachShip::labels()->forConsignment($id, printA4: true);
MachShip::labels()->forConsignments([$a, $b]);                    // one merged PDF (max 40)
MachShip::labels()->forConsignments([$a, $b], singlePdf: false);  // ZIP
MachShip::labels()->consignmentNote($id);
MachShip::labels()->manifest($manifestId);
MachShip::labels()->dangerousGoodsDocument($id);
MachShip::labels()->attachment($attachmentId);
MachShip::labels()->sendToPrinter([$id], printerId: 5);
```

```php
$doc->store('labels', 's3');        // returns the path
$doc->saveTo('/tmp/label.pdf');
$doc->contents; $doc->fileName; $doc->base64();

// in a controller
return MachShip::labels()->forConsignment($id);              // shown inline
return MachShip::labels()->forConsignment($id)->download();  // download
```

## Manifesting

Manifesting sends the consignments to the carrier and books the pickup.

```php
$manifests = MachShip::manifests()->manifest(
    [$id1, $id2],
    pickupAt: now()->setTime(13, 0),
    closingAt: now()->setTime(16, 0),
);

foreach ($manifests as $m) {
    $m->bookingSuccessful ? $m->carrierReference : $m->errorMessage;
}
```

You can also do it in two steps, which lets you change the groups before booking: `$groups = manifests()->group($ids)`, then `manifests()->book($groups)`.

## Companies and carriers

```php
MachShip::companies()->all();
MachShip::companies()->carriers($companyId);   // carriers with their accounts and services
MachShip::companies()->locations($companyId);  // saved addresses
```

## Multiple tenants and companies

```php
MachShip::withToken($tenant->machship_token)->consignments()->create($request);
MachShip::forCompany(12345)->routes()->get($request);
```

## Errors

| Exception | When |
|---|---|
| `ValidationException` | MachShip returned error results, e.g. "From Location is invalid" or "No prices were found". Call `->messages()` or `->errors()` for details. |
| `AuthenticationException` | HTTP 401/403: the token is bad or lacks permission. |
| `RateLimitException` | HTTP 429. Call `->retryAfter()` for the wait time. |
| `ConfigurationException` | No token is configured. |
| `MachShipException` | Base class: connection failures and other HTTP errors. |

All of them extend `MachShipException`. Info and warning results that come back with a successful response do not throw.

## Raw access

```php
MachShip::client()->get('consignments/getActive', ['companyId' => 1]);
MachShip::client()->post('routes/returnmultipleroutes', [...]);
```

## Testing your app

The package uses Laravel's HTTP client, so `Http::fake()` works:

```php
Http::fake([
    'live.machship.com/apiv2/consignments/createConsignment' => Http::response([
        'object' => ['id' => 1, 'consignmentNumber' => 'MS00000001'], 'errors' => [],
    ]),
]);
```

## Package development

```bash
composer install && composer test
# no local PHP:
docker run --rm -v "$PWD":/app -w /app composer:2 sh -c "composer install && vendor/bin/phpunit"
```

## CI and releases

- **Tests** (`.github/workflows/tests.yml`) run on pushes to `main` and on pull requests. They cover PHP 8.2–8.6 with Laravel 12 and 13, each with both lowest and latest dependencies, and also run `composer validate --strict`.
- **Release** (`.github/workflows/release.yml`) runs when you push a semver tag. It runs the full test suite, creates a GitHub Release with generated notes (tags like `v1.1.0-beta.1` are marked as pre-releases), then tells Packagist to update.

```bash
git tag v1.0.0 && git push origin v1.0.0
```

One-time setup:
1. Submit the repository once at <https://packagist.org/packages/submit>.
2. Add the repository secrets `PACKAGIST_USERNAME` and `PACKAGIST_TOKEN`. The token is your Main API token from <https://packagist.org/profile/>.
3. Optional: add required reviewers to the `packagist` environment (Settings → Environments) so a person has to approve each publish.

## Things to check against a live test-mode token

These are built from the v2 Swagger but haven't been called live yet:

- `itemType` is sent as an integer, as the Swagger defines it. The guide's examples use names like `"Carton"`.
- Despatch fields use the Swagger spelling, `despatchDateTime*`. The create-consignment guide spells it `dispatch…`.
- `getAttachments` sends `consignmentId` as a query parameter, as the Swagger defines it.
