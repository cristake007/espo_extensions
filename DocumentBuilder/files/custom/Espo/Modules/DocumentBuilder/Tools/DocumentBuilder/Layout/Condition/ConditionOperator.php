<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Condition;

enum ConditionOperator: string
{
    case Exists = 'exists';
    case Missing = 'missing';
    case Equals = 'equals';
    case NotEquals = 'notEquals';
    case Contains = 'contains';
    case StartsWith = 'startsWith';
    case GreaterThan = 'greaterThan';
    case GreaterOrEqual = 'greaterOrEqual';
    case LessThan = 'lessThan';
    case LessOrEqual = 'lessOrEqual';
    case IsTrue = 'isTrue';
    case IsFalse = 'isFalse';
}
