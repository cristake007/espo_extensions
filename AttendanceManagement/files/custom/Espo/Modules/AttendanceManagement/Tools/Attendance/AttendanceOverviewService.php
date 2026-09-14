<?php

declare(strict_types=1);

namespace Espo\Modules\AttendanceManagement\Tools\Attendance;

use DateTimeImmutable;
use DateTimeInterface;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Entities\Notification;
use Espo\Entities\User;
use Espo\ORM\EntityManager;

final class AttendanceOverviewService
{
    private const ENTITY_TYPE = 'AttendanceRecord';
    private const STATUS_HOLIDAY = 'Holiday';
    private const SOURCE_APPROVED_HOLIDAY = 'ApprovedHoliday';

    public function __construct(
        private EntityManager $entityManager,
        private DateTimeUtil $dateTime,
        private NonWorkingDayProvider $nonWorkingDayProvider,
        private ApprovedHolidayProvider $approvedHolidayProvider,
        private AttendanceAccessChecker $accessChecker,
    ) {}

    /** @return array<string, mixed> */
    public function getOverview(?string $month): array
    {
        $this->accessChecker->assertManager();
        $today = $this->dateTime->getToday()->toString();
        $month = $this->normalizeMonth($month, $today);
        $monthStart = $month . '-01';
        $monthEnd = (new DateTimeImmutable($monthStart))->modify('last day of this month')->format('Y-m-d');
        $users = $this->entityManager
            ->getRDBRepositoryByClass(User::class)
            ->where([
                'type' => [User::TYPE_REGULAR, User::TYPE_ADMIN],
                'isActive' => true,
            ])
            ->order('name')
            ->find();
        $userIds = [];
        $userRows = [];

        foreach ($users as $user) {
            $userId = (string) $user->getId();
            $userIds[] = $userId;
            $userRows[$userId] = [
                'id' => $userId,
                'name' => (string) $user->get('name'),
                'signedDays' => 0,
                'missingDays' => 0,
            ];
        }

        $recordMap = $this->getRecordMap($userIds, $monthStart, $monthEnd);
        $holidayMap = [];

        foreach ($userIds as $userId) {
            $holidayMap[$userId] = $this->approvedHolidayProvider
                ->getDates($userId, $monthStart, $monthEnd);
        }

        $nonWorkingDates = array_fill_keys(
            $this->nonWorkingDayProvider->getDates($monthStart, $monthEnd),
            true,
        );
        $dates = $this->buildWorkingDates($monthStart, $monthEnd, $nonWorkingDates);
        $rows = [];
        $missingByUser = [];
        $missingCount = 0;
        $signedCount = 0;
        $futureCount = 0;

        foreach ($dates as $date) {
            $isFuture = $date > $today;
            $cells = [];

            foreach ($userIds as $userId) {
                $record = $recordMap[$userId][$date] ?? null;
                $isHoliday = isset($holidayMap[$userId][$date]);
                $status = $isHoliday ? self::STATUS_HOLIDAY : $record?->get('status');
                $source = $isHoliday ? self::SOURCE_APPROVED_HOLIDAY : $record?->get('source');
                $isMissing = !$isFuture && !$status;

                if ($isFuture) {
                    $futureCount++;
                } elseif ($isMissing) {
                    $missingCount++;
                    $userRows[$userId]['missingDays']++;
                    $missingByUser[$userId][] = $date;
                } else {
                    $signedCount++;
                    $userRows[$userId]['signedDays']++;
                }

                $cells[] = [
                    'userId' => $userId,
                    'status' => $status,
                    'source' => $source,
                    'isMissing' => $isMissing,
                    'isFuture' => $isFuture,
                ];
            }

            $rows[] = ['date' => $date, 'isFuture' => $isFuture, 'cells' => $cells];
        }

        $missingUsers = [];

        foreach ($missingByUser as $userId => $missingDates) {
            $missingUsers[] = [
                'id' => $userId,
                'name' => $userRows[$userId]['name'],
                'missingDays' => count($missingDates),
                'missingDates' => $missingDates,
            ];
        }

        return [
            'isManager' => true,
            'month' => $month,
            'today' => $today,
            'currentMonth' => substr($today, 0, 7),
            'users' => array_values($userRows),
            'rows' => $rows,
            'missingUsers' => $missingUsers,
            'signedCount' => $signedCount,
            'missingCount' => $missingCount,
            'futureCount' => $futureCount,
            'downloadReady' => $rows !== [] && $userRows !== [] && $signedCount > 0 && $missingCount === 0,
        ];
    }

    /** @return array{sent: int, users: list<array{id: string, name: string}>} */
    public function sendReminders(?string $month): array
    {
        $overview = $this->getOverview($month);
        $sentUsers = [];

        foreach ($overview['missingUsers'] as $missingUser) {
            $dates = array_map(
                static fn (string $date): string => (new DateTimeImmutable($date))->format('d.m.Y'),
                $missingUser['missingDates'],
            );
            /** @var Notification $notification */
            $notification = $this->entityManager->getNewEntity(Notification::ENTITY_TYPE);
            $notification->set([
                'type' => Notification::TYPE_MESSAGE,
                'userId' => $missingUser['id'],
                'read' => false,
                'message' => sprintf(
                    'Va rugam sa completati condica de prezenta pentru: %s.',
                    implode(', ', $dates),
                ),
                'data' => [
                    'url' => '#Attendance',
                    'month' => $overview['month'],
                ],
            ]);
            $this->entityManager->saveEntity($notification);
            $sentUsers[] = ['id' => $missingUser['id'], 'name' => $missingUser['name']];
        }

        return ['sent' => count($sentUsers), 'users' => $sentUsers];
    }

    /** @param list<string> $userIds @return array<string, array<string, \Espo\ORM\Entity>> */
    private function getRecordMap(array $userIds, string $dateStart, string $dateEnd): array
    {
        if ($userIds === []) {
            return [];
        }

        $records = $this->entityManager
            ->getRDBRepository(self::ENTITY_TYPE)
            ->where([
                'userId' => $userIds,
                'date>=' => $dateStart,
                'date<=' => $dateEnd,
            ])
            ->find();
        $map = [];

        foreach ($records as $record) {
            $map[(string) $record->get('userId')][(string) $record->get('date')] = $record;
        }

        return $map;
    }

    /** @param array<string, true> $nonWorkingDates @return list<string> */
    private function buildWorkingDates(string $dateStart, string $dateEnd, array $nonWorkingDates): array
    {
        $date = new DateTimeImmutable($dateStart);
        $end = new DateTimeImmutable($dateEnd);
        $dates = [];

        while ($date <= $end) {
            $value = $date->format('Y-m-d');

            if ($this->isWorkingDate($date, $nonWorkingDates)) {
                $dates[] = $value;
            }

            $date = $date->modify('+1 day');
        }

        return $dates;
    }

    /** @param array<string, true> $nonWorkingDates */
    private function isWorkingDate(DateTimeInterface $date, array $nonWorkingDates): bool
    {
        return (int) $date->format('N') <= 5 && !isset($nonWorkingDates[$date->format('Y-m-d')]);
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
}
