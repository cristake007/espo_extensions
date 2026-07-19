<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout;

enum Capability: string
{
    case FlowLayout = 'layout.flow';
    case GridLayout = 'layout.grid';
    case FreeformLayout = 'layout.freeform';
    case EntitySource = 'source.entity';
    case SpreadsheetSource = 'source.spreadsheet';
    case CollectionData = 'data.collection';
    case RasterMedia = 'media.raster';
    case WebpMedia = 'media.webp';
    case SvgMedia = 'media.svg';
}
