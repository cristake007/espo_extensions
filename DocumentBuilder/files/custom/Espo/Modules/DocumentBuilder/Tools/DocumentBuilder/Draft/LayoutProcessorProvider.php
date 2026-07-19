<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutProcessor;

interface LayoutProcessorProvider
{
    public function get(): LayoutProcessor;
}
