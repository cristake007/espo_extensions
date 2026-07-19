<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable;

enum VariableType: string
{
    case Direct = 'direct';
    case Related = 'related';
    case System = 'system';
    case Spreadsheet = 'spreadsheet';
    case Collection = 'collection';
}
