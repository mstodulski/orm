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

class LazyEntity {

    public static function getLazyEntity(object $object, string $fieldName)
    {
        $fieldValue = null;

        $classProperties = [];
        $fieldClassProperty = null;
        $fieldClassPropertyVisibilityLevel = null;

        ObjectMapper::getClassProperties(get_class($object), $classProperties);
        /** @var ReflectionProperty $classProperty */
        foreach ($classProperties as $fieldClassProperty) {
            if ($fieldClassProperty->name == $fieldName) {
                $fieldClassPropertyVisibilityLevel = ObjectMapper::setFieldAccessible($fieldClassProperty);
                $fieldValue = $fieldClassProperty->getValue($object);
                break;
            }
        }

        if ($fieldValue->___orm_initialized) {
            if (($fieldClassProperty !== null) && ($fieldClassPropertyVisibilityLevel !== null)) {
                ObjectMapper::setOriginalAccessibility($fieldClassProperty, $fieldClassPropertyVisibilityLevel);
            }

            return $fieldValue;
        } else {
            $entityManager = new EntityManager();
            $classConfiguration = $entityManager->loadClassConfiguration(get_class($fieldValue));
            $idFieldName = ObjectMapper::getIdFieldName($classConfiguration);

            $classProperties = [];
            ObjectMapper::getClassProperties(get_class($fieldValue), $classProperties);

            /** @var ReflectionProperty $classProperty */
            foreach ($classProperties as $classProperty) {
                if ($classProperty->name == $idFieldName) {
                    $visibilityLevel = ObjectMapper::setFieldAccessible($classProperty);
                    $objectId = $classProperty->getValue($fieldValue);

                    $propertyObject = $entityManager->find(get_class($fieldValue), $objectId);

                    if ($propertyObject !== null) {
                        $fieldClassProperty->setValue($object, $propertyObject);
                    }

                    if (($fieldClassProperty !== null) && ($fieldClassPropertyVisibilityLevel !== null)) {
                        ObjectMapper::setOriginalAccessibility($fieldClassProperty, $fieldClassPropertyVisibilityLevel);
                    }

                    ObjectMapper::setOriginalAccessibility($classProperty, $visibilityLevel);

                    return $propertyObject;
                }
            }
        }

        return null;
    }
}
