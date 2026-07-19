<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable;

enum VariableSource: string
{
    case Entity = 'entity';
    case System = 'system';
    case Spreadsheet = 'spreadsheet';
}
