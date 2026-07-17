<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\ZileLibere;

use Espo\Modules\ZileSarbatoare\Entities\ZileLibere;
use Espo\ORM\Entity;

final class ManualRecordPolicy
{
    public function __construct(private ZileLibereValidator $validator)
    {}

    public function normalize(Entity $entity): void
    {
        $entity->set('name', $this->validator->normalizeName($entity->get('name')));
        $entity->set('dateStart', $this->validator->normalizeDate($entity->get('dateStart')));
        $entity->set(
            'countryCode',
            $this->validator->normalizeCountryCode($entity->get('countryCode') ?: 'RO')
        );

        $entity->set('source', ZileLibere::SOURCE_MANUAL);
        $entity->set('managed', false);
        $entity->set('sourceYear', null);
        $entity->set('holidayTypes', ['Public']);
        $entity->set('nationalHoliday', true);
        $entity->set('subdivisionCodes', []);
        $entity->set('syncedAt', null);
    }
}
