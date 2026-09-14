<?php

declare(strict_types=1);

namespace Espo\Modules\AttendanceManagement\Classes\Record\Hooks\AttendanceRecord;

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
