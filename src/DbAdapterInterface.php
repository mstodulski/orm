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

use JetBrains\PhpStorm\Pure;

interface DbAdapterInterface
{
    public function getConditionsQueryPart(QueryCondition $queryCondition) : string;
    public function getSelectQuery(QueryBuilder $queryBuilder) : string;
    #[Pure] public function getInsertUpdateQuery(array $entityData, Repository $repository) : string;
    public function getForceInsertQuery(array $entityData, Repository $repository) : string;
    #[Pure] public function getRemoveQuery(array $entityData, Repository $repository) : string;
    public function getCountQuery(QueryBuilder $queryBuilder) : string;
    public function getGetTablesQuery() : string;
    public function getGetTableFieldsQuery(string $tableName, string $dbName) : string;
    public function getTableIndexesQuery(string $tableName) : string;
    public function getForeignKeysQuery(string $dbName) : string;
    public function getTableDetailsQuery(string $tableName, string $dbName) : string;
    public function getQueryForRemoveForeignKey(string $tableName, string $foreignKeyName) : string;
    public function getQueryForRemoveIndex(string $tableName, string $indexName) : string;
    public function getQueryForRemoveField(string $tableName, string $fieldName) : string;
    public function getQueryForRemoveTable(string $tableName) : string;
    public function getQueryForCreateTable(string $tableName, string $idFieldName, array $tableData) : string;
    public function getQueryForCreateField(string $tableName, string $fieldName, array $fieldData) : string;
    public function getQueryForUpdateField(string $tableName, string $fieldName, array $fieldData) : string;
    public function getQueryForCreateIndex(string $tableName, string $indexName, array $fieldData) : string;
    public function getQueryForCreateForeignKey(string $tableName, string $foreignKeyName, array $foreignKeyData) : string;
    public function getQueryForTurnOffForeignKeyCheck() : string;
    public function getQueryForTurnOnForeignKeyCheckQuery() : string;
    public function getQueryForTruncateTable($tableName) : string;
}
