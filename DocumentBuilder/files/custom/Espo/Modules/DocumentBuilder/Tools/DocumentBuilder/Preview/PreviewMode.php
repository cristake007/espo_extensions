<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview;

enum PreviewMode: string
{
    case Sample = 'sample';
    case Record = 'record';
}
