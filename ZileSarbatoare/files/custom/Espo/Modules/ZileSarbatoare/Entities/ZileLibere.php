<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Entities;

use Espo\Core\Templates\Entities\Event;

class ZileLibere extends Event
{
    public const ENTITY_TYPE = 'ZileLibere';

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_NAGER_DATE = 'nager-date';
}
