<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout;

enum CapabilityStatus: string
{
    case SchemaOnly = 'schemaOnly';
    case Draft = 'draft';
    case Publishable = 'publishable';
}
