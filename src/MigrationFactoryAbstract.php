<?php
/**
 * This file is part of the EasyCore package.
 *
 * (c) Marcin Stodulski <marcin.stodulski@devsprint.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace mstodulski\database;

abstract class MigrationFactoryAbstract {

    public EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    abstract public function createObject(array $yamlRecord);
}
