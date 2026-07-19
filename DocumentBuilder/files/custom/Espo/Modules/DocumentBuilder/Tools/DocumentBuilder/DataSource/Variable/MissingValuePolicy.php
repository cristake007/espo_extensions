<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable;

enum MissingValuePolicy: string
{
    case Empty = 'empty';
    case Fallback = 'fallback';
    case HideElement = 'hideElement';
    case HideRow = 'hideRow';
    case HideSection = 'hideSection';
    case Warning = 'warning';
    case Required = 'required';
}
