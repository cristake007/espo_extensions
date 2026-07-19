<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Source;

enum SourceType: string
{
    case None = 'none';
    case Entity = 'entity';
    case Spreadsheet = 'spreadsheet';
}
