<?php
namespace test\orm\helpers;

use JetBrains\PhpStorm\Pure;
use mstodulski\database\MigrationFactoryAbstract;

class InvoiceToWarehouseDocumentMigrationFactory extends MigrationFactoryAbstract
{
    #[Pure] public function createObject(array $yamlRecord) : InvoiceToWarehouseDocument
    {
        $invoiceRepository = $this->entityManager->createRepository(Invoice::class);
        $warehouseDocumentRepository = $this->entityManager->createRepository(WarehouseDocument::class);

        /** @var Invoice $invoice */
        $invoice = $invoiceRepository->find($yamlRecord['FK_Inv_invoice']);
        /** @var WarehouseDocument $warehouseDocument */
        $warehouseDocument = $warehouseDocumentRepository->find($yamlRecord['FK_WaD_warehouseDocument']);

        $invoiceToWarehouseDocument = new InvoiceToWarehouseDocument();
        $invoiceToWarehouseDocument->setId($yamlRecord['id']);
        $invoiceToWarehouseDocument->setFK_Inv_invoice($invoice);
        $invoiceToWarehouseDocument->setFK_WaD_warehouseDocument($warehouseDocument);

        return $invoiceToWarehouseDocument;
    }
}
