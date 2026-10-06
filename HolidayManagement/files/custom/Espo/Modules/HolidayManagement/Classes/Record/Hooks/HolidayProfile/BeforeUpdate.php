<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Classes\Record\Hooks\HolidayProfile;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\Hook\UpdateHook;
use Espo\Core\Record\UpdateParams;
use Espo\ORM\Entity;
use Espo\Core\Utils\Language;

final class BeforeUpdate implements UpdateHook
{
    public function __construct(private Language $language)
    {}

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
                throw new Forbidden($this->language->translateLabel(
                    'profileAccountingFieldsForbidden', 'messages', 'HolidayProfile'
                ));
            }
        }
    }
}
