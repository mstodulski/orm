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

//https://www.php.net/releases/8.1/en.php
//utworzyć nową klasę ConditionOperator i tam te wszystkie parametry, potem update ofc
//puścić composera

class QueryConditionComparision {

    private static function insertQuotation(QueryConditionValueKind $valueKind): string
    {
        return ($valueKind == QueryConditionValueKind::Parameter) ? '' : '"';
    }

    private static function insertPercent(QueryConditionValueKind $valueKind): string
    {
        return ($valueKind == QueryConditionValueKind::Parameter) ? '' : '%';
    }

    #[Pure] public static function equals($field, $parameterName, QueryConditionValueKind $valueKind = QueryConditionValueKind::Parameter): string
    {
        return $field . ' = ' . self::insertQuotation($valueKind) .  $parameterName . self::insertQuotation($valueKind);
    }

    #[Pure] public static function differs($field, $parameterName, QueryConditionValueKind $valueKind = QueryConditionValueKind::Parameter): string
    {
        return $field . ' <> ' . self::insertQuotation($valueKind) .  $parameterName . self::insertQuotation($valueKind);
    }

    #[Pure] public static function gt($field, $parameterName, QueryConditionValueKind $valueKind = QueryConditionValueKind::Parameter): string
    {
        return $field . ' > ' . self::insertQuotation($valueKind) .  $parameterName . self::insertQuotation($valueKind);
    }

    #[Pure] public static function gte($field, $parameterName, QueryConditionValueKind $valueKind = QueryConditionValueKind::Parameter): string
    {
        return $field . ' >= ' . self::insertQuotation($valueKind) .  $parameterName . self::insertQuotation($valueKind);
    }

    #[Pure] public static function lt($field, $parameterName, QueryConditionValueKind $valueKind = QueryConditionValueKind::Parameter): string
    {
        return $field . ' < ' . self::insertQuotation($valueKind) .  $parameterName . self::insertQuotation($valueKind);
    }

    #[Pure] public static function lte($field, $parameterName, QueryConditionValueKind $valueKind = QueryConditionValueKind::Parameter): string
    {
        return $field . ' <= ' . self::insertQuotation($valueKind) .  $parameterName . self::insertQuotation($valueKind);
    }

    public static function isNull($field): string
    {
        return $field . ' IS NULL ';
    }

    public static function isNotNull($field): string
    {
        return $field . ' IS NOT NULL ';
    }

    public static function in($field, $parameterValue): string
    {
        return $field . ' IN ("' . implode('", "', $parameterValue) . '") ';
    }

    public static function notIn($field, $parameterName): string
    {
        return $field . ' NOT IN ("' . implode('", "', $parameterName) . '") ';
    }

    #[Pure] public static function contains($field, $parameterName, QueryConditionValueKind $valueKind = QueryConditionValueKind::Parameter): string
    {
        return $field . ' LIKE ' .
            self::insertQuotation($valueKind) . self::insertPercent($valueKind) .
            $parameterName .
            self::insertPercent($valueKind) . self::insertQuotation($valueKind);
    }
}
