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

use ReflectionProperty;

class LazyCollection {

    public object $parent;
    public string $class;
    public string $joiningField;
    public string $fieldName;

    public function __construct(object $parent, string $class, string $joiningField, string $fieldName)
    {
        $this->parent = $parent;
        $this->class = $class;
        $this->joiningField = $joiningField;
        $this->fieldName = $fieldName;
    }

    public function getCollection() : Collection
    {
        $entityManager = new EntityManager();
        $classConfiguration = $entityManager->loadClassConfiguration(get_class($this->parent));
        $idFieldName = ObjectMapper::getIdFieldName($classConfiguration);

        $classProperties = [];
        ObjectMapper::getClassProperties(get_class($this->parent), $classProperties, [$idFieldName, $this->fieldName]);

        /** @var ReflectionProperty $idProperty */
        $idProperty = $classProperties[$idFieldName];
        /** @var ReflectionProperty $fieldProperty */
        $fieldProperty = $classProperties[$this->fieldName];

        $visibilityLevel = ObjectMapper::setFieldAccessible($idProperty);
        $parentId = $idProperty->getValue($this->parent);
        ObjectMapper::setOriginalAccessibility($idProperty, $visibilityLevel);

        $elements = $entityManager->findForParent($this->class, [$this->joiningField => $parentId], $this->joiningField, $this->parent);
        $collection = new Collection($this->class);
        $collection->setCollectionArray($elements);
        $collection->setRecordsCount(count($elements));

        $visibilityLevel = ObjectMapper::setFieldAccessible($fieldProperty);
        $fieldProperty->setValue($this->parent, $collection);
        ObjectMapper::setOriginalAccessibility($fieldProperty, $visibilityLevel);

        return $collection;
    }
}
