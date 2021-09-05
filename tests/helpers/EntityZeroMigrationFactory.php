<?php
namespace app\admin;

use JetBrains\PhpStorm\Pure;
use mstodulski\database\MigrationFactoryAbstract;

class EntityZeroMigrationFactory extends MigrationFactoryAbstract
{
    #[Pure] public function createObject(array $yamlRecord) : EntityZero
    {
        $entityTwo = new EntityZero();
        $entityTwo->setId($yamlRecord['id']);
        $entityTwo->setName($yamlRecord['name']);

        return $entityTwo;
    }
}
