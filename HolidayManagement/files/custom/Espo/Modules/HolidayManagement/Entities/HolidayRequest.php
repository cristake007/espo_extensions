<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Entities;

use Espo\Core\Templates\Entities\Event;

final class HolidayRequest extends Event
{
    public const ENTITY_TYPE = 'HolidayRequest';
}
