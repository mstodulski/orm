<?php
namespace app\admin;

use mstodulski\database\MigrationFactoryAbstract;

class UserMigrationFactory extends MigrationFactoryAbstract
{
    public function createObject(array $yamlRecord) : User
    {
        $user = new User();
        $user->setId($yamlRecord['id']);
        $user->setName($yamlRecord['name']);

        return $user;
    }
}
