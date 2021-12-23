<?php
namespace test\orm\helpers;

use mstodulski\database\Collection;
use mstodulski\database\LazyCollection;

class SaleInvoice
{
    public ?int $id = null;
    public ?string $number = null;
    private Collection|LazyCollection $warehouseDocuments;

    public function __construct()
    {
        $this->warehouseDocuments = new Collection(WarehouseDocument::class);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(?string $number): void
    {
        $this->number = $number;
    }

    public function setWarehouseDocuments(LazyCollection|Collection $warehouseDocuments): void
    {
        $this->warehouseDocuments = $warehouseDocuments;
    }

    public function getWarehouseDocuments(): LazyCollection|Collection
    {
        return $this->warehouseDocuments;
    }
}
