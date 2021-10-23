<?php
namespace test\orm\helpers;

use JetBrains\PhpStorm\Pure;
use mstodulski\database\MigrationFactoryAbstract;

class EntityTwoMigrationFactory extends MigrationFactoryAbstract
{
    #[Pure] public function createObject(array $yamlRecord) : EntityTwo
    {
        $entityTwo = new EntityTwo();
        $entityTwo->setId($yamlRecord['id']);
        $entityTwo->setName($yamlRecord['name']);

        return $entityTwo;
    }
}
