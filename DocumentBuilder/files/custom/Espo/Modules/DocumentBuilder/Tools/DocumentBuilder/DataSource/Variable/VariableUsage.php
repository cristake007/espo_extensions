<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable;

enum VariableUsage: string
{
    case Scalar = 'scalar';
    case Collection = 'collection';
}
