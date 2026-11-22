<?php

namespace WebdevsComAu\MachShip\Resources;

use DateTimeInterface;
use Illuminate\Support\Collection;
use WebdevsComAu\MachShip\Data\Requests\ConsignmentRequest;
use WebdevsComAu\MachShip\Data\Responses\Consignment;
use WebdevsComAu\MachShip\Enums\TrackingStatus;
use WebdevsComAu\MachShip\Support\Dates;

class Consignments extends Resource
{
    /**
     * Create a consignment. Requests containing dangerous goods are sent to
     * createConsignmentwithComplexItems automatically.
     *
     * @param  ConsignmentRequest|array<string, mixed>  $request  builder or raw CreateConsignmentV2 payload
     */
    public function create(ConsignmentRequest|array $request, bool $includeCarrierCustomFields = false): Consignment
    {
        if (is_array($request)) {
            $request = ConsignmentRequest::fromArray($request);
        }

        $request->companyId ??= $this->defaultCompanyId;
        $complex = $request->requiresComplexItems();

        $result = $this->client->post(
            $complex ? 'consignments/createConsignmentwithComplexItems' : 'consignments/createConsignment',
            $request->toPayload($complex),
            ['includeCarrierCustomFields' => $includeCarrierCustomFields ?: null],
        );

        return Consignment::fromArray($result ?? []);
    }

    /**
     * Send a pre-built payload as-is to either create endpoint.
     *
     * @param  array<string, mixed>  $payload
     */
    public function createRaw(array $payload, bool $complexItems = false): Consignment
    {
        $result = $this->client->post(
            $complexItems ? 'consignments/createConsignmentwithComplexItems' : 'consignments/createConsignment',
            $payload,
        );

        return Consignment::fromArray($result ?? []);
    }

    public function find(int $id, bool $includeDeleted = false): Consignment
    {
        $result = $this->client->get('consignments/getConsignment', [
            'id' => $id,
            'includeDeleted' => $includeDeleted ?: null,
        ]);

        return Consignment::fromArray($result ?? []);
    }

    /**
     * Fetch several consignments (MachShip suggests batches of 10).
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, Consignment>
     */
    public function findMany(array $ids): Collection
    {
        return collect($this->ids($ids))
            ->chunk(10)
            ->flatMap(fn (Collection $chunk) => $this->client->post('consignments/returnConsignments', ['ids' => $chunk->values()->all()]) ?? [])
            ->map(fn (array $row) => Consignment::fromArray($row))
            ->values();
    }

    /**
     * Look up consignments by customerReference.
     *
     * @param  array<int, string>  $references
     * @return Collection<int, Consignment>
     */
    public function findByReference(array $references, ?int $companyId = null, bool $includeChildCompanies = false): Collection
    {
        $result = $this->client->post('consignments/returnConsignmentsByReference1', array_values($references), [
            'companyId' => $this->companyId($companyId),
            'includeChildCompanies' => $includeChildCompanies ?: null,
        ]);

        return Consignment::collect($result);
    }

    /**
     * Look up consignments by the carrier's connote number.
     *
     * @param  array<int, string>  $carrierConsignmentIds
     * @return Collection<int, Consignment>
     */
    public function findByCarrierConsignmentId(array $carrierConsignmentIds): Collection
    {
        return Consignment::collect(
            $this->client->post('consignments/returnConsignmentsByCarrierConsignmentId', array_values($carrierConsignmentIds)),
        );
    }

    /**
     * Consignments not yet manifested (paged).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function unmanifested(?int $companyId = null, int $startIndex = 1, int $retrieveSize = 40, ?int $carrierId = null): Collection
    {
        $result = $this->client->get('consignments/getUnmanifested', [
            'companyId' => $this->companyId($companyId),
            'startIndex' => $startIndex,
            'retrieveSize' => $retrieveSize,
            'carrierId' => $carrierId,
        ]);

        return collect($result ?? []);
    }

    /**
     * Consignments created or updated in a window, useful for status syncing.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function recentlyChanged(DateTimeInterface|string $fromUtc, DateTimeInterface|string|null $toUtc = null, ?int $companyId = null, int $startIndex = 1, int $retrieveSize = 40): Collection
    {
        $result = $this->client->get('consignments/getRecentlyCreatedOrUpdatedConsignments', [
            'fromDateUtc' => Dates::utc($fromUtc),
            'toDateUtc' => Dates::utc($toUtc),
            'companyId' => $this->companyId($companyId),
            'startIndex' => $startIndex,
            'retrieveSize' => $retrieveSize,
        ]);

        return collect($result ?? []);
    }

    /**
     * Delete consignments that have NOT been manifested yet.
     *
     * @param  int|array<int, int>  $ids
     */
    public function delete(int|array $ids): void
    {
        $this->client->post('consignments/deleteUnmanifestedConsignments', $this->ids((array) $ids));
    }

    /**
     * Mark manifested consignments as cancelled in MachShip. This does NOT
     * tell the carrier; contact them separately to cancel a booked pickup.
     *
     * @param  int|array<int, int>  $ids
     */
    public function cancel(int|array $ids, ?string $reason = null, DateTimeInterface|string|null $at = null): void
    {
        $this->updateStatus($ids, TrackingStatus::Cancelled, $at, $reason);
    }

    /**
     * Push a manual tracking status onto consignments.
     *
     * @param  int|array<int, int>  $ids
     */
    public function updateStatus(int|array $ids, TrackingStatus|int $status, DateTimeInterface|string|null $at = null, ?string $extraInformation = null): void
    {
        $at = Dates::local($at ?? now());
        $status = $status instanceof TrackingStatus ? $status->value : $status;

        $this->client->post('consignments/updateConsignmentStatuses', array_map(fn (int $id) => array_filter([
            'consignmentId' => $id,
            'status' => $status,
            'statusDateTimeLocal' => $at,
            'extraInformation' => $extraInformation,
        ], fn ($v) => $v !== null), $this->ids((array) $ids)));
    }

    /**
     * Attachments (e.g. proof of delivery) on a consignment:
     * [{id, fileName, dateAdded}]. Download with MachShip::labels()->attachment($id).
     *
     * @return Collection<int, array{id: int, fileName: string, dateAdded: string}>
     */
    public function attachments(int $consignmentId): Collection
    {
        return collect($this->client->post('consignments/getAttachments', null, ['consignmentId' => $consignmentId]) ?? []);
    }
}
