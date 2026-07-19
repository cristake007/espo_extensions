<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable;

enum TextCase: string
{
    case None = 'none';
    case Upper = 'upper';
    case Lower = 'lower';
    case Title = 'title';
}
