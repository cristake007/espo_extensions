<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Tools\HolidayRequest;

use Espo\Core\Utils\Metadata;
use Espo\ORM\EntityManager;

final class NonWorkingDayProvider
{
    private const ENTITY_TYPE = 'ZileLibere';
    private const COUNTRY_CODE = 'RO';

    public function __construct(
        private EntityManager $entityManager,
        private Metadata $metadata,
    ) {}

    /** @return list<string> */
    public function getDates(string $dateStart, string $dateEnd): array
    {
        if (!$this->metadata->get(['entityDefs', self::ENTITY_TYPE, 'fields', 'dateStart'])) {
            return [];
        }

        $records = $this->entityManager
            ->getRDBRepository(self::ENTITY_TYPE)
            ->where([
                'countryCode' => self::COUNTRY_CODE,
                'dateStart>=' => $dateStart,
                'dateStart<=' => $dateEnd,
            ])
            ->find();
        $dates = [];

        foreach ($records as $record) {
            $date = (string) $record->get('dateStart');

            if ($date !== '') {
                $dates[$date] = true;
            }
        }

        return array_keys($dates);
    }
}
