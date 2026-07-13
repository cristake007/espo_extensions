<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Classes\Record\Hooks\HolidayProfile;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\Hook\UpdateHook;
use Espo\Core\Record\UpdateParams;
use Espo\ORM\Entity;

final class BeforeUpdate implements UpdateHook
{
    private const MANAGED_ATTRIBUTE_LIST = [
        'annualEntitlement',
        'balance',
        'nextResetDate',
        'isInitialized',
        'resetPending',
        'pendingResetDate',
        'pendingResetKey',
    ];

    public function process(Entity $entity, UpdateParams $params): void
    {
        foreach (self::MANAGED_ATTRIBUTE_LIST as $attribute) {
            if ($entity->isAttributeChanged($attribute)) {
                throw new Forbidden('Holiday profile accounting fields can only be changed by HolidayBalanceService.');
            }
        }
    }
}
