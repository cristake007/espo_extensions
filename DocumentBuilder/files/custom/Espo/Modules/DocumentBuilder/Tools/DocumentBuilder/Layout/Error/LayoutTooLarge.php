<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Error;

final class LayoutTooLarge extends LayoutProcessingException
{
    public function __construct()
    {
        parent::__construct('The layout exceeds the configured size limit.');
    }
}
