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
use Symfony\Component\Yaml\Yaml;

class OrmService
{
    const TABLE_INDEX_PREFIX = 'idx_';
    const TABLE_FOREIGN_KEY_PREFIX = 'fk_';
    const MIGRATION_TABLE_NAME = 'orm_migrations';

    private static EntityManager $entityManager;

    private static array $tableDefaultsSchema = [
        'encoding' => 'utf8',
        'collation' => 'utf8_general_ci',
        'engine' => 'InnoDB',
        'class' => null
    ];

    private static array $fieldPropertiesSchema = [
        'name' => null,
        'type' => null,
        'precision' => null,
        'scale' => null,
        'collation' => 'utf8_general_ci',
        'nullable' => false,
        'defaultValue' => null,
        'extra' => null,
        'entityClass' => null,
        'joiningField' => null,
        'lazy' => true
    ];

    private static array $tableIndexSchema = [
        'keyName' => null,
        'nonUnique' => '1',
        'packed' => null,
        'indexType' => 'BTREE',
        'fields' => [],
    ];

    private static array $foreignKeySchema = [
        'foreignKeyName' => null,
        'tableName' => null,
        'columnName' => null,
        'referencedTable' => null,
        'referencedColumnName' => null,
        'updateRule' => 'RESTRICT',
        'deleteRule' => 'RESTRICT',
    ];

    private static array $allowedFieldTypes = [
        'int',
        'tinyint',
        'smallint',
        'mediumint',
        'int',
        'bigint',
        'float',
        'double',
        'decimal',
        'varchar',
        'char',
        'tinytext',
        'text',
        'mediumtext',
        'longtext',
        'date',
        'datetime',
        'entity',
        'collection',
        'boolean',
    ];

    private static array $realSqlTypes = [
        'boolean' => 'tinyint',
        'entity' => 'int',
    ];

    private static array $fieldTypeDefaults = [
        'tinyint' => [
            'precision' => 4,
            'nullable' => false
        ],
        'smallint' => [
            'precision' => 6,
            'nullable' => false
        ],
        'mediumint' => [
            'precision' => 9,
            'nullable' => false
        ],
        'int' => [
            'precision' => 11,
            'nullable' => false
        ],
        'bigint' => [
            'precision' => 20,
            'nullable' => false
        ],
        'float' => [
            'nullable' => false
        ],
        'double' => [
            'nullable' => false
        ],
        'decimal' => [
            'precision' => 20,
            'scale' => 6,
            'nullable' => false
        ],
        'varchar' => [
            'precision' => 255,
            'collation' => 'utf8_general_ci',
            'nullable' => false
        ],
        'char' => [
            'precision' => 255,
            'collation' => 'utf8_general_ci',
            'nullable' => false
        ],
        'tinytext' => [
            'collation' => 'utf8_general_ci',
            'nullable' => false
        ],
        'text' => [
            'collation' => 'utf8_general_ci',
            'nullable' => false
        ],
        'mediumtext' => [
            'collation' => 'utf8_general_ci',
            'nullable' => false
        ],
        'longtext' => [
            'collation' => 'utf8_general_ci',
            'nullable' => false
        ],
        'entity' => [
            'nullable' => false
        ],
        'collection' => [
            'nullable' => false
        ],
        'date' => [
            'nullable' => false
        ],
        'datetime' => [
            'nullable' => false
        ],
    ];

    private static array $fieldTypeRequirements = [
        'entity' => ['entityClass'],
        'collection' => ['entityClass', 'joiningField'],
    ];

    public static function route(array $arguments)
    {
        $config = Yaml::parseFile($arguments[1]);

        $sqlAdapter = new $config['sqlAdapterClass']();
        self::$entityManager = EntityManager::create($sqlAdapter, $config);

        switch ($arguments[2]) {
            case 'generate':
                self::generateAction($config, $arguments);
                break;
            case 'migrate':
                self::migrateAction($config);
                break;
            case 'import':
                self::importAction($config, $arguments);
                break;
            default:
                throw new Exception('Unknown action: ' . $arguments[2]);
        }
    }

    private static function importAction(array $config, array $arguments)
    {
        switch ($arguments[3]) {
            case 'fixtures':
                self::importFixturesAction($config);
                break;
            default:
                throw new Exception('Unknown import argument: ' . $arguments[3]);
        }
    }

    private static function importFixturesAction(array $config) : void
    {
        $fixtureFiles = glob($config['fixtureDir'] . DIRECTORY_SEPARATOR . '*.yml');

        self::$entityManager->turnOffCheckForeignKeys();

        $fixtureFilesContents = [];
        foreach ($fixtureFiles as $fixtureFile) {
            $fixture = Yaml::parseFile($fixtureFile);
            $fixtureFilesContents[$fixtureFile] = $fixture;
            $repository = self::$entityManager->createRepository($fixture['fixture']['class']);
            self::$entityManager->truncateTable($repository->getTableName());
        }

        uasort($fixtureFilesContents, function($a, $b) {
            return ($a['fixture']['fixtureOrder'] >= $b['fixture']['fixtureOrder'])  ? 1 : -1;
        });

        foreach ($fixtureFilesContents as $fixture) {
            $factoryClass = $fixture['fixture']['factoryClass'];
            /** @var MigrationFactoryAbstract $factory */
            $factory = new $factoryClass(self::$entityManager);

            foreach ($fixture['fixture']['records'] as $record) {
                $object = $factory->createObject($record);
                $object->fromMigration = true;
                self::$entityManager->persist($object);
            }

            self::$entityManager->flush();
        }

        self::$entityManager->turnOnCheckForeignKeys();

        echo('import fixtures OK');
    }

    private static function generateAction(array $config, array $arguments)
    {
        switch ($arguments[3]) {
            case 'migration':
                self::generateMigrationAction($config);
                break;
//            case 'structure':
//                die('struktura bazy daych, z fixturami');
//                break;
            default:
                throw new Exception('Unknown generate argument: ' . $arguments[2]);
        }
    }

    private static function migrateAction(array $config) : void
    {
        $dbName = self::$entityManager->getDsnValue('dbname');
        $checkQuery = /** @lang */'SELECT count(*) FROM information_schema.TABLES WHERE (TABLE_SCHEMA = "' . $dbName . '") AND (TABLE_NAME = "' . self::MIGRATION_TABLE_NAME . '")';
        $migrationTableCount = self::$entityManager->getDbConnection()->getValue($checkQuery);

        if ($migrationTableCount == 0) {
            $query = /** @lang */'CREATE TABLE `' . self::MIGRATION_TABLE_NAME . '` (
                          `id` int(11) NOT NULL AUTO_INCREMENT,
                          `migrationName` varchar(64) COLLATE utf8_polish_ci NOT NULL,
                          `migrationDate` datetime DEFAULT NULL,
                          PRIMARY KEY (`id`),
                          UNIQUE KEY `migrationName` (`migrationName`) USING BTREE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_polish_ci';

            self::$entityManager->getDbConnection()->executeQuery($query);
        }

        $migrationFiles = glob($config['migrationDir'] . DIRECTORY_SEPARATOR . 'Migration*.php');
        foreach ($migrationFiles as $file) {
            $migrationClassName = pathinfo($file, PATHINFO_FILENAME );

            $query = /** @lang */'SELECT COUNT(id) FROM ' . self::MIGRATION_TABLE_NAME . ' WHERE migrationName="' . $migrationClassName . '"';
            $importedCount = self::$entityManager->getDbConnection()->getValue($query);

            if ($importedCount == 0) {
                require_once $file;
                /** @var AbstractMigration $migrationClass */
                $migrationClass = new $migrationClassName(self::$entityManager);
                $migrationClass->upVersion();

                $query = /** @lang */'INSERT INTO ' . self::MIGRATION_TABLE_NAME . ' SET migrationName="' . $migrationClassName . '", migrationDate = NOW()';
                self::$entityManager->getDbConnection()->executeQuery($query);

            }
        }

        echo('migrate OK');
    }

    private static function generateMigrationAction(array $config) : void
    {
        $ormStructure = self::getOrmStructure();
        $dbTablesStructure = self::getDbStructure();

        $tablesToCreate = self::findTablesToCreate($ormStructure, $dbTablesStructure);
        $tablesToRemove = self::findTablesToRemove($ormStructure, $dbTablesStructure);
        $fieldsToCreate = self::findFieldsToCreate($ormStructure, $dbTablesStructure, array_keys($tablesToCreate));
        $fieldsToUpdate = self::findFieldsToUpdate($ormStructure, $dbTablesStructure, array_keys($tablesToCreate), array_keys($tablesToRemove));
        $fieldsToRemove = self::findFieldsToRemove($ormStructure, $dbTablesStructure, array_keys($tablesToCreate), array_keys($tablesToRemove));
        $indexesToCreate = self::findIndexesToCreate($ormStructure, $dbTablesStructure, array_keys($tablesToCreate), array_keys($tablesToRemove));
        $indexesToUpdate = self::findIndexesToUpdate($ormStructure, $dbTablesStructure, array_keys($tablesToCreate), array_keys($tablesToRemove));
        $indexesToRemove = self::findIndexesToRemove($ormStructure, $dbTablesStructure, $tablesToRemove);
        $foreignKeysToCreate = self::findForeignKeysToCreate($ormStructure, $dbTablesStructure, array_keys($tablesToRemove));
        $foreignKeysToUpdate = self::findForeignKeysToUpdate($ormStructure, $dbTablesStructure, array_keys($tablesToCreate), array_keys($tablesToRemove));
        $foreignKeysToRemove = self::findForeignKeysToRemove($ormStructure, $dbTablesStructure);

        $migrationQueries = [];
        $migrationQueries = array_merge($migrationQueries, self::getQueriesForRemoveForeignKeys($foreignKeysToRemove));
        $migrationQueries = array_merge($migrationQueries, self::getQueriesForRemoveIndexes($indexesToRemove));
        $migrationQueries = array_merge($migrationQueries, self::getQueriesForRemoveFields($fieldsToRemove));
        $migrationQueries = array_merge($migrationQueries, self::getQueriesForRemoveTables($tablesToRemove));
        $migrationQueries = array_merge($migrationQueries, self::getQueriesForCreateTables($tablesToCreate));
        $migrationQueries = array_merge($migrationQueries, self::getQueriesForCreateFields($fieldsToCreate));
        $migrationQueries = array_merge($migrationQueries, self::getQueriesForUpdateFields($fieldsToUpdate));
        $migrationQueries = array_merge($migrationQueries, self::getQueriesForCreateIndexes($indexesToCreate));
        $migrationQueries = array_merge($migrationQueries, self::getQueriesForUpdateIndexes($indexesToUpdate));
        $migrationQueries = array_merge($migrationQueries, self::getQueriesForCreateForeignKeys($foreignKeysToCreate));
        $migrationQueries = array_merge($migrationQueries, self::getQueriesForUpdateForeignKeys($foreignKeysToUpdate));

        if (!empty($migrationQueries)) {
            $migrationClassName = 'Migration' . time();
            $fileContent = '<?php' . PHP_EOL;
            $fileContent .= 'use mstodulski\database\AbstractMigration;' . PHP_EOL . PHP_EOL;
            $fileContent .= 'final class ' . $migrationClassName . ' extends AbstractMigration {' . PHP_EOL . PHP_EOL;
            $fileContent .= '    public function upVersion()' . PHP_EOL;
            $fileContent .= '    {' . PHP_EOL;
            foreach ($migrationQueries as $query) {
                $fileContent .= '        $this->executeQuery("' . $query . '");' . PHP_EOL;
            }

            $fileContent .= '    }' . PHP_EOL;
            $fileContent .= '}' . PHP_EOL;

            if (!is_dir($config['migrationDir'])) {
                mkdir($config['migrationDir'], 0777, true);
            }

            file_put_contents($config['migrationDir'] . DIRECTORY_SEPARATOR . $migrationClassName . '.php', $fileContent);
        } else {
            echo 'Db structure is up to date. No migrations created.';
        }
    }

    private static function findForeignKeysToRemove($ormStructure, $dbTablesStructure): array
    {
        $foreignKeysToRemove = [];

        foreach ($dbTablesStructure as $dbTableName => $dbTableData) {
            foreach ($dbTableData['foreignKeys'] as $dbIndexName => $dbIndexData) {
                if (!isset($ormStructure[$dbTableName]['foreignKeys'][$dbIndexName])) {
                    $foreignKeysToRemove[$dbTableName][$dbIndexName] = $dbIndexName;
                }
            }
        }

        return $foreignKeysToRemove;
    }

    private static function findForeignKeysToUpdate($ormStructure, $dbTablesStructure, $tablesToCreate, $tablesToRemove): array
    {
        $foreignKeysToUpdate = [];

        foreach ($ormStructure as $ormTableName => $ormTableData) {
            if (in_array($ormTableName, $tablesToCreate) || in_array($ormTableName, $tablesToRemove)) {
                continue;
            }

            if (isset($ormTableData['foreignKeys']) && is_array($ormTableData['foreignKeys'])) {
                foreach ($ormTableData['foreignKeys'] as $ormForeignKeyName => $ormForeignKeyData) {
                    if (!isset($dbTablesStructure[$ormTableName]['foreignKeys'][$ormForeignKeyName])) {
                        continue;
                    }

                    $ormFieldChecksum = md5(json_encode($ormForeignKeyData));
                    $dbFieldChecksum = md5(json_encode($dbTablesStructure[$ormTableName]['foreignKeys'][$ormForeignKeyName]));

                    if ($ormFieldChecksum != $dbFieldChecksum) {
                        $foreignKeysToUpdate[$ormTableName][$ormForeignKeyName] = $ormForeignKeyData;
                    }
                }
            }
        }

        return $foreignKeysToUpdate;
    }

    private static function findForeignKeysToCreate($ormStructure, $dbTablesStructure, $tablesToRemove): array
    {
        $foreignKeysToCreate = [];
        foreach ($ormStructure as $ormTableName => $ormTableData) {
            if (in_array($ormTableName, $tablesToRemove)) {
                continue;
            }

            if (isset($ormTableData['foreignKeys']) && is_array($ormTableData['foreignKeys'])) {
                foreach ($ormTableData['foreignKeys'] as $ormForeignKeyName => $ormForeignKeyData) {
                    if (!isset($dbTablesStructure[$ormTableName]['foreignKeys'][$ormForeignKeyName])) {
                        $foreignKeysToCreate[$ormTableName][$ormForeignKeyName] = $ormForeignKeyData;
                    }
                }
            }
        }

        return $foreignKeysToCreate;
    }

    private static function findIndexesToRemove($ormStructure, $dbTablesStructure, $tablesToRemove): array
    {
        $indexesToRemove = [];

        foreach ($dbTablesStructure as $dbTableName => $dbTableData) {
            if (in_array($dbTableName, $tablesToRemove) || ($dbTableName == self::MIGRATION_TABLE_NAME)) {
                continue;
            }

            foreach ($dbTableData['tableIndexes'] as $dbIndexName => $dbIndexData) {
                if (!isset($ormStructure[$dbTableName]['tableIndexes'][$dbIndexName])) {
                    $indexesToRemove[$dbTableName][$dbIndexName] = $dbIndexName;
                }
            }
        }

        return $indexesToRemove;
    }

    private static function findIndexesToUpdate($ormStructure, $dbTablesStructure, $tablesToCreate, $tablesToRemove): array
    {
        $indexesToUpdate = [];

        foreach ($ormStructure as $ormTableName => $ormTableData) {
            if (in_array($ormTableName, $tablesToCreate) || in_array($ormTableName, $tablesToRemove)) {
                continue;
            }

            if (isset($ormTableData['tableIndexes']) && is_array($ormTableData['tableIndexes'])) {
                foreach ($ormTableData['tableIndexes'] as $ormIndexName => $ormIndexData) {
                    if (!isset($dbTablesStructure[$ormTableName]['tableIndexes'][$ormIndexName])) {
                        continue;
                    }

                    $ormFieldChecksum = md5(json_encode($ormIndexData));
                    $dbFieldChecksum = md5(json_encode($dbTablesStructure[$ormTableName]['tableIndexes'][$ormIndexName]));

                    if ($ormFieldChecksum != $dbFieldChecksum) {
                        $indexesToUpdate[$ormTableName][$ormIndexName] = $ormIndexData;

                        foreach ($dbTablesStructure[$ormTableName]['tableIndexes'][$ormIndexName] as $property => $value) {
                            if ($indexesToUpdate[$ormTableName][$ormIndexName][$property] == $value) {
                                unset($indexesToUpdate[$ormTableName][$ormIndexName][$property]);
                            }
                        }
                    }
                }
            }
        }

        return $indexesToUpdate;
    }


    private static function findIndexesToCreate($ormStructure, $dbTablesStructure, $tablesToCreate, $tablesToRemove): array
    {
        $indexesToCreate = [];
        foreach ($ormStructure as $ormTableName => $ormTableData) {
            if (in_array($ormTableName, $tablesToRemove)) {
                continue;
            }

            if (isset($ormTableData['tableIndexes']) && is_array($ormTableData['tableIndexes'])) {
                foreach ($ormTableData['tableIndexes'] as $ormIndexName => $ormIndexData) {

                    if (
                        !isset($dbTablesStructure[$ormTableName]['tableIndexes'][$ormIndexName]) &&
                        (!(($ormIndexData['keyName'] == 'PRIMARY') && in_array($ormTableName, $tablesToCreate)))
                    ) {
                        $indexesToCreate[$ormTableName][$ormIndexName] = $ormIndexData;
                    }
                }
            }
        }

        return $indexesToCreate;
    }


    private static function findFieldsToRemove($ormStructure, $dbTablesStructure, $tablesToCreate, $tablesToRemove): array
    {
        $fieldsToRemove = [];

        foreach ($dbTablesStructure as $dbTableName => $dbTableData) {
            if (in_array($dbTableName, $tablesToCreate) || in_array($dbTableName, $tablesToRemove) || ($dbTableName == self::MIGRATION_TABLE_NAME)) {
                continue;
            }

            foreach ($dbTableData['fields'] as $dbFieldName => $dbFieldData) {
                if (!isset($ormStructure[$dbTableName]['fields'][$dbFieldName])) {
                    $fieldsToRemove[$dbTableName][$dbFieldName] = $dbFieldName;
                }
            }
        }

        return $fieldsToRemove;
    }

    private static function findFieldsToUpdate($ormStructure, $dbTablesStructure, $tablesToCreate, $tablesToRemove): array
    {
        $fieldsToUpdate = [];

        foreach ($ormStructure as $ormTableName => $ormTableData) {
            if (in_array($ormTableName, $tablesToCreate) || in_array($ormTableName, $tablesToRemove)) {
                continue;
            }

            foreach ($ormTableData['fields'] as $ormFieldName => $ormFieldData) {

                if (!isset($dbTablesStructure[$ormTableName]['fields'][$ormFieldName])) {
                    continue;
                }

                $ormFieldChecksum = md5(json_encode($ormFieldData));
                $dbFieldChecksum = md5(json_encode($dbTablesStructure[$ormTableName]['fields'][$ormFieldName]));

                if ($ormFieldChecksum != $dbFieldChecksum) {
                    $fieldsToUpdate[$ormTableName][$ormFieldName] = $ormFieldData;
                }
            }
        }

        return $fieldsToUpdate;
    }

    private static function findFieldsToCreate($ormStructure, $dbTablesStructure, $tablesToCreate): array
    {
        $fieldsToCreate = [];
        foreach ($ormStructure as $ormTableName => $ormTableData) {
            if (in_array($ormTableName, $tablesToCreate)) {
                continue;
            }

            foreach ($ormTableData['fields'] as $ormFieldName => $ormFieldData) {
                if (!isset($dbTablesStructure[$ormTableName]['fields'][$ormFieldName])) {
                    $fieldsToCreate[$ormTableName][$ormFieldName] = $ormFieldData;
                }
            }
        }

        return $fieldsToCreate;
    }

    private static function findTablesToCreate($ormStructure, $dbTablesStructure): array
    {
        $tablesToCreate = [];
        foreach (array_keys($ormStructure) as $ormTableName) {
            if (!in_array($ormTableName, array_keys($dbTablesStructure))) {
                $tablesToCreate[$ormTableName] = $ormStructure[$ormTableName];
            }
        }

        return $tablesToCreate;
    }

    private static function findTablesToRemove($ormStructure, $dbTablesStructure): array
    {
        $tablesToRemove = [];
        foreach (array_keys($dbTablesStructure) as $dbTableName) {
            if (!in_array($dbTableName, array_keys($ormStructure))) {
                $tablesToRemove[$dbTableName] = $dbTableName;
            }
        }

        if (isset($tablesToRemove[self::MIGRATION_TABLE_NAME])) {
            unset($tablesToRemove[self::MIGRATION_TABLE_NAME]);
        }

        return $tablesToRemove;
    }

    private static function createDefaultKeyName(string $tableName, array $tableIndexData): string
    {
        return self::TABLE_INDEX_PREFIX . substr(md5($tableName . json_encode($tableIndexData['fields'])), 0, 16);
    }

    private static function createDefaultForeignKeyName(array $foreignKeyData): string
    {
        $foreignKeySimpleData = $foreignKeyData;
        unset($foreignKeySimpleData['updateRule']);
        unset($foreignKeySimpleData['deleteRule']);

        return self::TABLE_FOREIGN_KEY_PREFIX . substr(md5(json_encode($foreignKeySimpleData)), 0, 16);
    }

    private static function getOrmStructure(): array
    {
        $ormStructure = [];
        $ormFiles = glob(self::$entityManager->getEntityConfigurationDir() . DIRECTORY_SEPARATOR . '*.orm.yml');
        foreach ($ormFiles as $ormFile) {
            $config = Yaml::parseFile($ormFile);

            $foreignKeyIndexes = [];
            $foreignKeysForEntityFields = [];
            $repository = self::$entityManager->createRepository($config['entity']);

            $tableStructure = [];
            $tableStructure['tableDefaults'] = self::$tableDefaultsSchema;
            $tableStructure['tableDefaults']['encoding'] = (isset($config['encoding'])) ? $config['encoding'] : self::$tableDefaultsSchema['encoding'];
            $tableStructure['tableDefaults']['collation'] = (isset($config['collation'])) ? $config['collation'] : self::$tableDefaultsSchema['collation'];
            $tableStructure['tableDefaults']['engine'] = (isset($config['engine'])) ? $config['engine'] : self::$tableDefaultsSchema['engine'];
            $tableStructure['tableDefaults']['class'] = $config['entity'];

            $primaryKey = null;
            foreach ($config['fields'] as $fieldName => $fieldProperties) {
                if ($fieldProperties['type'] == 'collection') {
                    continue;
                }

                if (isset($fieldProperties['id']) && ($fieldProperties['id'] === true)) {
                    $primaryKey = self::$tableIndexSchema;
                    $primaryKey['keyName'] = 'PRIMARY';
                    $primaryKey['nonUnique'] = '0';
                    $primaryKey['packed'] = null;
                    $primaryKey['indexType'] = 'BTREE';
                    $primaryKey['fields'] = ['id'];
                }

                if ($fieldProperties['type'] == 'entity') {
                    $foreignKeyIndex = self::$tableIndexSchema;
                    $foreignKeyIndex['fields'] = [$fieldName];
                    $defaultKeyName = self::createDefaultKeyName($repository->getTableName(), $foreignKeyIndex);
                    $foreignKeyIndex['keyName'] = $defaultKeyName;

                    $foreignKeyIndexes[$defaultKeyName] = $foreignKeyIndex;

                    $repositoryFK = self::$entityManager->createRepository($fieldProperties['entityClass']);
                    $configFK = self::$entityManager->loadClassConfiguration($fieldProperties['entityClass']);
                    $idFieldNameFK = ObjectMapper::getIdFieldName($configFK);

                    $foreignKey = self::$foreignKeySchema;
                    $foreignKey['tableName'] = $repository->getTableName();
                    $foreignKey['columnName'] = $fieldName;
                    $foreignKey['referencedTable'] = $repositoryFK->getTableName();
                    $foreignKey['referencedColumnName'] = $idFieldNameFK;

                    $defaultForeignKeyName = self::createDefaultForeignKeyName($foreignKey);
                    $foreignKey['foreignKeyName'] = $defaultForeignKeyName;

                    $foreignKeysForEntityFields[$defaultForeignKeyName] = $foreignKey;
                }

                $tableStructure['fields'][$fieldName] = self::$fieldPropertiesSchema;
                $tableStructure['fields'][$fieldName]['name'] = $fieldName;

                if (!isset($fieldProperties['type'])) {
                    throw new Exception('You must specify field type - field ' . $fieldName . ', file ' . $ormFile);
                }

                if (isset(self::$realSqlTypes[$fieldProperties['type']])) {
                    $fieldType = self::$realSqlTypes[$fieldProperties['type']];
                    $fieldProperties['type'] = $fieldType;
                } else {
                    $fieldType = $fieldProperties['type'];
                }

                if (!in_array($fieldType, self::$allowedFieldTypes)) {
                    throw new Exception('"' . $fieldType . '" field type not allowed - field ' . $fieldName . ', file ' . $ormFile);
                }

                $tableStructure['fields'][$fieldName]['type'] = $fieldType;

                foreach (array_keys(self::$fieldPropertiesSchema) as $fieldProperty) {
                    if (isset(self::$fieldTypeRequirements[$fieldType][$fieldProperty]) && (!isset($fieldProperties[$fieldProperty]))) {
                        throw new Exception('Property ' . $fieldProperty . ' in field ' . $fieldName . ' in file ' . $ormFile . ' is required.');
                    }
                    $tableStructure['fields'][$fieldName][$fieldProperty] = ($fieldProperties[$fieldProperty] ?? (self::$fieldTypeDefaults[$fieldType][$fieldProperty] ?? null));
                }

                if ($tableStructure['fields'][$fieldName]['type'] == 'tinyint') {
                    if (is_bool($tableStructure['fields'][$fieldName]['defaultValue'])) {
                        $tableStructure['fields'][$fieldName]['defaultValue'] = (string)((int)$tableStructure['fields'][$fieldName]['defaultValue']);
                    }
                }
            }

            if ($primaryKey !== null) {
                $tableStructure['tableIndexes'][$primaryKey['keyName']] = $primaryKey;
            }

            if (!empty($foreignKeyIndexes)) {
                foreach ($foreignKeyIndexes as $keyName => $foreignKeyIndexData) {
                    $tableStructure['tableIndexes'][$keyName] = $foreignKeyIndexData;
                }
            }

            if (isset($config['tableIndexes'])) {
                foreach ($config['tableIndexes'] as $tableIndexData) {

                    foreach ($tableIndexData['fields'] as $tableIndexField) {
                        if (!isset($tableStructure['fields'][$tableIndexField])) {
                            throw new Exception('Field ' . $tableIndexField . ' does not exist in table to be used for table index.');
                        }
                    }

                    $defaultKeyName = self::createDefaultKeyName($repository->getTableName(), $tableIndexData);

                    $keyName = ($tableIndexData['keyName'] ?? $defaultKeyName);
                    $tableIndexData['keyName'] = $keyName;
                    $tableStructure['tableIndexes'][$keyName] = self::$tableIndexSchema;

                    foreach ($tableStructure['tableIndexes'][$keyName] as $propertyName => $propertyValue) {
                        if (isset($tableIndexData[$propertyName])) {
                            $tableStructure['tableIndexes'][$keyName][$propertyName] = $tableIndexData[$propertyName];
                        }
                    }
                }
            }

            if (!empty($foreignKeysForEntityFields)) {
                foreach ($foreignKeysForEntityFields as $foreignKeyName => $foreignKeyData) {
                    $tableStructure['foreignKeys'][$foreignKeyName] = $foreignKeyData;
                }
            }

            if (isset($config['foreignKeys'])) {
                foreach ($config['foreignKeys'] as $foreignKeyData) {

                    if (!isset($foreignKeyData['columnName'])) {
                        throw new Exception('Property "columnName" in foreignKeys in file ' . $ormFile . ' is required.');
                    } elseif (!isset($foreignKeyData['referencedColumnName'])) {
                        throw new Exception('Property "referencedColumnName" in foreignKeys in file ' . $ormFile . ' is required.');
                    }

                    $foreignKey = self::$foreignKeySchema;
                    foreach ($foreignKey as $propertyName => $propertyValue) {
                        if ($propertyName == 'referencedTable') {
                            $foreignKeyRepository = self::$entityManager->createRepository($tableStructure['fields'][$foreignKeyData['columnName']]['entityClass']);
                            $foreignKey[$propertyName] = $foreignKeyRepository->getTableName();
                        } elseif ($propertyName == 'tableName') {
                            $foreignKey[$propertyName] = $repository->getTableName();
                        } else {
                            if (isset($foreignKeyData[$propertyName])) {
                                $foreignKey[$propertyName] = $foreignKeyData[$propertyName];
                            }
                        }
                    }

                    $defaultForeignKeyName = self::createDefaultForeignKeyName($foreignKey);
                    $foreignKeyName = ($foreignKeyData['keyName'] ?? $defaultForeignKeyName);
                    $foreignKey['foreignKeyName'] = $foreignKeyName;
                    $tableStructure['foreignKeys'][$foreignKeyName] = $foreignKey;
                }
            }

            foreach ($tableStructure['fields'] as $fieldName => $field) {
                unset($tableStructure['fields'][$fieldName]['entityClass']);
                unset($tableStructure['fields'][$fieldName]['joiningField']);
                unset($tableStructure['fields'][$fieldName]['lazy']);
            }

            $ormStructure[$repository->getTableName()] = $tableStructure;
        }

        return $ormStructure;
    }

    private static function getDbStructure(): array
    {
        $tablesArray = self::$entityManager->getTablesFromDb();
        $dbForeignKeys = self::getDbForeignKeys();

        $tables = [];
        if (!empty($tablesArray)) {
            foreach ($tablesArray as $tableData) {
                $fieldName = 'Tables_in_' . self::$entityManager->getDsnValue('dbname');

                $tables[$tableData[$fieldName]]['tableDefaults'] = self::getDbTablesDetails($tableData[$fieldName]);

                $tableFields = self::getTableFields($tableData[$fieldName]);
                $tables[$tableData[$fieldName]]['fields'] = [];
                foreach ($tableFields as $tableField) {

                    $translatedTableField = self::$fieldPropertiesSchema;
                    $translatedTableField['name'] = null;

                    $type = null;
                    $precision = null;
                    $scale = null;
                    if (str_contains($tableField['type'], '(')) {
                        $typeParts = explode('(', $tableField['type']);
                        $type = $typeParts[0];
                        $precisionAndScale = $typeParts[1];
                        $precisionAndScale = trim($precisionAndScale, ')');

                        if (str_contains($precisionAndScale, ',')) {
                            $precisionAndScaleParts = explode(',', $precisionAndScale);

                            $precision = (int)trim($precisionAndScaleParts[0]);
                            $scale = (int)trim($precisionAndScaleParts[1]);
                        } else {
                            $precision = (int)$precisionAndScale;
                        }
                    } else {
                        $type = $tableField['type'];
                    }

                    $translatedTableField['type'] = $type;
                    $translatedTableField['precision'] = $precision;
                    $translatedTableField['scale'] = $scale;
                    $translatedTableField['collation'] = $tableField['collation'];
                    $translatedTableField['nullable'] = ($tableField['nullable'] == 'YES');
                    $translatedTableField['defaultValue'] = $tableField['defaultValue'];
                    $translatedTableField['extra'] = ($tableField['extra'] != '') ? $tableField['extra'] : null;

                    unset($translatedTableField['entityClass']);
                    unset($translatedTableField['joiningField']);
                    unset($translatedTableField['lazy']);

                    $tables[$tableData[$fieldName]]['fields'][$tableField['name']] = $translatedTableField;
                }

                $tableIndexes = self::getDbTablesIndexes($tableData[$fieldName]);
                $tables[$tableData[$fieldName]]['tableIndexes'] = [];
                foreach ($tableIndexes as $tableIndex) {
                    $translatedTableIndex = self::$tableIndexSchema;
                    $translatedTableIndex['keyName'] = $tableIndex['Key_name'];
                    $translatedTableIndex['nonUnique'] = $tableIndex['Non_unique'];
                    $translatedTableIndex['packed'] = $tableIndex['Packed'];
                    $translatedTableIndex['indexType'] = $tableIndex['Index_type'];
                    $translatedTableIndex['fields'] = explode(',', $tableIndex['Column_name']);
                    $tables[$tableData[$fieldName]]['tableIndexes'][$tableIndex['Key_name']] = $translatedTableIndex;
                }

                $tables[$tableData[$fieldName]]['foreignKeys'] = [];
                foreach ($dbForeignKeys as $dbForeignKey) {
                    if ($dbForeignKey['tableName'] == $tableData[$fieldName]) {
                        $translatedTableForeignKey = self::$foreignKeySchema;
                        $translatedTableForeignKey['foreignKeyName'] = $dbForeignKey['keyName'];
                        $translatedTableForeignKey['columnName'] = $dbForeignKey['columnName'];
                        $translatedTableForeignKey['referencedTable'] = $dbForeignKey['referencedTable'];
                        $translatedTableForeignKey['referencedColumnName'] = $dbForeignKey['referencedColumn'];
                        $translatedTableForeignKey['updateRule'] = $dbForeignKey['updateRule'];
                        $translatedTableForeignKey['deleteRule'] = $dbForeignKey['deleteRule'];
                        $translatedTableForeignKey['tableName'] = $dbForeignKey['tableName'];

                        $tables[$tableData[$fieldName]]['foreignKeys'][$dbForeignKey['keyName']] = $translatedTableForeignKey;
                    }
                }
            }
        }

        return $tables;
    }

    public static function getTableFields(string $tableName): array
    {
        return self::$entityManager->getTableFieldsFromDb(
            $tableName,
            self::$entityManager->getDsnValue('dbname')
        );
    }

    public static function getDbTablesIndexes(string $tableName): array
    {
        return self::$entityManager->getTableIndexesFromDb($tableName);
    }

    public static function getDbForeignKeys(): array
    {
        return self::$entityManager->getDbForeignKeys(self::$entityManager->getDsnValue('dbname'));
    }

    public static function getDbTablesDetails(string $tableName): array
    {
        return self::$entityManager->getDbTableDetailsFromDb(
            $tableName,
            self::$entityManager->getDsnValue('dbname')
        );
    }

    ///----------------------------------------------
    
    private static function getQueriesForRemoveForeignKeys(array $foreignKeys) : array
    {
        $entityManager = self::$entityManager;
        $queries = [];
        foreach ($foreignKeys as $tableName => $foreignKeyData) {
            foreach ($foreignKeyData as $foreignKey) {
                $queries[] = $entityManager::$dbAdapter->getQueryForRemoveForeignKey($tableName, $foreignKey);
            }
        }
        
        return $queries;
    }

    private static function getQueriesForRemoveIndexes(array $indexes) : array
    {
        $entityManager = self::$entityManager;
        $queries = [];
        foreach ($indexes as $tableName => $indexData) {
            foreach ($indexData as $index) {
                $queries[] = $entityManager::$dbAdapter->getQueryForRemoveIndex($tableName, $index);
            }
        }

        return $queries;
    }

    private static function getQueriesForRemoveFields(array $fields) : array
    {
        $entityManager = self::$entityManager;
        $queries = [];
        foreach ($fields as $tableName => $fieldData) {
            foreach ($fieldData as $fieldName) {
                $queries[] = $entityManager::$dbAdapter->getQueryForRemoveField($tableName, $fieldName);
            }
        }

        return $queries;
    }

    private static function getQueriesForRemoveTables(array $tables) : array
    {
        $entityManager = self::$entityManager;
        $queries = [];
        foreach ($tables as $tableName) {
            $queries[] = $entityManager::$dbAdapter->getQueryForRemoveTable($tableName);
        }

        return $queries;
    }

    private static function getQueriesForCreateTables(array $tables) : array
    {
        $entityManager = self::$entityManager;
        $queries = [];
        foreach ($tables as $tableName => $tableData) {
            $config = self::$entityManager->loadClassConfiguration($tableData['tableDefaults']['class']);
            $idFieldName = ObjectMapper::getIdFieldName($config);
            $queries[] = $entityManager::$dbAdapter->getQueryForCreateTable($tableName, $idFieldName, $tableData);
        }

        return $queries;
    }

    private static function getQueriesForCreateFields(array $fields) : array
    {
        $entityManager = self::$entityManager;
        $queries = [];
        foreach ($fields as $tableName => $fieldsData) {
            foreach ($fieldsData as $fieldName => $fieldData) {
                $queries[] = $entityManager::$dbAdapter->getQueryForCreateField($tableName, $fieldName, $fieldData);
            }
        }

        return $queries;
    }

    private static function getQueriesForUpdateFields(array $fields) : array
    {
        $entityManager = self::$entityManager;
        $queries = [];
        foreach ($fields as $tableName => $fieldsData) {
            foreach ($fieldsData as $fieldName => $fieldData) {
                $queries[] = $entityManager::$dbAdapter->getQueryForUpdateField($tableName, $fieldName, $fieldData);
            }
        }

        return $queries;
    }

    private static function getQueriesForCreateIndexes(array $indexes) : array
    {
        $entityManager = self::$entityManager;
        $queries = [];
        foreach ($indexes as $tableName => $indexesData) {
            foreach ($indexesData as $indexName => $indexData) {
                $queries[] = $entityManager::$dbAdapter->getQueryForCreateIndex($tableName, $indexName, $indexData);
            }
        }

        return $queries;
    }

    private static function getQueriesForUpdateIndexes(array $indexes) : array
    {
        $entityManager = self::$entityManager;
        $queries = [];

        foreach ($indexes as $tableName => $indexesData) {
            foreach ($indexesData as $indexName => $indexData) {
                $queries[] = $entityManager::$dbAdapter->getQueryForRemoveIndex($tableName, $indexName);
            }
        }

        foreach ($indexes as $tableName => $indexesData) {
            foreach ($indexesData as $indexName => $indexData) {
                $queries[] = $entityManager::$dbAdapter->getQueryForCreateIndex($tableName, $indexName, $indexData);
            }
        }

        return $queries;
    }

    private static function getQueriesForCreateForeignKeys(array $foreignKeys) : array
    {
        $entityManager = self::$entityManager;
        $queries = [];
        foreach ($foreignKeys as $tableName => $foreignKeysData) {
            foreach ($foreignKeysData as $foreignKeyName => $foreignKeyData) {
                $queries[] = $entityManager::$dbAdapter->getQueryForCreateForeignKey($tableName, $foreignKeyName, $foreignKeyData);
            }
        }

        return $queries;
    }

    private static function getQueriesForUpdateForeignKeys(array $foreignKeys) : array
    {
        $entityManager = self::$entityManager;
        $queries = [];

        foreach ($foreignKeys as $tableName => $foreignKeysData) {
            foreach ($foreignKeysData as $foreignKeyName => $foreignKeyData) {
                $queries[] = $entityManager::$dbAdapter->getQueryForRemoveForeignKey($tableName, $foreignKeyName);
            }
        }

        foreach ($foreignKeys as $tableName => $foreignKeysData) {
            foreach ($foreignKeysData as $foreignKeyName => $foreignKeyData) {
                $queries[] = $entityManager::$dbAdapter->getQueryForCreateForeignKey($tableName, $foreignKeyName, $foreignKeyData);
            }
        }

        return $queries;
    }

}
