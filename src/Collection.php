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

use Iterator;
use Exception;
use ReflectionProperty;

class Collection implements Iterator {

    protected string $collectionClass;
    protected array $collectionArray = [];
    protected int $position = 0;
    protected int $recordsCount = 0;

    public function __construct($collectionRepresentative)
    {
        if (is_object($collectionRepresentative)) {
            $this->collectionClass = get_class($collectionRepresentative);
        } else {
            $this->collectionClass = $collectionRepresentative;
        }
    }

    public function clear()
    {
        $this->collectionArray = [];
        $this->position = 0;
        $this->recordsCount = 0;
    }

    public function setRecordsCount(int $recordsCount): void
    {
        $this->recordsCount = $recordsCount;
    }

    public function add(object $collectionElement)
    {
        if ((get_class($collectionElement) != $this->collectionClass) && (!is_subclass_of($collectionElement, $this->collectionClass))) {
            throw new Exception('Collection class is different than added entity class: ' .
                $this->collectionClass . ' vs ' . get_class($collectionElement));
        } else {
            $this->collectionArray[] = $collectionElement;
            $this->recordsCount++;

            $this->collectionArray = array_values($this->collectionArray);
            $this->rewind();
        }
    }

    public function setCollectionArray(array $elements)
    {
        $this->collectionArray = $elements;
    }

    public function replaceCollectionElementAtIndex(object $collectionElement, int $index)
    {
        if ((get_class($collectionElement) != $this->collectionClass) && (!is_subclass_of($collectionElement, $this->collectionClass))) {
            throw new Exception('Collection class is different than added entity class: ' .
                $this->collectionClass . ' vs ' . get_class($collectionElement));
        } else {
            if (!isset($this->collectionArray[$index])) {
                $this->recordsCount++;
            }
            $this->collectionArray[$index] = $collectionElement;
            $this->collectionArray = array_values($this->collectionArray);
            $this->rewind();
        }
    }

    public function remove($index)
    {
        if (isset($this->collectionArray[$index])) {
            unset($this->collectionArray[$index]);
            $this->recordsCount--;

            $this->collectionArray = array_values($this->collectionArray);
            $this->position = 0;
        }
    }

    public function findOneByFieldValue($fieldName, $expectedValue) : ?object
    {
        foreach ($this->collectionArray as $collectionElement) {
            $classProperties = [];
            ObjectMapper::getClassProperties(get_class($collectionElement), $classProperties, [$fieldName]);
            /** @var ReflectionProperty $property */
            $property = $classProperties[$fieldName];
            $visibilityLevel = ObjectMapper::setFieldAccessible($property);

            $value = $property->getValue($collectionElement);
            ObjectMapper::setOriginalAccessibility($property, $visibilityLevel);

            if ($value === $expectedValue) {
                return $collectionElement;
            }
        }

        return null;
    }

    public function getIndexByFieldValue($fieldName, $expectedValue): ?int
    {
        foreach ($this->collectionArray as $index => $collectionElement) {
            $classProperties = [];
            ObjectMapper::getClassProperties(get_class($collectionElement), $classProperties, [$fieldName]);
            /** @var ReflectionProperty $property */
            $property = $classProperties[$fieldName];
            $visibilityLevel = ObjectMapper::setFieldAccessible($property);

            $value = $property->getValue($collectionElement);
            ObjectMapper::setOriginalAccessibility($property, $visibilityLevel);

            if ($value === $expectedValue) {
                return $index;
            }
        }

        return null;
    }

    public function rewind() : void
    {
        $this->position = 0;
    }

    public function current() : object
    {
        return $this->collectionArray[$this->position];
    }

    public function key() : int
    {
        return $this->position;
    }

    public function next() : void
    {
        ++$this->position;
    }

    public function valid() : bool
    {
        return isset($this->collectionArray[$this->position]);
    }

    public function getRecordsCount(): int
    {
        return $this->recordsCount;
    }

    public function isEmpty() : bool
    {
        return empty($this->collectionArray);
    }

    public function getElementByIndex(int $index) : ?object
    {
        return $this->collectionArray[$index] ?? null;
    }

    public function getCollectionClass(): string
    {
        return $this->collectionClass;
    }
}
