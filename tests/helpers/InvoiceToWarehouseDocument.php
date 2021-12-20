<?php
namespace test\orm\helpers;

class InvoiceToWarehouseDocument
{
    public ?int $id = null;
    public Invoice $FK_Inv_invoice;
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

    public function getFK_Inv_invoice(): Invoice
    {
        return $this->FK_Inv_invoice;
    }

    public function setFK_Inv_invoice(Invoice $FK_Inv_invoice): void
    {
        $this->FK_Inv_invoice = $FK_Inv_invoice;
    }
}
