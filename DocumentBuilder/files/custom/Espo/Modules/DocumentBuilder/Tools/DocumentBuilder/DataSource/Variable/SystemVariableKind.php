<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable;

enum SystemVariableKind: string
{
    case Value = 'value';
    case RendererPlaceholder = 'rendererPlaceholder';
}
