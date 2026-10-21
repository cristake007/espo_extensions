<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Entities;

use Espo\Core\Templates\Entities\Event;

final class EmployeeHolidayRequest extends Event
{
    public const ENTITY_TYPE = 'EmployeeHolidayRequest';
}
