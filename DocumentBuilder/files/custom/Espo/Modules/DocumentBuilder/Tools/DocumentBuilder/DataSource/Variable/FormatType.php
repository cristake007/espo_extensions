<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable;

enum FormatType: string
{
    case Auto = 'auto';
    case Date = 'date';
    case DateTime = 'datetime';
    case Number = 'number';
    case Currency = 'currency';
    case Boolean = 'boolean';
    case Enum = 'enum';
    case MultiValue = 'multiValue';
}
