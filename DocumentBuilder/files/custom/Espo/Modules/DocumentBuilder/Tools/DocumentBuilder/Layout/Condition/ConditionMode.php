<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Condition;

enum ConditionMode: string
{
    case All = 'all';
    case Any = 'any';
}
