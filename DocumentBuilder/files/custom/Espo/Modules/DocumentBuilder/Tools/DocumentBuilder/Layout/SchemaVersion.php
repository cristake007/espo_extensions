<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout;

enum SchemaVersion: int
{
    case V1 = 1;

    public static function current(): self
    {
        return self::V1;
    }
}
