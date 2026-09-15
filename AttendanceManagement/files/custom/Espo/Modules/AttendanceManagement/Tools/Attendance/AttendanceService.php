<?php

declare(strict_types=1);

namespace Espo\Modules\AttendanceManagement\Tools\Attendance;

use DateTimeImmutable;
use DateTimeInterface;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Core\Utils\Config;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

final class AttendanceService
{
    private const ENTITY_TYPE = 'AttendanceRecord';
    private const STATUS_AT_WORK = 'AtWork';
    private const STATUS_HOLIDAY = 'Holiday';
    private const STATUS_BUSINESS_TRIP = 'BusinessTrip';
    private const SOURCE_SELF = 'Self';
    private const SOURCE_APPROVED_HOLIDAY = 'ApprovedHoliday';
    private const EDITABLE_PAST_MONTHS_CONFIG = 'attendanceManagementEditablePastMonths';
    private const DEFAULT_EDITABLE_PAST_MONTHS = 1;

    public function __construct(
        private EntityManager $entityManager,
        private User $user,
        private DateTimeUtil $dateTime,
        private Config $config,
        private NonWorkingDayProvider $nonWorkingDayProvider,
        private ApprovedHolidayProvider $approvedHolidayProvider,
    ) {}

    /** @return array<string, mixed> */
    public function getMine(?string $month): array
    {
        $this->assertInternalUser();
        $today = $this->dateTime->getToday()->toString();
        $month = $this->normalizeMonth($month, $today);
        $monthStart = $month . '-01';
        $monthEnd = (new DateTimeImmutable($monthStart))->modify('last day of this month')->format('Y-m-d');
        $editablePastMonths = $this->getEditablePastMonths();
        $editableFromMonth = (new DateTimeImmutable(substr($today, 0, 7) . '-01'))
            ->modify(sprintf('-%d months', $editablePastMonths))
            ->format('Y-m');
        $userId = (string) $this->user->getId();
        $recordMap = $this->getRecordMap($userId, $monthStart, $monthEnd);
        $approvedHolidayDates = $this->approvedHolidayProvider
            ->getDates($userId, $monthStart, $monthEnd);
        $nonWorkingDates = array_fill_keys(
            $this->nonWorkingDayProvider->getDates($monthStart, $monthEnd),
            true,
        );
        $days = [];
        $date = new DateTimeImmutable($monthStart);
        $end = new DateTimeImmutable($monthEnd);

        while ($date <= $end) {
            $dateValue = $date->format('Y-m-d');

            if ($this->isWorkingDate($date, $nonWorkingDates)) {
                $record = $recordMap[$dateValue] ?? null;
                $isHoliday = isset($approvedHolidayDates[$dateValue]);
                $isLocked = $dateValue <= $today && substr($dateValue, 0, 7) < $editableFromMonth;
                $days[] = [
                    'date' => $dateValue,
                    'status' => $isHoliday ? self::STATUS_HOLIDAY : $record?->get('status'),
                    'source' => $isHoliday ? self::SOURCE_APPROVED_HOLIDAY : $record?->get('source'),
                    'markedAt' => $isHoliday ? null : $record?->get('markedAt'),
                    'canMark' => $dateValue <= $today && !$isHoliday && !$isLocked,
                    'isLocked' => $isLocked,
                ];
            }

            $date = $date->modify('+1 day');
        }

        $todayState = $this->getTodayState(
            $today,
            $month,
            $recordMap,
            $approvedHolidayDates,
            $nonWorkingDates,
        );

        return [
            'month' => $month,
            'today' => $today,
            'currentMonth' => substr($today, 0, 7),
            'editablePastMonths' => $editablePastMonths,
            'editableFromMonth' => $editableFromMonth,
            'monthLocked' => $month < $editableFromMonth,
            'todayStatus' => $todayState['status'],
            'todaySource' => $todayState['source'],
            'todayCanMark' => $todayState['canMark'],
            'holidayIntegrationAvailable' => $this->approvedHolidayProvider->isAvailable(),
            'days' => $days,
        ];
    }

    /** @return array<string, mixed> */
    public function mark(string $date, string $status): array
    {
        $this->assertInternalUser();
        $this->validateDate($date);

        if (!in_array($status, [self::STATUS_AT_WORK, self::STATUS_BUSINESS_TRIP], true)) {
            throw new BadRequest('Attendance status must be AtWork or BusinessTrip.');
        }

        $today = $this->dateTime->getToday()->toString();

        if ($date > $today) {
            throw new BadRequest('Attendance cannot be marked for a future date.');
        }

        if (substr($date, 0, 7) < $this->getEditableFromMonth($today)) {
            throw new Conflict('Attendance for this month is locked.');
        }

        $dateValue = new DateTimeImmutable($date);
        $nonWorkingDates = array_fill_keys(
            $this->nonWorkingDayProvider->getDates($date, $date),
            true,
        );

        if (!$this->isWorkingDate($dateValue, $nonWorkingDates)) {
            throw new BadRequest('Attendance can only be marked for a working day.');
        }

        if ($this->isApprovedHoliday((string) $this->user->getId(), $date)) {
            throw new Conflict('An approved holiday controls attendance for this date.');
        }

        return $this->entityManager->getTransactionManager()->run(
            function () use ($date, $status): array {
                $record = $this->entityManager
                    ->getRDBRepository(self::ENTITY_TYPE)
                    ->where([
                        'userId' => $this->user->getId(),
                        'date' => $date,
                    ])
                    ->forUpdate()
                    ->findOne();

                if (!$record) {
                    $record = $this->entityManager->getNewEntity(self::ENTITY_TYPE);
                }

                $record->set([
                    'name' => sprintf('%s - %s', $this->user->get('name'), $date),
                    'date' => $date,
                    'status' => $status,
                    'source' => self::SOURCE_SELF,
                    'userId' => $this->user->getId(),
                    'userName' => $this->user->get('name'),
                    'markedAt' => DateTimeUtil::getSystemNowString(),
                ]);
                $this->entityManager->saveEntity($record);

                return $this->mapRecord($record);
            },
        );
    }

    /** @return array<string, Entity> */
    private function getRecordMap(string $userId, string $dateStart, string $dateEnd): array
    {
        $records = $this->entityManager
            ->getRDBRepository(self::ENTITY_TYPE)
            ->where([
                'userId' => $userId,
                'date>=' => $dateStart,
                'date<=' => $dateEnd,
            ])
            ->find();
        $map = [];

        foreach ($records as $record) {
            $map[(string) $record->get('date')] = $record;
        }

        return $map;
    }

    /**
     * @param array<string, Entity> $recordMap
     * @param array<string, true> $approvedHolidayDates
     * @param array<string, true> $nonWorkingDates
     * @return array{status: mixed, source: mixed, canMark: bool}
     */
    private function getTodayState(
        string $today,
        string $month,
        array $recordMap,
        array $approvedHolidayDates,
        array $nonWorkingDates,
    ): array {
        $userId = (string) $this->user->getId();

        if (substr($today, 0, 7) === $month) {
            $record = $recordMap[$today] ?? null;
            $isHoliday = isset($approvedHolidayDates[$today]);
            $todayNonWorkingDates = $nonWorkingDates;
        } else {
            $record = $this->entityManager
                ->getRDBRepository(self::ENTITY_TYPE)
                ->where(['userId' => $userId, 'date' => $today])
                ->findOne();
            $isHoliday = $this->isApprovedHoliday($userId, $today);
            $todayNonWorkingDates = array_fill_keys(
                $this->nonWorkingDayProvider->getDates($today, $today),
                true,
            );
        }

        return [
            'status' => $isHoliday ? self::STATUS_HOLIDAY : $record?->get('status'),
            'source' => $isHoliday ? self::SOURCE_APPROVED_HOLIDAY : $record?->get('source'),
            'canMark' => $this->isWorkingDate(
                new DateTimeImmutable($today),
                $todayNonWorkingDates,
            ) && !$isHoliday,
        ];
    }

    private function isApprovedHoliday(string $userId, string $date): bool
    {
        return isset($this->approvedHolidayProvider->getDates($userId, $date, $date)[$date]);
    }

    private function normalizeMonth(?string $month, string $today): string
    {
        $month = trim((string) $month);

        if ($month === '') {
            return substr($today, 0, 7);
        }

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw new BadRequest('Month must use the YYYY-MM format.');
        }

        if ($month > substr($today, 0, 7)) {
            throw new BadRequest('A future attendance month cannot be opened.');
        }

        return $month;
    }

    private function validateDate(string $date): void
    {
        $value = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if (!$value || $value->format('Y-m-d') !== $date) {
            throw new BadRequest('Attendance date must use the YYYY-MM-DD format.');
        }
    }

    private function getEditablePastMonths(): int
    {
        $value = $this->config->get(self::EDITABLE_PAST_MONTHS_CONFIG);

        if (!is_int($value) && !is_numeric($value)) {
            return self::DEFAULT_EDITABLE_PAST_MONTHS;
        }

        return max(0, min(120, (int) $value));
    }

    private function getEditableFromMonth(string $today): string
    {
        return (new DateTimeImmutable(substr($today, 0, 7) . '-01'))
            ->modify(sprintf('-%d months', $this->getEditablePastMonths()))
            ->format('Y-m');
    }

    /** @param array<string, true> $nonWorkingDates */
    private function isWorkingDate(DateTimeInterface $date, array $nonWorkingDates): bool
    {
        $dateValue = $date->format('Y-m-d');

        return (int) $date->format('N') <= 5 && !isset($nonWorkingDates[$dateValue]);
    }

    /** @return array<string, mixed> */
    private function mapRecord(Entity $record): array
    {
        return [
            'id' => $record->getId(),
            'date' => $record->get('date'),
            'status' => $record->get('status'),
            'source' => $record->get('source'),
            'markedAt' => $record->get('markedAt'),
        ];
    }

    private function assertInternalUser(): void
    {
        if (
            !(bool) $this->user->get('isActive') ||
            !in_array($this->user->get('type'), [User::TYPE_REGULAR, User::TYPE_ADMIN], true)
        ) {
            throw new Forbidden('Only active internal users can mark attendance.');
        }
    }
}
