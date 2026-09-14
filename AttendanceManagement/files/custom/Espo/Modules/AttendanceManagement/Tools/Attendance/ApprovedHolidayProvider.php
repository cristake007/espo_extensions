<?php

declare(strict_types=1);

namespace Espo\Modules\AttendanceManagement\Tools\Attendance;

use DateTimeImmutable;
use Espo\Core\Utils\Metadata;
use Espo\ORM\EntityManager;

final class ApprovedHolidayProvider
{
    private const ENTITY_TYPE = 'HolidayRequest';
    private const STATUS_APPROVED = 'Approved';

    public function __construct(
        private EntityManager $entityManager,
        private Metadata $metadata,
    ) {}

    /** @return array<string, true> */
    public function getDates(string $userId, string $dateStart, string $dateEnd): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $requests = $this->entityManager
            ->getRDBRepository(self::ENTITY_TYPE)
            ->where([
                'assignedUserId' => $userId,
                'status' => self::STATUS_APPROVED,
                'dateStartDate<=' => $dateEnd,
                'dateEndDate>=' => $dateStart,
            ])
            ->find();
        $dates = [];

        foreach ($requests as $request) {
            $start = max($dateStart, (string) $request->get('dateStartDate'));
            $end = min($dateEnd, (string) $request->get('dateEndDate'));
            $date = new DateTimeImmutable($start);
            $lastDate = new DateTimeImmutable($end);

            while ($date <= $lastDate) {
                $dates[$date->format('Y-m-d')] = true;
                $date = $date->modify('+1 day');
            }
        }

        return $dates;
    }

    public function isAvailable(): bool
    {
        return (bool) $this->metadata->get([
            'entityDefs', self::ENTITY_TYPE, 'fields', 'dateStartDate',
        ]) && (bool) $this->metadata->get([
            'entityDefs', self::ENTITY_TYPE, 'fields', 'dateEndDate',
        ]);
    }
}
