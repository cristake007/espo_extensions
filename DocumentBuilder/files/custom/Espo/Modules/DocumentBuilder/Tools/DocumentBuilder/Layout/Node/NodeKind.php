<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Node;

enum NodeKind: string
{
    case Section = 'section';
    case Element = 'element';
}
