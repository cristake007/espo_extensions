<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Source;

enum SpreadsheetFormat: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';
}
