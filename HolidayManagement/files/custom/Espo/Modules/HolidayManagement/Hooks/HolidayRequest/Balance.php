<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Hooks\HolidayRequest;

use Espo\Core\Hook\Hook\BeforeRemove;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\HolidayManagement\Tools\HolidayBalance\HolidayBalanceService;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\RemoveOptions;
use Espo\ORM\Repository\Option\SaveOptions;

final class Balance implements BeforeSave, BeforeRemove
{
    public static int $order = 1;

    public function __construct(private HolidayBalanceService $balanceService)
    {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->isNew()) {
            $this->balanceService->reserveHoliday($entity);
            return;
        }

        $status = $entity->get('status') ?: 'Pending';
        $fetchedStatus = $entity->getFetched('status') ?: 'Pending';

        if ($status !== $fetchedStatus) {
            $this->balanceService->processApprovalDecision($entity);
            return;
        }

        $this->balanceService->adjustHoliday($entity);
    }

    public function beforeRemove(Entity $entity, RemoveOptions $options): void
    {
        $this->balanceService->cancelHoliday($entity);
    }
}
