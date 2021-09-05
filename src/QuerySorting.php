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

class QuerySorting {

    const DIRECTION_ASC = 'asc';
    const DIRECTION_DESC = 'desc';
    const DIRECTION_ORDERED = 'ordered';
    const DIRECTION_RANDOM = 'random';

    private array $fields = [];

    public function __construct(string $fieldName = null, string $sortDirection = 'ASC')
    {
        if (null !== $fieldName) {
            $field['fieldName'] = $fieldName;
            $field['sortDirection'] = $sortDirection;
            $field['sortOrder'] = null;

            $this->fields[$fieldName] = $field;
        }
    }

    public function addField(string $fieldName, string $sortDirection, array $orderedDirection = [], $orderedDirectionAscDesc = self::DIRECTION_ASC)
    {
        $field['fieldName'] = $fieldName;
        $field['sortDirection'] = $sortDirection;
        $field['sortOrder'] = $orderedDirection;
        $field['orderedDirectionAscDesc'] = $orderedDirectionAscDesc;

        $this->fields[$fieldName] = $field;
    }

    public function getFields() : array
    {
        return $this->fields;
    }

    public function clear()
    {
        $this->fields = [];
    }
}