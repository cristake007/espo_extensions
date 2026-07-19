<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Condition;

enum ConditionTarget: string
{
    case Element = 'element';
    case Parent = 'parent';
}
