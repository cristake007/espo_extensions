<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable;

enum VariableValueState: string
{
    case Present = 'present';
    case Missing = 'missing';
    case Forbidden = 'forbidden';
    case Invalid = 'invalid';
}
