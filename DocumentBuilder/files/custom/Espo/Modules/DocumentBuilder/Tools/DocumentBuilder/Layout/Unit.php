<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout;

enum Unit: string
{
    case Millimetre = 'mm';
    case Point = 'pt';
    case Percent = 'percent';
    case GridSpan = 'gridSpan';
}
