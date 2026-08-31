<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Classes\Record\Hooks\HolidayRequest;

use Espo\Core\Record\Hook\UpdateHook;
use Espo\Core\Record\UpdateParams;
use Espo\Modules\HolidayManagement\Tools\HolidayBalance\HolidayBalanceService;
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
