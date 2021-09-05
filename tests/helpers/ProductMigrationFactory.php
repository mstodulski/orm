<?php
namespace app\admin;

use JetBrains\PhpStorm\Pure;
use mstodulski\database\MigrationFactoryAbstract;

class ProductMigrationFactory extends MigrationFactoryAbstract
{
    #[Pure] public function createObject(array $yamlRecord) : Product
    {
        $userRepository = $this->entityManager->createRepository(User::class);
        $entityOneRepository = $this->entityManager->createRepository(EntityOne::class);
        $entityTwoRepository = $this->entityManager->createRepository(EntityTwo::class);

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

        $entityOne = null;
        if ($yamlRecord['entityOne'] != null) {
            /** @var EntityOne $entityOne */
            $entityOne = $entityOneRepository->find($yamlRecord['entityOne']);
        }

        $entityTwo = null;
        if ($yamlRecord['entityTwo'] != null) {
            /** @var EntityTwo $entityTwo */
            $entityTwo = $entityTwoRepository->find($yamlRecord['entityTwo']);
        }

        $dateCreatedAt = null;
        if ($yamlRecord['createdAt'] != null) {
            $dateCreatedAt = \DateTime::createFromFormat('Y-m-d H:i:s', $yamlRecord['createdAt']);
        }

        $dateUpdatedAt = null;
        if ($yamlRecord['updatedAt'] != null) {
            $dateUpdatedAt = \DateTime::createFromFormat('Y-m-d H:i:s', $yamlRecord['updatedAt']);
        }

        $date = null;
        if ($yamlRecord['date'] != null) {
            $date = \DateTime::createFromFormat('Y-m-d H:i:s', $yamlRecord['date']);
        }

        $product = new Product();
        $product->setId($yamlRecord['id']);
        $product->setFK_Usr_createdBy($userCreatedBy);
        $product->setCreatedAt($dateCreatedAt);
        $product->setFK_Usr_updatedBy($userUpdatedBy);
        $product->setUpdatedAt($dateUpdatedAt);
        $product->setSortOrder($yamlRecord['sortOrder']);
        $product->setCreatorBrowser($yamlRecord['creatorBrowser']);
        $product->setName($yamlRecord['name']);
        $product->setArchived($yamlRecord['archived']);
        $product->setWeight($yamlRecord['weight']);
        $product->setEntityOne($entityOne);
        $product->setEntityTwo($entityTwo);
        $product->setDate($date);

        return $product;
    }
}
