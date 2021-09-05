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

abstract class AbstractMigration {

    protected EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function executeQuery($query)
    {
        echo $query . PHP_EOL;
        $this->entityManager->getDbConnection()->executeQuery($query);
    }

    abstract public function upVersion();
}
