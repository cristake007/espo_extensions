<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Error;

final class UnsupportedSchemaVersion extends LayoutProcessingException
{
    public function __construct()
    {
        parent::__construct('The layout schema version is missing or unsupported.');
    }
}
