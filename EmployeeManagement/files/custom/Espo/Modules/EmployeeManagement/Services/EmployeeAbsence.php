<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Name\Field;
use Espo\ORM\Query\Select;
use Espo\Services\Record;
use RuntimeException;

/**
 * @extends Record<\Espo\Modules\EmployeeManagement\Entities\EmployeeAbsence>
 */
final class EmployeeAbsence extends Record
{
    /** EspoCRM 10 calendar extension point; the spelling is defined by core. */
    public function getCalenderQuery(
        string $userId,
        string $from,
        string $to,
        bool $skipAcl = false,
    ): Select {
        [$fromDate, $toDate] = $this->dateBounds($from, $to);
        $builder = $this->selectBuilderFactory
            ->create()
            ->from($this->entityType);

        // Public Calendar must never inherit a skip-ACL path from free/busy.
        $builder->withStrictAccessControl();

        $seed = $this->entityManager->getNewEntity($this->entityType);
        $select = [
            ['"EmployeeAbsence"', 'scope'],
            'id',
            'name',
            ['null', 'dateStart'],
            ['null', 'dateEnd'],
            ['null', 'status'],
            ['dateStart', 'dateStartDate'],
            ['dateEnd', 'dateEndDate'],
            ['null', 'parentType'],
            ['null', 'parentId'],
            ['null', 'createdAt'],
        ];

        $additionalAttributeList =
            $this->metadata->get(['app', 'calendar', 'additionalAttributeList']) ?? [];
        $needsColor = in_array('color', $additionalAttributeList, true) && $this->acl->checkField($this->entityType, 'color');
        foreach ($additionalAttributeList as $attribute) {
            $select[] = $attribute === 'color' && $needsColor ? ['holidayProfile.calendarColor', 'color'] : ['null', $attribute];
        }

        try {
            $queryBuilder = $builder->buildQueryBuilder();

            if ($needsColor) {
                // Preserve the original display-color setting, never a public relationship/profile payload.
                $queryBuilder->leftJoin('EmployeeHolidayProfile', 'holidayProfile', [
                    'holidayProfile.assignedUserId:' => 'assignedUserId', 'holidayProfile.deleted' => false,
                ]);
            }

            // The common feed intentionally is not restricted to the selected user's ownership.
            foreach (['name', 'dateStart', 'dateEnd'] as $field) {
                if (!$this->acl->checkField($this->entityType, $field)) { $queryBuilder->where(['id' => null]); }
            }
            if (!$this->user->isRegular() || !$this->user->isActive()) { $queryBuilder->where(['id' => null]); }

            return $queryBuilder
                ->select($select)
                ->where([
                    'dateStart<' => $toDate,
                    'dateEnd>=' => $fromDate,
                ])
                ->build();
        } catch (BadRequest | Forbidden $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    /** Core Calendar passes UTC instants; all-day storage uses the viewer's local dates. */
    private function dateBounds(string $from, string $to): array
    {
        $preferences = $this->entityManager->getRepositoryByClass(\Espo\Entities\Preferences::class)
            ->getById($this->user->getId());
        $zone = new \DateTimeZone($preferences?->getTimeZone() ?: ($this->config->get('timeZone') ?? 'UTC'));
        $utc = new \DateTimeZone('UTC');
        $start = (new \DateTimeImmutable($from, $utc))->setTimezone($zone);
        $end = (new \DateTimeImmutable($to, $utc))->setTimezone($zone);
        if ($end->format('H:i:s') !== '00:00:00') { $end = $end->modify('+1 day'); }
        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }
}
