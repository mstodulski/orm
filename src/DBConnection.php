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

use DateTime;
use Exception;
use PDO;
use PDOException;
use PDOStatement;

class DBConnection
{
    private PDO $connection;
    private DbAdapterInterface $dbAdapter;
    private EntityManager $entityManager;

    public function __construct(DbAdapterInterface $dbAdapter, string $dsn, string $user, string $password = null, EntityManager $entityManager)
    {
        $this->dbAdapter = $dbAdapter;

        $options = [
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => true
        ];

        $this->connection = new PDO($dsn, $user, $password, $options);
        $this->entityManager = $entityManager;
    }

    public function bindParameters(string $query, array $parameters = []) : PDOStatement
    {
        $statement = $this->connection->prepare($query);

        foreach ($parameters as $parameter) {

            if (is_object($parameter['value'])) {
                if ($parameter['value'] instanceof DateTime) {
                    $statement->bindValue($parameter['name'], $parameter['value']->format('Y-m-d H:i:s'), $parameter['type']);
                } else {
                    $fieldObjectConfiguration = $this->entityManager->loadClassConfiguration(get_class($parameter['value']));
                    $idFieldName = ObjectMapper::getIdFieldName($fieldObjectConfiguration);
                    $classProperties = [];
                    ObjectMapper::getClassProperties(get_class($parameter['value']), $classProperties);

                    $propertyIdField = $classProperties[$idFieldName];
                    $visibilityLevel = ObjectMapper::setFieldAccessible($propertyIdField);

                    if (!$propertyIdField->isInitialized($parameter['value'])) {
                        throw new Exception('The object used as a parameter does not have an id field.');
                    } else {
                        $preparedId = $propertyIdField->getValue($parameter['value']);
                    }
                    ObjectMapper::setOriginalAccessibility($propertyIdField, $visibilityLevel);

                    $statement->bindValue($parameter['name'], $preparedId, $parameter['type']);
                }
            } else {
                if (is_array($parameter['value'])) {
                    $parameter['value'] = json_encode($parameter['value']);
                }

                $statement->bindValue($parameter['name'], $parameter['value'], $parameter['type']);
            }
        }

        return $statement;
    }

    public function beginTransaction() : bool
    {
        return $this->connection->beginTransaction();
    }

    public function commitTransaction() : bool
    {
        return $this->connection->inTransaction() && $this->connection->commit();
    }

    public function rollbackTransaction() : bool
    {
        return $this->connection->inTransaction() && $this->connection->rollBack();
    }

    public function checkIfTransactionStarted(): bool
    {
        return $this->connection->inTransaction();
    }

    public function getConnection(): ?PDO
    {
        return $this->connection;
    }

    public function getLastInsertId(): string
    {
        return $this->connection->lastInsertId();
    }

    public function executeQuery(string $query, array $parameters = [])
    {
        $statement = $this->bindParameters($query, $parameters);

        try {
            $statement->execute();
            $statement->closeCursor();
        } catch (PDOException $pdoException) {
            $statement->closeCursor();
            throw $pdoException;
        }
    }

    public function getTable(string $query, array $parameters = []): array
    {
        $statement = $this->bindParameters($query, $parameters);

        try {
            $statement->execute();
            $resultArray = $statement->fetchAll(PDO::FETCH_ASSOC);
            $statement->closeCursor();

            return $resultArray;
        } catch (PDOException $pdoException) {
            $statement->closeCursor();
            throw $pdoException;
        }
    }

    public function getSingleRow(string $query, array $parameters = [])
    {
        $statement = $this->bindParameters($query, $parameters);

        try {
            $statement->execute();
            $resultRow = $statement->fetch(PDO::FETCH_ASSOC);
            $statement->closeCursor();

            return ($resultRow) ?: null;
        } catch (PDOException $pdoException) {
            $statement->closeCursor();
            throw $pdoException;
        }
    }

    public function getValue(string $query, array $parameters = []) : string
    {
        $statement = $this->bindParameters($query, $parameters);

        try {
            $statement->execute();
            $resultField = $statement->fetchColumn();
            $statement->closeCursor();

            return $resultField;
        } catch (PDOException $pdoException) {
            $statement->closeCursor();
            throw $pdoException;
        }
    }

    public function getDbAdapter(): DbAdapterInterface
    {
        return $this->dbAdapter;
    }
}
