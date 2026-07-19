<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Audit;

enum AuditEventCategory: string
{
    case TemplateLifecycle = 'templateLifecycle';
    case Publication = 'publication';
    case Generation = 'generation';
    case Batch = 'batch';
    case SpreadsheetImport = 'spreadsheetImport';
    case SharedMedia = 'sharedMedia';
    case Authorization = 'authorization';
    case SecurityValidation = 'securityValidation';
    case Settings = 'settings';
}
