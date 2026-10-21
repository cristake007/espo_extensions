<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Classes\Record\Hooks\EmployeeAttendanceRecord;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\Hook\UpdateHook;
use Espo\Core\Record\UpdateParams;
use Espo\ORM\Entity;

final class BeforeUpdate implements UpdateHook
{
    public function process(Entity $entity, UpdateParams $params): void
    {
        throw new Forbidden('Attendance records can only be updated through the attendance page.');
    }
}
