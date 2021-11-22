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

class QueryConditionOperator {

    const VALUE_KIND_PARAMETER = 0;
    const VALUE_KIND_VALUE = 1;

    const OPERATOR_EQUALS = 2;
    const OPERATOR_GT = 3;
    const OPERATOR_GTE = 4;
    const OPERATOR_LT = 5;
    const OPERATOR_LTE = 6;
    const OPERATOR_ISNULL = 7;
    const OPERATOR_ISNOTNULL = 8;
    const OPERATOR_IN = 9;
    const OPERATOR_NOTIN = 10;
    const OPERATOR_CONTAINS = 11;

    private static function insertQuotation($valueKind): string
    {
        return ($valueKind == self::VALUE_KIND_PARAMETER) ? '' : '"';
    }

    private static function insertPercent($valueKind): string
    {
        return ($valueKind == self::VALUE_KIND_PARAMETER) ? '' : '%';
    }

    #[Pure] public static function equals($field, $parameterName, $valueKind = self::VALUE_KIND_PARAMETER): string
    {
        return $field . ' = ' . self::insertQuotation($valueKind) .  $parameterName . self::insertQuotation($valueKind);
    }

    #[Pure] public static function differs($field, $parameterName, $valueKind = self::VALUE_KIND_PARAMETER): string
    {
        return $field . ' <> ' . self::insertQuotation($valueKind) .  $parameterName . self::insertQuotation($valueKind);
    }

    #[Pure] public static function gt($field, $parameterName, $valueKind = self::VALUE_KIND_PARAMETER): string
    {
        return $field . ' > ' . self::insertQuotation($valueKind) .  $parameterName . self::insertQuotation($valueKind);
    }

    #[Pure] public static function gte($field, $parameterName, $valueKind = self::VALUE_KIND_PARAMETER): string
    {
        return $field . ' >= ' . self::insertQuotation($valueKind) .  $parameterName . self::insertQuotation($valueKind);
    }

    #[Pure] public static function lt($field, $parameterName, $valueKind = self::VALUE_KIND_PARAMETER): string
    {
        return $field . ' < ' . self::insertQuotation($valueKind) .  $parameterName . self::insertQuotation($valueKind);
    }

    #[Pure] public static function lte($field, $parameterName, $valueKind = self::VALUE_KIND_PARAMETER): string
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

    #[Pure] public static function contains($field, $parameterName, $valueKind = self::VALUE_KIND_PARAMETER): string
    {
        return $field . ' LIKE ' .
            self::insertQuotation($valueKind) . self::insertPercent($valueKind) .
            $parameterName .
            self::insertPercent($valueKind) . self::insertQuotation($valueKind);
    }
}
