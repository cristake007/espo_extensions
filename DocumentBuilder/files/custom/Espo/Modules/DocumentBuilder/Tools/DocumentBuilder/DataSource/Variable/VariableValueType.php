<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable;

enum VariableValueType: string
{
    case Text = 'text';
    case Date = 'date';
    case DateTime = 'datetime';
    case Number = 'number';
    case Currency = 'currency';
    case Boolean = 'boolean';
    case Enum = 'enum';
    case MultiValue = 'multiValue';
}
