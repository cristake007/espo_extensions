<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Classes\Record\Hooks\EmployeeHolidayRequest;

use Espo\Core\Record\Hook\UpdateHook;
use Espo\Core\Record\UpdateParams;
use Espo\Modules\EmployeeManagement\Tools\HolidayBalance\HolidayBalanceService;
use Espo\ORM\Entity;

final class BeforeUpdate implements UpdateHook
{
    public function __construct(private HolidayBalanceService $balanceService)
    {}

    public function process(Entity $entity, UpdateParams $params): void
    {
        $this->balanceService->prepareHolidayForUpdate($entity);
    }
}
