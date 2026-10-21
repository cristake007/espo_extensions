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
 * @extends Record<\Espo\Modules\EmployeeManagement\Entities\EmployeeHolidayRequest>
 */
final class EmployeeHolidayRequest extends Record
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
            ['"EmployeeHolidayRequest"', 'scope'],
            'id',
            'name',
            ['null', 'dateStart'],
            ['null', 'dateEnd'],
            ['status', 'status'],
            ['dateStartDate', 'dateStartDate'],
            ['dateEndDate', 'dateEndDate'],
            ['null', 'parentType'],
            ['null', 'parentId'],
            Field::CREATED_AT,
        ];

        $additionalAttributeList =
            $this->metadata->get(['app', 'calendar', 'additionalAttributeList']) ?? [];
        $requiresProfileJoin = false;

        foreach ($additionalAttributeList as $attribute) {
            if ($attribute === 'color') {
                $select[] = ['profile.calendarColor', 'color'];
                $requiresProfileJoin = true;

                continue;
            }

            $select[] = ['null', $attribute]; // Never export new private attributes via global Calendar metadata.
        }

        try {
            $queryBuilder = $builder->buildQueryBuilder();

            $queryBuilder->join('assignedUser')->where(['assignedUser.type' => 'regular', 'assignedUser.isActive' => true]);
            if (!$this->user->isRegular() || !$this->user->isActive() ||
                !$this->acl->checkField($this->entityType, 'name') ||
                !$this->acl->checkField($this->entityType, 'dateStart') ||
                !$this->acl->checkField($this->entityType, 'dateEnd') ||
                !$this->acl->checkField($this->entityType, 'status') ||
                ($userId !== $this->user->getId() && !$this->acl->checkScope('EmployeeHolidayApproval'))) {
                $queryBuilder->where(['id' => null]);
            }
            if ($requiresProfileJoin) {
                $queryBuilder->leftJoin('profile');
            }

            return $queryBuilder
                ->select($select)
                ->where([
                    'dateStartDate<' => $toDate,
                    'dateEndDate>=' => $fromDate,
                    'status' => 'Pending',
                    'assignedUserId' => $userId,
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
