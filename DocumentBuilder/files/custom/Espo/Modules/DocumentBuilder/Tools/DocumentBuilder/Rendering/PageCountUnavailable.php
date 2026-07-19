<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering;

use InvalidArgumentException;

final class PageCountUnavailable extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('Total page count is unavailable until renderer compatibility is verified.');
    }
}
