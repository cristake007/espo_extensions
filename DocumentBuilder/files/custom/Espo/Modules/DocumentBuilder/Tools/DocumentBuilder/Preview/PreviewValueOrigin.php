<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview;

enum PreviewValueOrigin: string
{
    case Sample = 'sample';
    case Real = 'real';
}
