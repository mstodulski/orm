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

class MySQLAdapter implements DbAdapterInterface
{
    public function getSelectQuery(QueryBuilder $queryBuilder) : string
    {
        return 'SELECT ' .
            $this->prepareFields($queryBuilder) .
            ' FROM ' .
            $this->prepareTable($queryBuilder) .
            $this->prepareJoins($queryBuilder) .
            $this->prepareWhere($queryBuilder) .
            $this->prepareGroupBy($queryBuilder) .
            $this->prepareHaving($queryBuilder) .
            $this->prepareSorting($queryBuilder) .
            $this->prepareLimit($queryBuilder)
            ;
    }

    #[Pure] public function getForceInsertQuery(array $entityData, Repository $repository) : string
    {
        $entityFields = [$entityData['identifier']['fieldName']];
        $entityFieldsValues = [':' . $entityData['identifier']['fieldName']];
        foreach ($entityData['data'] as $fieldName => $fieldValue) {
            $entityFields[] = $fieldName;
            $entityFieldsValues[] = ':' . $fieldName;
        }

        return /** @lang */'INSERT INTO `' . $repository->getTableName() . '` (' . implode(', ', $entityFields) . ') VALUES (' . implode(', ', $entityFieldsValues)  . ')';
    }

    #[Pure] public function getInsertUpdateQuery(array $entityData, Repository $repository) : string
    {
        if (null === $entityData['identifier']['value']) {
            $entityFields = [];
            $entityFieldsValues = [];
            foreach ($entityData['data'] as $fieldName => $fieldValue) {
                $entityFields[] = $fieldName;
                $entityFieldsValues[] = ':' . $fieldName;
            }

            $query =  /** @lang */'INSERT INTO `' . $repository->getTableName() . '` (' . implode(', ', $entityFields) . ') VALUES (' . implode(', ', $entityFieldsValues)  . ')';

        } else {
            $query = /** @lang */'UPDATE ';

            $query .=  '`' . $repository->getTableName() . '` ';
            $query .= 'SET ';
            $queryFields = [];
            foreach ($entityData['data'] as $fieldName => $fieldValue) {
                $queryFields[] = $fieldName . ' = :' .$fieldName;
            }
            $query .= implode(', ', $queryFields);

            if (null !== $entityData['identifier']['value']) {
                $query .= ' WHERE ' . $entityData['identifier']['fieldName'] . '=:' . $entityData['identifier']['fieldName'] . ' ';
            }
        }

        return $query;
    }

    #[Pure] public function getRemoveQuery(array $entityData, Repository $repository) : string
    {
        return /** @lang */ 'DELETE FROM `' . $repository->getTableName() . '` WHERE ' . $entityData['identifier']['fieldName'] . ' = :' . $entityData['identifier']['fieldName'];
    }

    public function getCountQuery(QueryBuilder $queryBuilder) : string
    {
        return /** @lang  */'SELECT COUNT(*) FROM (' . $this->getSelectQuery($queryBuilder) . ') as ' . uniqid('tab_');
    }

    public function getConditionsQueryPart(QueryCondition $queryCondition) : string
    {
        $conditionString = '';

        foreach ($queryCondition->conditions as $condition) {
            $precendingOperator = match ($condition['precedingOperator']) {
                QueryConditionOperator::And => 'AND',
                QueryConditionOperator::Or => 'OR',
                null => '',
            };

            if ($condition['conditionKind'] == QueryConditionKind::Condition) {
                $conditionString = ($conditionString != '') ? ' (' . $conditionString . ') ' : $conditionString;
                /** @var QueryCondition $conditionObj */
                $conditionObj = $condition['condition'];
                $conditionString .= $precendingOperator . ' (' . $this->getConditionsQueryPart($conditionObj) . ') ';
            } elseif ($condition['conditionKind'] == QueryConditionKind::String) {
                $conditionString .= $precendingOperator . ' ' . $condition['condition'] . ' ';
            }
        }

        return $conditionString;
    }

    #[Pure] private function prepareFields(QueryBuilder $queryBuilder) : string
    {
        if (empty($queryBuilder->getFields())) {
            return ' `' . ((null !== $queryBuilder->getTableAlias()) ? $queryBuilder->getTableAlias() : $queryBuilder->getTableName()) . '`.* ';
        } else {
            $fields = [];
            foreach ($queryBuilder->getFields() as $field) {
                $fields[] = $field['fieldName'] . ((null !== $field['alias']) ? ' AS ' . $field['alias'] : '');
            }
            return implode(', ', $fields) . ' ';
        }
    }

    #[Pure] private function prepareTable(QueryBuilder $queryBuilder) : string
    {
        return '`' . $queryBuilder->getTableName() . ((null !== $queryBuilder->getTableAlias()) ? '` AS `' . $queryBuilder->getTableAlias() . '` ' : '`');
    }

    private function prepareJoins(QueryBuilder $queryBuilder) : string
    {
        $query = ' ';
        foreach ($queryBuilder->getJoins() as $join) {
            /** @var QueryCondition $queryCondition */
            $queryCondition = $join['queryCondition'];
            $query .= ' LEFT JOIN ' .
                $join['table'] . ' AS ' . $join['alias'] .
                ' ON ' . $this->getConditionsQueryPart($queryCondition) . ' ';
        }

        return $query;
    }

    private function prepareWhere(QueryBuilder $queryBuilder) : string
    {
        $whereConditions = [];

        foreach ($queryBuilder->getWhere() as $whereCondition) {
            /** @var QueryCondition $whereCondition */
            $whereConditions[] = $this->getConditionsQueryPart($whereCondition);
        }

        if (!empty($whereConditions)) {
            return ' WHERE ' . implode(' AND ', $whereConditions);
        } else {
            return '';
        }
    }

    #[Pure] private function prepareGroupBy(QueryBuilder $queryBuilder) : string
    {
        if (!empty($queryBuilder->getGroupBy())) {
            return ' GROUP BY ' . implode(', ', $queryBuilder->getGroupBy()) . ' ';
        } else {
            return '';
        }
    }

    private function prepareHaving(QueryBuilder $queryBuilder) : string
    {
        $havingConditions = [];
        foreach ($queryBuilder->getHaving() as $havingCondition) {
            /** @var QueryCondition $havingCondition */
            $havingConditions[] = $this->getConditionsQueryPart($havingCondition);
        }

        if (!empty($havingConditions)) {
            return ' HAVING ' . implode(' AND ', $havingConditions);
        } else {
            return '';
        }
    }

    #[Pure] private function getSortingQueryPart(QuerySorting $querySorting) : string
    {
        if (!empty($querySorting->getFields())) {
            $condition = '';
            foreach ($querySorting->getFields() as $field) {
                $condition .= match (strtolower($field['sortDirection'])) {
                    QuerySorting::DIRECTION_ASC => ' ' . $field['fieldName'] . ' ASC ',
                    QuerySorting::DIRECTION_DESC => ' ' . $field['fieldName'] . ' DESC ',
                    QuerySorting::DIRECTION_RANDOM => ' RAND() ',
                    QuerySorting::DIRECTION_ORDERED => ' FIELD (' . $field['fieldName'] . ', "' . implode('", "', $field['sortOrder']) . '") ' . $field['orderedDirectionAscDesc'] . ' ',
                };
            }

            return ' ORDER BY ' . $condition;
        } else {
            return '';
        }
    }

    #[Pure] private function prepareSorting(QueryBuilder $queryBuilder) : string
    {
        if (null !== $queryBuilder->getSorting()) {
            return $this->getSortingQueryPart($queryBuilder->getSorting());
        } else {
            return '';
        }
    }

    #[Pure] private function prepareLimit(QueryBuilder $queryBuilder) : string
    {
        if (null !== $queryBuilder->getLimit()) {
            return ' LIMIT ' . ((null !== $queryBuilder->getOffset()) ? $queryBuilder->getOffset() . ', ' . $queryBuilder->getLimit() : '' . $queryBuilder->getLimit() . ' ');
        } else {
            return '';
        }
    }

    public function getGetTablesQuery() : string
    {
        return 'SHOW TABLES';
    }

    public function getGetTableFieldsQuery(string $tableName, string $dbName) : string
    {
        return 'SELECT ' .
            'COLUMN_NAME as name, ' .
            'COLUMN_TYPE as type, ' .
            'COLLATION_NAME as collation, ' .
            'IS_NULLABLE as nullable, ' .
            'COLUMN_DEFAULT as defaultValue, ' .
            'EXTRA as extra ' .
            'FROM INFORMATION_SCHEMA.COLUMNS ' .
            'WHERE ' .
            'TABLE_SCHEMA = "' . $dbName . '" AND ' .
            'TABLE_NAME = "' . $tableName .'"';
    }

    public function getTableIndexesQuery(string $tableName) : string
    {
        return 'SHOW INDEX FROM `' . $tableName . '`';
    }

    public function getForeignKeysQuery(string $dbName) : string
    {
        return 'SELECT  ' .
            'INFORMATION_SCHEMA.KEY_COLUMN_USAGE.TABLE_NAME as tableName, ' .
            'COLUMN_NAME as columnName, ' .
            'INFORMATION_SCHEMA.KEY_COLUMN_USAGE.CONSTRAINT_NAME as keyName, ' .
            'INFORMATION_SCHEMA.KEY_COLUMN_USAGE.REFERENCED_TABLE_NAME as referencedTable, ' .
            'REFERENCED_COLUMN_NAME as referencedColumn, ' .
            'information_schema.referential_constraints.UPDATE_RULE as updateRule, ' .
            'information_schema.referential_constraints.DELETE_RULE as deleteRule ' .
            'FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE ' .
            'LEFT JOIN information_schema.referential_constraints ' .
            'ON INFORMATION_SCHEMA.KEY_COLUMN_USAGE.CONSTRAINT_NAME=information_schema.referential_constraints.CONSTRAINT_NAME AND information_schema.referential_constraints.CONSTRAINT_SCHEMA = "' . $dbName . '" ' .
            'WHERE ' .
            'INFORMATION_SCHEMA.KEY_COLUMN_USAGE.TABLE_SCHEMA="' . $dbName . '" AND ' .
            'INFORMATION_SCHEMA.KEY_COLUMN_USAGE.REFERENCED_TABLE_NAME IS NOT NULL ' .
            'GROUP BY INFORMATION_SCHEMA.KEY_COLUMN_USAGE.CONSTRAINT_NAME';
    }

    public function getTableDetailsQuery(string $tableName, string $dbName) : string
    {
        return 'SELECT ' .
            'CCSA.CHARACTER_SET_NAME as encoding, ' .
            'T.TABLE_COLLATION as collation, ' .
            'T.ENGINE as engine ' .
            'FROM '.
            'information_schema.`TABLES` T, ' .
            'information_schema.`COLLATION_CHARACTER_SET_APPLICABILITY` CCSA ' .
            'WHERE ' .
            'CCSA.collation_name = T.table_collation AND '  .
            'T.table_schema = "' . $dbName. '" AND ' .
            'T.table_name = "' . $tableName . '";';
    }

    //---------------------------------------

    public function getQueryForRemoveForeignKey(string $tableName, string $foreignKeyName) : string
    {
        return /** @lang */'ALTER TABLE `' . $tableName . '` DROP FOREIGN KEY ' . $foreignKeyName;
    }

    public function getQueryForRemoveIndex(string $tableName, string $indexName) : string
    {
        return /** @lang */'ALTER TABLE `' . $tableName . '` DROP INDEX `' . $indexName . '`';
    }

    public function getQueryForRemoveField(string $tableName, string $fieldName) : string
    {
        return /** @lang */'ALTER TABLE `' . $tableName . '` DROP `' . $fieldName . '`';
    }

    public function getQueryForRemoveTable($tableName) : string
    {
        return /** @lang */'DROP TABLE `' . $tableName . '`;';
    }

    public function getQueryForCreateTable(string $tableName, string $idFieldName, array $tableData) : string
    {
        $query = 'CREATE TABLE `' . $tableName . '` (';
        $fields = [];
        foreach ($tableData['fields'] as $fieldName => $fieldData) {
            $type = $fieldData['type'];
            if (isset($fieldData['precision'])) {
                if ($fieldData['precision'] !== null) $type .= '(' . $fieldData['precision'];
                if (($fieldData['scale'] !== null) && ($fieldData['precision'] !== null)) $type .= ', ' . $fieldData['scale'];
                if ($fieldData['precision'] !== null) $type .= ')';
            }

            $nullable = $fieldData['nullable'] ? ' NULL ' : ' NOT NULL ';
            $default = (($fieldData['defaultValue'] != null)
                    ? " DEFAULT '" . $fieldData['defaultValue'] . "'"
                    : (($fieldData['nullable']) ? ' DEFAULT NULL' : ''));

            $collation = '';
            if ($fieldData['collation'] != null) {
                $collation .= ' COLLATE ' . $fieldData['collation'] . ' ';
            }

            $extra = '';
            if (isset($fieldData['extra'])) {
                $extra = ' ' . $fieldData['extra'];
            }

            $fields[] = '`' . $fieldName . '` ' . $type . $extra . $collation . $nullable . $default;
        }

        $fields[] = 'PRIMARY KEY (`' . $idFieldName . '`)';

        $query .= implode(', ', $fields);
        $query .= ')';
        $query .= ' ENGINE ' . $tableData['tableDefaults']['engine'] . ' DEFAULT CHARSET=' . $tableData['tableDefaults']['encoding'] .
                  ' COLLATE=' . $tableData['tableDefaults']['collation'];

        return $query;
    }

    public function getQueryForCreateField(string $tableName, string $fieldName, array $fieldData) : string
    {
        $type = $fieldData['type'];
        if (isset($fieldData['precision'])) {
            if ($fieldData['precision'] !== null) $type .= '(' . $fieldData['precision'];
            if (($fieldData['scale'] !== null) && ($fieldData['precision'] !== null)) $type .= ', ' . $fieldData['scale'];
            if ($fieldData['precision'] !== null) $type .= ')';
        }

        $nullable = $fieldData['nullable'] ? ' NULL ' : ' NOT NULL ';
        $default = (($fieldData['defaultValue'] != null)
            ? ' DEFAULT "' . $fieldData['defaultValue'] . '"'
            : (($fieldData['nullable']) ? ' DEFAULT NULL' : ''));

        $collation = '';
        if ($fieldData['collation'] != null) {
            $collation .= ' CHARACTER SET utf8 COLLATE ' . $fieldData['collation'] . ' ';
        }

        $extra = '';
        if (isset($fieldData['extra'])) {
            $extra = ' ' . $fieldData['extra'];
        }

        return /** @lang */'ALTER TABLE `' . $tableName . '` ADD `' . $fieldName . '` ' . $type . $extra . $collation . $nullable . $default;
    }

    public function getQueryForUpdateField(string $tableName, string $fieldName, array $fieldData) : string
    {
        $type = $fieldData['type'];
        if (isset($fieldData['precision'])) {
            if ($fieldData['precision'] !== null) $type .= '(' . $fieldData['precision'];
            if (($fieldData['scale'] !== null) && ($fieldData['precision'] !== null)) $type .= ', ' . $fieldData['scale'];
            if ($fieldData['precision'] !== null) $type .= ')';
        }

        $nullable = '';
        if (isset($fieldData['nullable'])) {
            $nullable = $fieldData['nullable'] ? ' NULL ' : ' NOT NULL ';
        }

        $default = '';
        if (isset($fieldData['defaultValue'])) {
            $default = ' DEFAULT ' . (($fieldData['defaultValue'] != null) ? "'" . $fieldData['defaultValue'] . "'" : 'NULL');
        }

        $collation = '';
        if (isset($fieldData['collation'])) {
            $collation .= ' CHARACTER SET utf8 COLLATE ' . $fieldData['collation'] . ' ';
        }

        return /** @lang */'ALTER TABLE `' . $tableName . '` CHANGE `' . $fieldName . '` `' . $fieldName . '` ' . $type . $collation . $nullable . $default;
    }

    public function getQueryForCreateIndex(string $tableName, string $indexName, array $fieldData) : string
    {
        return /** @lang */'ALTER TABLE `' . $tableName . '` ADD INDEX `' . $indexName . '` (`' . implode('`, `', $fieldData['fields']) . '`); ';
    }

    public function getQueryForCreateForeignKey(string $tableName, string $foreignKeyName, array $foreignKeyData) : string
    {
        return /** @lang */'ALTER TABLE `' . $tableName . '` ADD CONSTRAINT `' . $foreignKeyName . '` FOREIGN KEY (`' . $foreignKeyData['columnName'] . '`) REFERENCES `' . $foreignKeyData['referencedTable'] . '`(`' . $foreignKeyData['referencedColumnName'] . '`) ON DELETE ' . $foreignKeyData['deleteRule'] . ' ON UPDATE ' . $foreignKeyData['updateRule'];
    }

    public function getQueryForTurnOffForeignKeyCheck() : string
    {
        return /** @lang */'SET foreign_key_checks = 0';
    }

    public function getQueryForTurnOnForeignKeyCheckQuery() : string
    {
        return /** @lang */'SET foreign_key_checks = 1';
    }

    public function getQueryForTruncateTable($tableName) : string
    {
        return /** @lang */'TRUNCATE TABLE `' . $tableName . '`';
    }
}
