<?php
namespace test\orm\helpers;

class SaleInvoiceToWarehouseDocument
{
    public ?int $id = null;
    public SaleInvoice $FK_Sal_invoice;
    public WarehouseDocument $FK_WaD_warehouseDocument;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getFK_WaD_warehouseDocument(): WarehouseDocument
    {
        return $this->FK_WaD_warehouseDocument;
    }

    public function setFK_WaD_warehouseDocument(WarehouseDocument $FK_WaD_warehouseDocument): void
    {
        $this->FK_WaD_warehouseDocument = $FK_WaD_warehouseDocument;
    }

    public function getFK_Sal_invoice(): SaleInvoice
    {
        return $this->FK_Sal_invoice;
    }

    public function setFK_Sal_invoice(SaleInvoice $FK_Sal_invoice): void
    {
        $this->FK_Sal_invoice = $FK_Sal_invoice;
    }
}
