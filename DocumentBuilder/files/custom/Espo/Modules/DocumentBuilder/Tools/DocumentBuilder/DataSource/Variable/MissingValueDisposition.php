<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable;

enum MissingValueDisposition: string
{
    case Display = 'display';
    case HideElement = 'hideElement';
    case HideRow = 'hideRow';
    case HideSection = 'hideSection';
    case Warning = 'warning';
    case Failure = 'failure';
}
