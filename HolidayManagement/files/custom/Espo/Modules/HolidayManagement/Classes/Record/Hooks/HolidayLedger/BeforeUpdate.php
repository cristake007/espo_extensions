<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Classes\Record\Hooks\HolidayLedger;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\Hook\UpdateHook;
use Espo\Core\Record\UpdateParams;
use Espo\ORM\Entity;
use Espo\Core\Utils\Language;

final class BeforeUpdate implements UpdateHook
{
    public function __construct(private Language $language)
    {}

    public function process(Entity $entity, UpdateParams $params): void
    {
        throw new Forbidden($this->language->translateLabel(
            'ledgerImmutable', 'messages', 'HolidayLedger'
        ));
    }
}
