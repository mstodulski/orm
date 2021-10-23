<?php
namespace test\orm\helpers;

use DateTime;
use mstodulski\database\MigrationFactoryAbstract;

class PriceMigrationFactory extends MigrationFactoryAbstract
{
    public function createObject(array $yamlRecord) : Price
    {
        $userRepository = $this->entityManager->createRepository(User::class);
        $productRepository = $this->entityManager->createRepository(Product::class);

        $userCreatedBy = null;
        if ($yamlRecord['FK_Usr_createdBy'] != null) {
            /** @var User $userCreatedBy */
            $userCreatedBy = $userRepository->find($yamlRecord['FK_Usr_createdBy']);
        }

        $userUpdatedBy = null;
        if ($yamlRecord['FK_Usr_updatedBy'] != null) {
            /** @var User $userUpdatedBy */
            $userUpdatedBy = $userRepository->find($yamlRecord['FK_Usr_updatedBy']);
        }

        $product = null;
        if ($yamlRecord['FK_Pro_product'] != null) {
            /** @var Product $product */
            $product = $productRepository->find($yamlRecord['FK_Pro_product']);
        }

        $dateCreatedAt = null;
        if ($yamlRecord['createdAt'] != null) {
            $dateCreatedAt = DateTime::createFromFormat('Y-m-d H:i:s', $yamlRecord['createdAt']);
        }

        $dateUpdatedAt = null;
        if ($yamlRecord['updatedAt'] != null) {
            $dateUpdatedAt = DateTime::createFromFormat('Y-m-d H:i:s', $yamlRecord['updatedAt']);
        }

        $price = new Price();
        $price->setId($yamlRecord['id']);
        $price->setFK_Usr_createdBy($userCreatedBy);
        $price->setCreatedAt($dateCreatedAt);
        $price->setFK_Usr_updatedBy($userUpdatedBy);
        $price->setUpdatedAt($dateUpdatedAt);
        $price->setName($yamlRecord['name']);
        $price->setValue($yamlRecord['value']);
        $price->setFK_Pro_product($product);

        return $price;
    }
}
