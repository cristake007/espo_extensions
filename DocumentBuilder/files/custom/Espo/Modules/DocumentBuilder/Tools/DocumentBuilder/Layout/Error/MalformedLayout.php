<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Error;

final class MalformedLayout extends LayoutProcessingException
{
    public function __construct()
    {
        parent::__construct('The layout is not valid JSON.');
    }
}
