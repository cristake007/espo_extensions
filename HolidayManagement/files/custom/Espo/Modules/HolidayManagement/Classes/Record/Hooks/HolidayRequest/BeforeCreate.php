<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Classes\Record\Hooks\HolidayRequest;

use Espo\Core\Record\CreateParams;
use Espo\Core\Record\Hook\CreateHook;
use Espo\Modules\HolidayManagement\Tools\HolidayBalance\HolidayBalanceService;
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
