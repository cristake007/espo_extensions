<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Tools\HolidayOverview;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Entities\User;
use Espo\Modules\EmployeeManagement\Tools\HolidayAdministration\EmployeeManagementAccessChecker;
use Espo\Modules\EmployeeManagement\Tools\EmployeeHolidayRequest\NonWorkingDayProvider;
use Espo\ORM\EntityManager;

final class HolidayOverviewService
{
    private const VISIBLE_STATUSES = ['Pending', 'Approved', 'CancellationPending'];

    public function __construct(
        private EntityManager $entityManager,
        private DateTimeUtil $dateTime,
        private NonWorkingDayProvider $nonWorkingDayProvider,
        private EmployeeManagementAccessChecker $accessChecker,
    ) {}

    /** @return array<string, mixed> */
    public function get(?string $month): array
    {
        $this->accessChecker->assertOverview();
        $today = $this->dateTime->getToday()->toString();
        $month = $this->normalizeMonth($month, $today);
        $monthStart = $month . '-01';
        $monthEnd = (new DateTimeImmutable($monthStart))
            ->modify('last day of this month')
            ->format('Y-m-d');
        $users = $this->entityManager
            ->getRDBRepositoryByClass(User::class)
            ->where(['type' => User::TYPE_REGULAR, 'isActive' => true])
            ->order('name')
            ->find();
        $userIds = [];
        $userRows = [];

        foreach ($users as $user) {
            $id = (string) $user->getId();
            $userIds[] = $id;
            $userRows[$id] = [
                'id' => $id,
                'name' => (string) $user->get('name'),
                'profileInitialized' => false,
                'balance' => null,
                'annualEntitlement' => null,
                'nextResetDate' => null,
                'pendingDays' => 0,
                'availableDays' => null,
            ];
        }

        if ($userIds !== []) {
            $this->addProfiles($userRows, $userIds);
            $this->addPendingDays($userRows, $userIds);
        }

        $requestMap = [];
        $statusCounts = array_fill_keys(self::VISIBLE_STATUSES, 0);

        if ($userIds !== []) {
            $requests = $this->entityManager
                ->getRDBRepository('EmployeeHolidayRequest')
                ->where([
                    'assignedUserId' => $userIds,
                    'dateStartDate<=' => $monthEnd,
                    'dateEndDate>=' => $monthStart,
                    'status' => self::VISIBLE_STATUSES,
                ])
                ->find();

            foreach ($requests as $request) {
                $userId = (string) $request->get('assignedUserId');
                $start = max($monthStart, (string) $request->get('dateStartDate'));
                $end = min($monthEnd, (string) $request->get('dateEndDate'));
                $status = (string) $request->get('status');
                $statusCounts[$status]++;

                for ($date = $start; $date <= $end; $date = $this->nextDate($date)) {
                    $requestMap[$userId][$date] = [
                        'requestId' => (string) $request->getId(),
                        'status' => $status,
                    ];
                }
            }
        }

        $nonWorkingDates = array_fill_keys(
            $this->nonWorkingDayProvider->getDates($monthStart, $monthEnd),
            true,
        );
        $rows = [];

        for ($date = $monthStart; $date <= $monthEnd; $date = $this->nextDate($date)) {
            $dayOfWeek = (int) (new DateTimeImmutable($date))->format('N');
            $cells = [];

            foreach ($userIds as $userId) {
                $request = $requestMap[$userId][$date] ?? null;
                $cells[] = [
                    'userId' => $userId,
                    'date' => $date,
                    'requestId' => $request['requestId'] ?? null,
                    'status' => $request['status'] ?? null,
                ];
            }

            $rows[] = [
                'date' => $date,
                'isWeekend' => $dayOfWeek >= 6,
                'isPublicHoliday' => isset($nonWorkingDates[$date]),
                'cells' => $cells,
            ];
        }

        $awayToday = 0;

        foreach ($userIds as $userId) {
            $status = $requestMap[$userId][$today]['status'] ?? null;

            if ($status === 'Approved' || $status === 'CancellationPending') {
                $awayToday++;
            }
        }

        return [
            'month' => $month,
            'today' => $today,
            'currentMonth' => substr($today, 0, 7),
            'users' => array_values($userRows),
            'rows' => $rows,
            'summary' => [
                'employeeCount' => count($userRows),
                'awayToday' => $awayToday,
                'pendingRequests' => $statusCounts['Pending'],
                'approvedRequests' => $statusCounts['Approved'],
                'cancellationPendingRequests' => $statusCounts['CancellationPending'],
                'missingProfiles' => count(array_filter(
                    $userRows,
                    static fn (array $row): bool => !$row['profileInitialized'],
                )),
            ],
        ];
    }

    /** @param array<string, array<string, mixed>> $userRows @param list<string> $userIds */
    private function addProfiles(array &$userRows, array $userIds): void
    {
        $profiles = $this->entityManager
            ->getRDBRepository('EmployeeHolidayProfile')
            ->where(['userId' => $userIds])
            ->find();

        foreach ($profiles as $profile) {
            $userId = (string) $profile->get('userId');

            if (!isset($userRows[$userId])) {
                continue;
            }

            $initialized = (bool) $profile->get('isInitialized');
            $balance = $initialized ? (float) $profile->get('balance') : null;
            $userRows[$userId]['profileInitialized'] = $initialized;
            $userRows[$userId]['balance'] = $balance;
            $userRows[$userId]['annualEntitlement'] = $initialized
                ? (float) $profile->get('annualEntitlement')
                : null;
            $userRows[$userId]['nextResetDate'] = $initialized
                ? $profile->get('nextResetDate')
                : null;
        }
    }

    /** @param array<string, array<string, mixed>> $userRows @param list<string> $userIds */
    private function addPendingDays(array &$userRows, array $userIds): void
    {
        $requests = $this->entityManager
            ->getRDBRepository('EmployeeHolidayRequest')
            ->where(['assignedUserId' => $userIds, 'status' => 'Pending'])
            ->find();

        foreach ($requests as $request) {
            $userId = (string) $request->get('assignedUserId');

            if (isset($userRows[$userId])) {
                $userRows[$userId]['pendingDays'] += (int) $request->get('days');
            }
        }

        foreach ($userRows as &$row) {
            if ($row['balance'] !== null) {
                // Pending requests are already reserved from the stored balance.
                $row['availableDays'] = (float) $row['balance'] + (int) $row['pendingDays'];
            }
        }
        unset($row);
    }

    private function normalizeMonth(?string $month, string $today): string
    {
        $month = $month ?: substr($today, 0, 7);

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw new BadRequest('Month must use the YYYY-MM format.');
        }

        return $month;
    }

    private function nextDate(string $date): string
    {
        return (new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d');
    }
}
