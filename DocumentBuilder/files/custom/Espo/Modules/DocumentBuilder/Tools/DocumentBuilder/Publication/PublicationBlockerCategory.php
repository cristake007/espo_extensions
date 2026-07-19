<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication;

enum PublicationBlockerCategory: string
{
    case Capability = 'capability';
    case DataSource = 'dataSource';
    case Media = 'media';
    case Variable = 'variable';
}
