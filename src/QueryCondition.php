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

class QueryCondition
{
    const PARAMETER_TYPE_STRING = 2; // \PDO::PARAM_STR
    const PARAMETER_TYPE_INT = 1; // \PDO::PARAM_INT
    const PARAMETER_TYPE_NULL = 0; // \PDO::PARAM_NULL
    const PARAMETER_TYPE_BOOL = 5; // \PDO::PARAM_BOOL

    public array $conditions = [];
    public array $parameters = [];

    public function __construct(string $condition = null,
                                mixed $parameterValue = null,
                                $parameterType = QueryCondition::PARAMETER_TYPE_STRING
    ) {
        $parameterName = null;

        if (null !== $condition) {

            $conditionClone = str_replace('"', '`', $condition);
            $conditionClone = str_replace("'", '`', $conditionClone);

            while (strpos($conditionClone, '`')) {

                $posStart = strpos($conditionClone, '`');
                $posEnd = strpos($conditionClone, '`', $posStart + 1);
                $substr = substr($conditionClone, $posStart, $posEnd - $posStart + 1);

                $conditionClone = str_replace($substr, '', $conditionClone);
            }

            while (strpos($conditionClone, ':')) {
                $posStart = strpos($conditionClone, ':');
                $posEnd = strlen($conditionClone);
                $substr = substr($conditionClone, $posStart, $posEnd);

                $parameterName = substr($substr, 1, strlen($substr));
                $conditionClone = str_replace($substr, '', $conditionClone);
            }

            $this->conditions[] = [
                'precedingOperator' => null,
                'condition' => $condition,
                'conditionKind' => QueryConditionKind::String
            ];
        }

        if ((null !== $parameterName) && (null !== $parameterValue)) {
            $this->parameters[] = [
                'name' => $parameterName,
                'value' => $parameterValue,
                'type' => $parameterType
            ];
        }
    }

    public function addCondition(string|QueryCondition $condition, $precedingOperator = null): QueryCondition
    {
        if ((count($this->conditions) == 0) && ($precedingOperator !== null)) {
            $precedingOperator = null;
        } elseif ((count($this->conditions) > 0) && ($precedingOperator === null)) {
            throw new Exception('Second or more condition must have a preceding operator.');
        }

        if ($condition instanceof self) {
            $this->conditions[] = [
                'precedingOperator' => $precedingOperator,
                'condition' => $condition,
                'conditionKind' => QueryConditionKind::Condition
            ];

            $this->parameters = array_merge($this->parameters, $condition->parameters);
        } else {
            $this->conditions[] = [
                'precedingOperator' => $precedingOperator,
                'condition' => $condition,
                'conditionKind' => QueryConditionKind::String
            ];
        }

        return $this;
    }

    public function addParameter(string $name, mixed $value, $parameterType = self::PARAMETER_TYPE_STRING): QueryCondition
    {
        $this->parameters[] = [
            'name' => $name,
            'value' => $value,
            'type' => $parameterType
        ];

        return $this;
    }
}
