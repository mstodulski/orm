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

enum QueryConditionOperator
{
    case Or;
    case And;
    case Equals;
    case Gt;
    case Gte;
    case Lt;
    case Lte;
    case IsNull;
    case IsNotNull;
    case In;
    case NotIn;
    case Contains;
}
