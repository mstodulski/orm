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

use Exception;

class Repository
{
    private string $tableName;
    private string $entityClass;
    private EntityManager $entityManager;

    public function __construct(string $entityClass, EntityManager $entityManager, string $tableName = null)
    {
        if (!class_exists($entityClass)) {
            throw new Exception('Class ' . $entityClass . ' not exists.');
        }

        $this->entityClass = $entityClass;
        $this->entityManager = $entityManager;

        if (!isset($tableName)) {
            $this->tableName = self::createTableNameFromEntityClass($this->entityClass);
        } else {
            $this->tableName = $tableName;
        }
    }

    public static function createTableNameFromEntityClass($entityName): string
    {
        $objectClass = explode('\\', $entityName);
        $objectClass = end($objectClass);
        $pieces = preg_split('/(?=[A-Z])/', lcfirst($objectClass));

        $previousIndex = array_key_first($pieces);
        foreach ($pieces as $index => $piece) {
            if ((strlen($piece) == 1) && (strtoupper($piece) == $piece)) {
                $pieces[$previousIndex] .= $piece;
                unset($pieces[$index]);
            } else {
                $previousIndex = $index;
            }
        }

        return implode('_', array_map('strtolower', $pieces));
    }

    public function getTableName() : string
    {
        return $this->tableName;
    }

    public function createQueryBuilder(?string $alias) : QueryBuilder
    {
        $queryBuilder = new QueryBuilder($this->entityManager, $this->entityClass);
        $queryBuilder->from($this->tableName, $alias);

        return $queryBuilder;
    }

    public function find(int $id, HydrationMode $hydrationMode = HydrationMode::Object) : object|array|null
    {
        return $this->entityManager->find($this->entityClass, $id, $hydrationMode);
    }

    public function findBy(array $parameters, array $sort = [], HydrationMode $hydrationMode = HydrationMode::Object) : array
    {
        return $this->entityManager->findBy($this->entityClass, $parameters, $sort, $hydrationMode);
    }

    public function findOneBy(array $parameters, array $sort = [], HydrationMode $hydrationMode = HydrationMode::Object) : array|object
    {
        return $this->entityManager->findOneBy($this->entityClass, $parameters, $sort, $hydrationMode);
    }

    public function count(array $parameters = []) : int
    {
        return $this->entityManager->count($this->entityClass, $parameters);
    }

    public function findAll(array $sort = [], HydrationMode $hydrationMode = HydrationMode::Object) : array
    {
        return $this->entityManager->findBy($this->entityClass, [], $sort, $hydrationMode);
    }
}
