<?php
namespace test\orm\helpers;

use JetBrains\PhpStorm\Pure;
use mstodulski\database\MigrationFactoryAbstract;

class WarehouseDocumentMigrationFactory extends MigrationFactoryAbstract
{
    #[Pure] public function createObject(array $yamlRecord) : WarehouseDocument
    {
        $warehouseDocument = new WarehouseDocument();
        $warehouseDocument->setId($yamlRecord['id']);
        $warehouseDocument->setNumber($yamlRecord['number']);

        return $warehouseDocument;
    }
}
