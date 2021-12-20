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
use mstodulski\cache\Cache;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use Throwable;

class EntityManager
{
    private DBConnection $dbConnection;
    private string $entityConfigurationDir;
    private string $mode;
    private array $entitiesToSave = [];
    private array $collectionEntitiesToSave = [];
    private array $entitiesToRemove = [];
    private array $classesConfigurations = [];
    private array $priorityArray = [];

    public static ?DbAdapterInterface $dbAdapter;
    private static array $config;

    public static function create(DbAdapterInterface $dbAdapter = null, array $config = []) : self
    {
        if ($dbAdapter !== null) {
            self::$dbAdapter = $dbAdapter;
            self::$config = $config;
        }

        $entityManager = new self();

        $entityManager->dbConnection = new DBConnection(self::$dbAdapter, self::$config['dsn'], self::$config['user'], self::$config['password'], $entityManager);
        $entityManager->entityConfigurationDir = (isset(self::$config['entityConfigurationDir'])) ? self::$config['entityConfigurationDir'] : '';
        $entityManager->mode = (isset(self::$config['mode'])) ? self::$config['mode'] : 'prod';

        if (!in_array($entityManager->mode, ['dev', 'prod'])) {
            throw  new Exception('Mode can be only [dev, prod]');
        }

        return $entityManager;
    }

    public function __construct()
    {
        if (self::$dbAdapter === null) {
            throw new Exception('Please use create() method with configuration arguments');
        }

        $this->dbConnection = new DBConnection(self::$dbAdapter, self::$config['dsn'], self::$config['user'], self::$config['password'], $this);
        $this->entityConfigurationDir = (isset(self::$config['entityConfigurationDir'])) ? self::$config['entityConfigurationDir'] : '';
        $this->mode = (isset(self::$config['mode'])) ? self::$config['mode'] : 'prod';
    }

    public function loadClassConfiguration(string $className)
    {
        $reflection = new ReflectionClass($className);
        $shortClassName = $reflection->getShortName();
        $filePath = $this->entityConfigurationDir . $shortClassName . '.orm.yml';

        if (!file_exists($filePath)) {
            throw new Exception('Configuration orm file ' . $filePath . ' does not exists.');
        }

        $variableName = 'config/orm/' . $className;

        switch ($this->mode) {
            case 'dev':
                if (isset($this->classesConfigurations[$variableName])) {
                    return $this->classesConfigurations[$variableName];
                } else {
                    $entityConfiguration = Yaml::parseFile($filePath);
                    $entityConfiguration['filePath'] = realpath($reflection->getFileName());
                    $entityConfiguration['configFilePath'] = realpath($filePath);
                    $this->classesConfigurations[$variableName] = $entityConfiguration;

                    return $entityConfiguration;
                }
            case 'prod':
                if (Cache::checkIfVariableExistsInCache($variableName)) {
                    return Cache::getVariableValueFromCache($variableName);
                } else {
                    $entityConfiguration = Yaml::parseFile($filePath);
                    $entityConfiguration['filePath'] = realpath($reflection->getFileName());
                    $entityConfiguration['configFilePath'] = realpath($filePath);
                    Cache::setVariableValueInCache($variableName, $entityConfiguration, 600);

                    return $entityConfiguration;
                }
        }

        throw new Exception('Unexpected EntityManager mode: ' . $this->mode);
    }

    public function createRepository(string $className) : Repository
    {
        $entityConfiguration = $this->loadClassConfiguration($className);

        if (isset($entityConfiguration['repository'])) {
            $repositoryClassName = $entityConfiguration['repository'];
            return new $repositoryClassName($className, $this);
        } else {
            throw new Exception('Repository field not defined in configuration orm file for class ' . $className);
        }
    }

    public function createQueryBuilder(string $className, ?string $alias = null) : QueryBuilder
    {
        $tableName = Repository::createTableNameFromEntityClass($className);

        $queryBuilder = new QueryBuilder($this, $className);
        $queryBuilder->from($tableName, $alias);

        return $queryBuilder;
    }

    public function find(string $className, int $id, HydrationMode $hydrationMode = HydrationMode::Object) : object|array|null
    {
        $adapter = $this->dbConnection->getDbAdapter();

        $queryBuilder = $this->createQueryBuilder($className);
        $queryBuilder->addWhere(new QueryCondition('id = :id', $id, QueryCondition::PARAMETER_TYPE_INT));
        $query = $adapter->getSelectQuery($queryBuilder);

        $parameters = [];
        /** @var QueryCondition $queryCondition */
        foreach ($queryBuilder->getWhere() as $queryCondition) {
            $parameters = array_merge($parameters, $queryCondition->parameters);
        }
        $row = $this->dbConnection->getSingleRow($query, $parameters);

        switch ($hydrationMode) {
            case HydrationMode::Array:
                return $row;
            case HydrationMode::Object:
                if ($row === null) {
                    return null;
                } else {
                    $entity = ObjectFactory::create($className, $this);
                    return ObjectMapper::mapEntity($entity, $row, $this);
                }
        }

        throw new Exception('Unexpected error');
    }

    private function findRecords(string $className, array $parameters) : array
    {
        $adapter = $this->dbConnection->getDbAdapter();
        $queryBuilder = $this->createQueryBuilder($className);

        if (!empty($parameters)) {
            $queryCondition = new QueryCondition();
            foreach ($parameters as $field => $value) {

                if (is_array($value)) {
                    $partialQueryCondition = new QueryCondition(QueryConditionComparision::in($field, $value));
                } else {
                    $partialQueryCondition = new QueryCondition($field . ' = :' . $field, $value);
                }

                $queryCondition->addCondition($partialQueryCondition, QueryConditionOperator::And);
            }

            $queryBuilder->addWhere($queryCondition);
        }

        $query = $adapter->getSelectQuery($queryBuilder);

        $parameters = [];
        /** @var QueryCondition $queryCondition */
        foreach ($queryBuilder->getWhere() as $queryCondition) {
            $parameters = array_merge($parameters, $queryCondition->parameters);
        }

        return $this->dbConnection->getTable($query, $parameters);
    }

    public function findForParent(string $className, array $parameters, string $fieldName, object $parent) : array
    {
        $table = $this->findRecords($className, $parameters);

        $resultTable = [];

        foreach ($table as $row) {
            $entity = ObjectFactory::create($className, $this);
            $resultTable[] = ObjectMapper::mapEntity($entity, $row, $this, $fieldName, $parent);
        }

        return $resultTable;
    }

    public function findBy(string $className, array $parameters, HydrationMode $hydrationMode = HydrationMode::Object) : array
    {
        $table = $this->findRecords($className, $parameters);

        switch ($hydrationMode) {
            case HydrationMode::Array:
                return $table;
            case HydrationMode::Object:
                $resultTable = [];

                foreach ($table as $row) {
                    $entity = ObjectFactory::create($className, $this);
                    $resultTable[] = ObjectMapper::mapEntity($entity, $row, $this);
                }

                return $resultTable;
        }

        throw new Exception('Unexpected error');
    }

    public function count(string $className, array $parameters) : int
    {
        $adapter = $this->dbConnection->getDbAdapter();
        $queryBuilder = $this->createQueryBuilder($className);

        if (!empty($parameters)) {
            $queryCondition = new QueryCondition();
            foreach ($parameters as $field => $value) {
                $partialQueryCondition = new QueryCondition($field . ' = :' . $field, $value);
                $queryCondition->addCondition($partialQueryCondition, QueryConditionOperator::And);
            }

            $queryBuilder->addWhere($queryCondition);
        }

        $query = $adapter->getCountQuery($queryBuilder);
        $parameters = [];
        /** @var QueryCondition $queryCondition */
        foreach ($queryBuilder->getWhere() as $queryCondition) {
            $parameters = array_merge($parameters, $queryCondition->parameters);
        }

        return $this->dbConnection->getValue($query, $parameters);
    }

    public function persist(object $entity)
    {
        $this->entitiesToSave[] = $entity;
    }

    public function remove(object $entity)
    {
        $this->entitiesToRemove[] = $entity;
    }

    public function getDbConnection(): DBConnection
    {
        return $this->dbConnection;
    }

//    private function addMigrationEntity(object $entity)
//    {
//        $entitiesFields = ObjectMapper::unmapEntity($entity, $this);
//        $classConfiguration = $this->loadClassConfiguration(get_class($entity));
//
//        foreach ($entitiesFields as $className => $entities) {
//            $repository = $this->createRepository($className);
//            foreach ($entities as $entityFields) {
//                $query = $this->dbConnection->getDbAdapter()->getForceInsertQuery($entityFields, $repository);
//
//                $parameters = [];
//                foreach ($entityFields['data'] as $fieldName => $fieldValue) {
//                    $parameter['name'] = $fieldName;
//                    $parameter['value'] = $fieldValue;
//                    $parameter['type'] = match ($classConfiguration['fields'][$fieldName]['type']) {
//                        'int' => QueryCondition::PARAMETER_TYPE_INT,
//                        default => QueryCondition::PARAMETER_TYPE_STRING,
//                    };
//                    $parameters[$fieldName] = $parameter;
//                }
//
//                $parameter['name'] = $entityFields['identifier']['fieldName'];
//                $parameter['value'] = $entityFields['identifier']['value'];
//                $parameter['type'] = match ($classConfiguration['fields'][$entityFields['identifier']['fieldName']]['type']) {
//                    'int' => QueryCondition::PARAMETER_TYPE_INT,
//                    default => QueryCondition::PARAMETER_TYPE_STRING,
//                };
//                $parameters[$entityFields['identifier']['fieldName']] = $parameter;
//
//                $this->dbConnection->executeQuery($query, $parameters);
//            }
//        }
//    }

    private function saveEntity(object $entity)
    {
        $classConfiguration = $this->loadClassConfiguration(get_class($entity));

        if (isset($classConfiguration['lifecycle']['preUpdate'])) {
            $staticMethodName = $classConfiguration['lifecycle']['preUpdate'];
            $staticMethodName($entity);
        }

        $entitiesFields = ObjectMapper::unmapEntity($entity, $this);

        if (isset($entity->fromMigration) && ($entity->fromMigration === true)) {
            $entitiesFields[get_class($entity)][spl_object_id($entity)]['data'][$entitiesFields[get_class($entity)][spl_object_id($entity)]['identifier']['fieldName']] = $entitiesFields[get_class($entity)][spl_object_id($entity)]['identifier']['value'];
            $entitiesFields[get_class($entity)][spl_object_id($entity)]['identifier']['value'] = null;
        }

        foreach ($entitiesFields as $className => $entities) {
            $repository = $this->createRepository($className);
            foreach ($entities as $entityFields) {

                $query = $this->dbConnection->getDbAdapter()->getInsertUpdateQuery($entityFields, $repository);

                $parameters = [];
                foreach ($entityFields['data'] as $fieldName => $fieldValue) {
                    $parameter['name'] = $fieldName;
                    $parameter['value'] = $fieldValue;

                    $parameter['type'] = match ($classConfiguration['fields'][$fieldName]['type']) {
                        'int' => QueryCondition::PARAMETER_TYPE_INT,
                        default => QueryCondition::PARAMETER_TYPE_STRING,
                    };

                    $parameters[$fieldName] = $parameter;
                }

                if ($entityFields['identifier']['value'] !== null) {
                    $parameter['name'] = $entityFields['identifier']['fieldName'];
                    $parameter['value'] = $entityFields['identifier']['value'];

                    $parameter['type'] = match ($classConfiguration['fields'][$entityFields['identifier']['fieldName']]['type']) {
                        'int' => QueryCondition::PARAMETER_TYPE_INT,
                        default => QueryCondition::PARAMETER_TYPE_STRING,
                    };

                    $parameters[$entityFields['identifier']['fieldName']] = $parameter;
                }

                $this->dbConnection->executeQuery($query, $parameters);

                if ($entityFields['identifier']['value'] === null) {
                    $classProperties = [];
                    ObjectMapper::getClassProperties(get_class($entity), $classProperties, [$entityFields['identifier']['fieldName']]);
                    $identifierClassProperty = $classProperties[$entityFields['identifier']['fieldName']];
                    $visibilityLevel = ObjectMapper::setFieldAccessible($identifierClassProperty);
                    $identifierClassProperty->setValue($entity, $this->dbConnection->getLastInsertId());
                    ObjectMapper::setOriginalAccessibility($identifierClassProperty, $visibilityLevel);
                }

                if (isset($classConfiguration['lifecycle']['postUpdate'])) {
                    $staticMethodName = $classConfiguration['lifecycle']['postUpdate'];
                    $staticMethodName($entity);
                }
            }
        }
    }

    private function removeEntity(object $entity)
    {
        $classConfiguration = $this->loadClassConfiguration(get_class($entity));

        if (isset($classConfiguration['lifecycle']['preRemove'])) {
            $staticMethodName = $classConfiguration['lifecycle']['preRemove'];
            $staticMethodName($entity);
        }

        $entitiesFields = ObjectMapper::unmapEntity($entity, $this);

        $firstElement = reset($entitiesFields);
        $entityFields = reset($firstElement);
        $repository = $this->createRepository(get_class($entity));

        $query = $this->dbConnection->getDbAdapter()->getRemoveQuery($entityFields, $repository);

        $parameter['name'] = $entityFields['identifier']['fieldName'];
        $parameter['value'] = $entityFields['identifier']['value'];

        $parameter['type'] = match ($classConfiguration['fields'][$entityFields['identifier']['fieldName']]['type']) {
            'int' => QueryCondition::PARAMETER_TYPE_INT,
            default => QueryCondition::PARAMETER_TYPE_STRING,
        };

        $parameters[$entityFields['identifier']['fieldName']] = $parameter;

        $this->dbConnection->executeQuery($query, $parameters);


        $idFieldName = ObjectMapper::getIdFieldName($classConfiguration);
        $classProperties = [];
        ObjectMapper::getClassProperties(get_class($entity), $classProperties, [$idFieldName]);
        $idFieldReflectionProperty = $classProperties[$idFieldName];

        ObjectMapper::setFieldAccessible($idFieldReflectionProperty);
        $idFieldReflectionProperty->setValue($entity, null);

        if (isset($classConfiguration['lifecycle']['postRemove'])) {
            $staticMethodName = $classConfiguration['lifecycle']['postRemove'];
            $staticMethodName($entity);
        }
    }

    private function addObjectToSaveEntityQueue(object $object, $entityIndex, &$additionalObjectsToFlush, $elementType)
    {
        if ((isset($object->___orm_initialized) && ($object->___orm_initialized === true)) || (!isset($object->___orm_initialized))) {
            $additionalObjectsToFlush[$entityIndex][$elementType][] = $object;
        }
    }

    private function findObjectPropertiesForObjectToFlush(array $objectArray, int $depth = 1)
    {
        $additionalObjectsToFlush = [];
        $additionalObjectToRemove = [];

        foreach ($objectArray as $entityIndex => $entityToSave)
        {
            if (!isset($this->priorityArray[spl_object_id($entityToSave)])) {
                $this->priorityArray[spl_object_id($entityToSave)] = 0;
            }

            $classConfiguration = $this->loadClassConfiguration(get_class($entityToSave));
            $entityOrCollectionFields = ObjectFactory::filterFieldsByType($classConfiguration['fields'], ['entity', 'collection']);
            $idFieldParent = ObjectMapper::getIdFieldName($classConfiguration);

            $classProperties = [];
            ObjectMapper::getClassProperties(get_class($entityToSave), $classProperties, array_merge(array_keys($entityOrCollectionFields), [$idFieldParent]));
            /** @var ReflectionProperty $idParentReflectionProperty */
            $idParentReflectionProperty = $classProperties[$idFieldParent];
            unset($classProperties[$idFieldParent]);
            $visibilityLevel = ObjectMapper::setFieldAccessible($idParentReflectionProperty);
            $parentId = $idParentReflectionProperty->getValue($entityToSave);
            ObjectMapper::setOriginalAccessibility($idParentReflectionProperty, $visibilityLevel);

            foreach ($entityOrCollectionFields as $fieldName => $fieldData) {

                /** @var ReflectionProperty $property */
                $property = $classProperties[$fieldName];
                $visibilityLevel = ObjectMapper::setFieldAccessible($property);
                if ($property->isInitialized($entityToSave)) {
                    $object = $property->getValue($entityToSave);
                } else {
                    $object = null;
                }

                if (is_object($object) && (get_class($object) == Collection::class)) {

                    $allCollectionObjectsIds = [];
                    $allCollectionObjectsCollection = new Collection($classConfiguration['fields'][$fieldName]['entityClass']);

                    $collectionElementConfiguration = $this->loadClassConfiguration($object->getCollectionClass());
                    $idFieldCollectionElement = ObjectMapper::getIdFieldName($collectionElementConfiguration);

                    if (isset($fieldData['relatedObjectField']) || isset($fieldData['joiningClass'])) {
                        if (isset($fieldData['relatedObjectField']) && !isset($fieldData['joiningClass'])) {
                            throw new Exception('Field "joiningClass" is required when field "relatedObjectField" is defined in entity configuration (orm file)');
                        } elseif (!isset($fieldData['relatedObjectField']) && isset($fieldData['joiningClass'])) {
                            throw new Exception('Field "relatedObjectField" is required when field "joiningClass" is defined in entity configuration (orm file)');
                        } else {
                            $collectionElementConfiguration = $this->loadClassConfiguration($fieldData['joiningClass']);
                            $idFieldCollectionElement = ObjectMapper::getIdFieldName($collectionElementConfiguration);
                        }

                        //pobieranie wszystkich pozycji do kasowania, potem to obsłużyć

                        if ($parentId !== null) {

                            $allCollectionObjects = $this->findBy(
                                $classConfiguration['fields'][$fieldName]['joiningClass'],
                                [
                                    $classConfiguration['fields'][$fieldName]['joiningField'] => $parentId
                                ]
                            );

                            $allCollectionObjectsCollection->setCollectionArray($allCollectionObjects);

                            foreach ($allCollectionObjects as $collectionObject) {
                                $collectionElementProperties = [];
                                ObjectMapper::getClassProperties(get_class($collectionObject), $collectionElementProperties, [$idFieldCollectionElement]);
                                /** @var ReflectionProperty $collectionElementIdProperty */
                                $collectionElementIdProperty = $collectionElementProperties[$idFieldCollectionElement];
                                $visibilityLevelCollectionElement = ObjectMapper::setFieldAccessible($collectionElementIdProperty);

                                $collectionElementId = $collectionElementIdProperty->getValue($collectionObject);
                                $allCollectionObjectsIds[$collectionElementId] = $collectionElementId;

                                ObjectMapper::setOriginalAccessibility($collectionElementIdProperty, $visibilityLevelCollectionElement);
                            }
                        }

                        foreach ($object as $collectionElement) {

                            //TODO - czy tu nie trzeba zastąpić operatorem new i potem wywalić to co jest w dalszej części usuwanie pola ___orm_initialized ?
                            $joiningEntity = ObjectFactory::create($collectionElementConfiguration['entity'], $this);
                            $setter = 'set' . $classConfiguration['fields'][$fieldName]['relatedObjectField'];
                            $joiningEntity->$setter($collectionElement);

                            $collectionElementProperties = [];
                            ObjectMapper::getClassProperties(get_class($joiningEntity), $collectionElementProperties, [$fieldData['joiningField'], $idFieldCollectionElement]);

                            /** @var ReflectionProperty $collectionElementJoiningFieldProperty */
                            $collectionElementJoiningFieldProperty = $collectionElementProperties[$fieldData['joiningField']];
                            /** @var ReflectionProperty $collectionElementIdProperty */
                            $collectionElementIdProperty = $collectionElementProperties[$idFieldCollectionElement];

                            $collectionElementJoiningFieldVisiblility = ObjectMapper::setFieldAccessible($collectionElementJoiningFieldProperty);
                            $collectionElementIdVisiblility = ObjectMapper::setFieldAccessible($collectionElementIdProperty);

                            $collectionElementJoiningFieldProperty->setValue($joiningEntity, $entityToSave);
                            $flushedCollectionElementId = $collectionElementIdProperty->getValue($joiningEntity);

                            if (($flushedCollectionElementId !== null) && (isset($allCollectionObjectsIds[$flushedCollectionElementId]))) {
                                unset($allCollectionObjectsIds[$flushedCollectionElementId]);
                            }

                            ObjectMapper::setOriginalAccessibility($collectionElementJoiningFieldProperty, $collectionElementJoiningFieldVisiblility);
                            ObjectMapper::setOriginalAccessibility($collectionElementIdProperty, $collectionElementIdVisiblility);

                            if (isset($joiningEntity->___orm_initialized)) unset($joiningEntity->___orm_initialized);

                            $this->addObjectToSaveEntityQueue($collectionElement, $entityIndex, $additionalObjectsToFlush, 'collectionElement');
                            $this->addObjectToSaveEntityQueue($joiningEntity, $entityIndex, $additionalObjectsToFlush, 'collectionElement');

                            $this->priorityArray[spl_object_id($joiningEntity)] = $depth;
                        }
                    } else {

                        if ($parentId !== null) {
                            $allCollectionObjects = $this->findBy(
                                $classConfiguration['fields'][$fieldName]['entityClass'],
                                [
                                    $classConfiguration['fields'][$fieldName]['joiningField'] => $parentId
                                ]
                            );

                            $allCollectionObjectsCollection->setCollectionArray($allCollectionObjects);

                            foreach ($allCollectionObjects as $collectionObject) {
                                $collectionElementProperties = [];
                                ObjectMapper::getClassProperties(get_class($collectionObject), $collectionElementProperties, [$idFieldCollectionElement]);
                                /** @var ReflectionProperty $collectionElementIdProperty */
                                $collectionElementIdProperty = $collectionElementProperties[$idFieldCollectionElement];
                                $visibilityLevelCollectionElement = ObjectMapper::setFieldAccessible($collectionElementIdProperty);

                                $collectionElementId = $collectionElementIdProperty->getValue($collectionObject);
                                $allCollectionObjectsIds[$collectionElementId] = $collectionElementId;

                                ObjectMapper::setOriginalAccessibility($collectionElementIdProperty, $visibilityLevelCollectionElement);
                            }
                        }

                        foreach ($object as $collectionElement) {
                            $collectionElementProperties = [];
                            ObjectMapper::getClassProperties(get_class($collectionElement), $collectionElementProperties, [$fieldData['joiningField'], $idFieldCollectionElement]);

                            /** @var ReflectionProperty $collectionElementJoiningFieldProperty */
                            $collectionElementJoiningFieldProperty = $collectionElementProperties[$fieldData['joiningField']];
                            /** @var ReflectionProperty $collectionElementIdProperty */
                            $collectionElementIdProperty = $collectionElementProperties[$idFieldCollectionElement];

                            $collectionElementJoiningFieldVisiblility = ObjectMapper::setFieldAccessible($collectionElementJoiningFieldProperty);
                            $collectionElementIdVisiblility = ObjectMapper::setFieldAccessible($collectionElementIdProperty);

                            $collectionElementJoiningFieldProperty->setValue($collectionElement, $entityToSave);
                            $flushedCollectionElementId = $collectionElementIdProperty->getValue($collectionElement);

                            if (($flushedCollectionElementId !== null) && (isset($allCollectionObjectsIds[$flushedCollectionElementId]))) {
                                unset($allCollectionObjectsIds[$flushedCollectionElementId]);
                            }

                            ObjectMapper::setOriginalAccessibility($collectionElementJoiningFieldProperty, $collectionElementJoiningFieldVisiblility);
                            ObjectMapper::setOriginalAccessibility($collectionElementIdProperty, $collectionElementIdVisiblility);

                            $this->addObjectToSaveEntityQueue($collectionElement, $entityIndex, $additionalObjectsToFlush, 'collectionElement');
                            $this->priorityArray[spl_object_id($collectionElement)] = $depth;
                        }
                    }

                    if (count($allCollectionObjectsIds) > 0) {
                        foreach ($allCollectionObjectsIds as $collectionObjectId) {
                            $additionalObjectToRemove[] = $allCollectionObjectsCollection->findOneByFieldValue($idFieldCollectionElement, $collectionObjectId);
                        }
                    }
                } elseif (is_object($object) && (get_class($object) != LazyCollection::class)) {
                    $this->addObjectToSaveEntityQueue($object, $entityIndex,$additionalObjectsToFlush, 'entity');
                    $this->priorityArray[spl_object_id($object)] = $depth;
                    $this->findObjectPropertiesForObjectToFlush([$object], ($depth + 1));
                } elseif ((!(is_object($object) && (get_class($object) == LazyCollection::class))) and (isset($object))) {
                    throw new Exception('whats that?!!');
                }

                ObjectMapper::setOriginalAccessibility($property, $visibilityLevel);
            }
        }

        $newEntitiesToSave = [];
        $newCollectionEntitiesToSave = [];

        foreach ($this->entitiesToSave as $entityIndex => $entityToSave) {
            if (isset($additionalObjectsToFlush[$entityIndex]['entity'])) {
                foreach ($additionalObjectsToFlush[$entityIndex]['entity'] as $entity) {
                    $newEntitiesToSave[spl_object_id($entity)] = $entity;
                }
            }

            if (isset($additionalObjectsToFlush[$entityIndex]['collectionElement'])) {
                foreach ($additionalObjectsToFlush[$entityIndex]['collectionElement'] as $entity) {
                    $newCollectionEntitiesToSave[spl_object_id($entity)] = $entity;
                }
            }
        }

        $entToSave = $this->entitiesToSave;
        $this->entitiesToSave = [];
        foreach ($newEntitiesToSave as $entity) {
            $this->entitiesToSave[] = $entity;
        }

        foreach ($entToSave as $entity) {
            $this->entitiesToSave[] = $entity;
        }

        $this->collectionEntitiesToSave = array_unique(array_merge($this->entitiesToRemove, $newCollectionEntitiesToSave), SORT_REGULAR);
        $this->entitiesToRemove = array_unique(array_merge($this->entitiesToRemove, $additionalObjectToRemove), SORT_REGULAR);
    }

    public function flush()
    {
        $this->findObjectPropertiesForObjectToFlush($this->entitiesToSave);

        $entitiesToSave = [];
        arsort($this->priorityArray);
        foreach ($this->priorityArray as $splObjectId => $priority) {
            foreach ($this->entitiesToSave as $entity) {
                if (spl_object_id($entity) == $splObjectId) {
                    $entitiesToSave[spl_object_id($entity)] = $entity;
                    break;
                }
            }
        }

        foreach ($this->collectionEntitiesToSave as $entity) {
            $entitiesToSave[spl_object_id($entity)] = $entity;
        }

        $entitiesToRemove = [];
        foreach ($this->entitiesToRemove as $entity) {
            $entitiesToRemove[spl_object_id($entity)] = $entity;
        }

        $this->dbConnection->beginTransaction();

        try {
            foreach ($entitiesToSave as $entity) {
                if (!$this->dbConnection->checkIfTransactionStarted()) {
                    throw new Exception('Transaction lost. You have probably made a "SELECT" query after transaction start.');
                }

                $this->saveEntity($entity);
            }

            foreach ($entitiesToRemove as $entity) {
                if (!$this->dbConnection->checkIfTransactionStarted()) {
                    throw new Exception('Transaction lost. You have probably made a "SELECT" query after transaction start.');
                }

                $this->removeEntity($entity);
            }

            $this->dbConnection->commitTransaction();

            $this->entitiesToSave = [];
            $this->entitiesToRemove = [];
        } catch (Throwable $exception) {
            $this->dbConnection->rollbackTransaction();
            throw $exception;
        }
    }

    public function getEntityConfigurationDir(): mixed
    {
        return $this->entityConfigurationDir;
    }

    public function getTablesFromDb() : array
    {
        $query = self::$dbAdapter->getGetTablesQuery();
        return $this->dbConnection->getTable($query);
    }

    public function getTableFieldsFromDb(string $tableName, string $dbName) : array
    {
        $query = self::$dbAdapter->getGetTableFieldsQuery($tableName, $dbName);
        return $this->dbConnection->getTable($query);
    }

    public function getTableIndexesFromDb(string $tableName) : array
    {
        $query = self::$dbAdapter->getTableIndexesQuery($tableName);
        return $this->dbConnection->getTable($query);
    }

    public function getDbForeignKeys(string $dbName) : array
    {
        $query = self::$dbAdapter->getForeignKeysQuery($dbName);
        return $this->dbConnection->getTable($query);
    }

    public function getDbTableDetailsFromDb(string $tableName, string $dbName) : array
    {
        $query = self::$dbAdapter->getTableDetailsQuery($tableName, $dbName);
        return $this->dbConnection->getSingleRow($query);
    }

    public function getDsnValue($dsnParameter, $default = NULL)
    {
        $pattern = sprintf('~%s=([^;]*)(?:;|$)~', preg_quote($dsnParameter, '~'));

        $result = preg_match($pattern, self::$config['dsn'], $matches);
        if ($result === FALSE) {
            throw new RuntimeException('Regular expression matching failed unexpectedly.');
        }

        return $result ? $matches[1] : $default;
    }

    public function turnOffCheckForeignKeys()
    {
        $query = self::$dbAdapter->getQueryForTurnOffForeignKeyCheck();
        $this->dbConnection->executeQuery($query);
    }

    public function turnOnCheckForeignKeys()
    {
        $query = self::$dbAdapter->getQueryForTurnOnForeignKeyCheckQuery();
        $this->dbConnection->executeQuery($query);
    }

    public function truncateTable($tableName)
    {
        $query = self::$dbAdapter->getQueryForTruncateTable($tableName);
        $this->dbConnection->executeQuery($query);
    }
}
