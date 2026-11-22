<?php

namespace WebdevsComAu\MachShip\Resources;

use DateTimeInterface;
use Illuminate\Support\Collection;
use WebdevsComAu\MachShip\Data\Responses\Manifest;
use WebdevsComAu\MachShip\Support\Dates;

/**
 * Manifesting is a two-step process: group consignments (MachShip splits
 * them per carrier/account/pickup), then book each group. Booking a
 * manifest sends the consignments to the carrier and books a pickup.
 */
class Manifests extends Resource
{
    /**
     * Group consignments into manifest-ready batches (BookedManifestV2[]).
     * Adjust pickup times etc. on the returned arrays before calling book().
     *
     * @param  array<int, int>  $consignmentIds
     * @return array<int, array<string, mixed>>
     */
    public function group(array $consignmentIds): array
    {
        return $this->client->post('manifests/groupConsignmentsForManifest', $this->ids($consignmentIds)) ?? [];
    }

    /**
     * Group every unmanifested consignment for a company.
     *
     * @return array<int, array<string, mixed>>
     */
    public function groupAllUnmanifested(?int $companyId = null): array
    {
        return $this->client->post('manifests/groupAllUnmanifestedConsignmentsForManifest', $this->companyId($companyId) ?? 0) ?? [];
    }

    /**
     * Book manifests from grouped batches.
     *
     * @param  array<int, array<string, mixed>>  $groups  output of group()
     * @return Collection<int, Manifest>
     */
    public function book(array $groups): Collection
    {
        return Manifest::collect($this->client->post('manifests/manifest', array_values($groups)));
    }

    /**
     * Group and book in one call.
     *
     * @param  array<int, int>  $consignmentIds
     * @return Collection<int, Manifest>
     */
    public function manifest(
        array $consignmentIds,
        DateTimeInterface|string|null $pickupAt = null,
        DateTimeInterface|string|null $closingAt = null,
        ?string $pickupInstructions = null,
        bool $pickupAlreadyBooked = false,
    ): Collection {
        $groups = array_map(fn (array $group) => array_merge($group, array_filter([
            'pickupDateTime' => Dates::local($pickupAt),
            'pickupClosingTime' => Dates::local($closingAt),
            'pickupSpecialInstructions' => $pickupInstructions,
            'pickupAlreadyBooked' => $pickupAlreadyBooked ?: null,
        ], fn ($v) => $v !== null)), $this->group($consignmentIds));

        return $groups === [] ? collect() : $this->book($groups);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function all(?int $companyId = null, int $startIndex = 1, int $retrieveSize = 40, DateTimeInterface|string|null $startDate = null, DateTimeInterface|string|null $endDate = null): Collection
    {
        return collect($this->client->get('manifests/getAll', [
            'companyId' => $this->companyId($companyId),
            'startIndex' => $startIndex,
            'retrieveSize' => $retrieveSize,
            'startDate' => Dates::local($startDate),
            'endDate' => Dates::local($endDate),
        ]) ?? []);
    }
}
