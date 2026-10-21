<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Classes\Record\Hooks\EmployeeHolidayRequest;

use Espo\Core\Record\CreateParams;
use Espo\Core\Record\Hook\CreateHook;
use Espo\Modules\EmployeeManagement\Tools\HolidayBalance\HolidayBalanceService;
use Espo\ORM\Entity;

final class BeforeCreate implements CreateHook
{
    public function __construct(private HolidayBalanceService $balanceService)
    {}

    public function process(Entity $entity, CreateParams $params): void
    {
        $this->balanceService->prepareHolidayForCreate($entity);
    }
}
