<?php

namespace WebdevsComAu\MachShip\Resources;

use WebdevsComAu\MachShip\Client;
use WebdevsComAu\MachShip\Data\Responses\Document;

/**
 * Labels and other printable documents. All methods return a Document you
 * can store(), saveTo(), or return straight from a controller.
 */
class Labels extends Resource
{
    public function __construct(Client $client, ?int $defaultCompanyId = null, protected bool $printA4 = false)
    {
        parent::__construct($client, $defaultCompanyId);
    }

    /**
     * Item (carton/pallet) labels for one consignment as a single PDF.
     */
    public function forConsignment(int $consignmentId, ?bool $printA4 = null): Document
    {
        $info = $this->client->get('labels/getItemPdfFileInfo', [
            'consignmentId' => $consignmentId,
            'printA4' => $printA4 ?? $this->printA4,
        ]);

        return Document::fromFileInfo($info ?? [], "labels-{$consignmentId}.pdf");
    }

    /**
     * Item labels for up to 40 consignments. Returns one merged PDF when
     * $singlePdf is true, otherwise a ZIP with a PDF per consignment.
     *
     * @param  array<int, int>  $consignmentIds
     */
    public function forConsignments(array $consignmentIds, bool $singlePdf = true, ?bool $printA4 = null): Document
    {
        $ids = $this->ids($consignmentIds);

        if (count($ids) > 40) {
            throw new \InvalidArgumentException('MachShip returns labels for at most 40 consignments per request.');
        }

        return $this->client->download('GET', 'labels/getItemPdfsForConsignments', [
            'consignmentIds' => $ids,
            'printA4' => $printA4 ?? $this->printA4,
            'singlePdf' => $singlePdf,
        ], fallbackName: $singlePdf ? 'labels.pdf' : 'labels.zip');
    }

    /**
     * The consignment note / connote PDF.
     */
    public function consignmentNote(int $consignmentId): Document
    {
        $info = $this->client->get('labels/getConsignmentPdfFileInfo', ['consignmentId' => $consignmentId]);

        return Document::fromFileInfo($info ?? [], "consignment-{$consignmentId}.pdf");
    }

    public function manifest(int $manifestId): Document
    {
        $info = $this->client->get('labels/getManifestPdfFileInfo', ['manifestId' => $manifestId]);

        return Document::fromFileInfo($info ?? [], "manifest-{$manifestId}.pdf");
    }

    public function manifestForConsignment(int $consignmentId): Document
    {
        return $this->client->download('GET', 'labels/getManifestPdfByConsignmentId', [
            'consignmentId' => $consignmentId,
        ], fallbackName: "manifest-consignment-{$consignmentId}.pdf");
    }

    public function dangerousGoodsDocument(int $consignmentId): Document
    {
        return $this->client->download('GET', 'labels/getDangerousGoodsDocumentPdf', [
            'consignmentId' => $consignmentId,
        ], fallbackName: "dg-{$consignmentId}.pdf");
    }

    /**
     * Download an attachment, e.g. a proof of delivery image.
     */
    public function attachment(int $attachmentId): Document
    {
        return $this->client->download('GET', 'attachments/getAttachment', ['id' => $attachmentId]);
    }

    /**
     * Send item labels to a printer configured in MachShip.
     *
     * @param  array<int, int>  $consignmentIds
     */
    public function sendToPrinter(array $consignmentIds, int $printerId, int $labelType = 3, ?bool $printA4 = null): mixed
    {
        return $this->client->post('labels/sendLabelsToPrinter', [[
            'consignmentIds' => $this->ids($consignmentIds),
            'labelType' => $labelType,
            'printerId' => $printerId,
            'printA4' => $printA4 ?? $this->printA4,
        ]]);
    }
}
