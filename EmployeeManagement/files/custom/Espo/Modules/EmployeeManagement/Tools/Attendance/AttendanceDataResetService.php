<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Tools\Attendance;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Entities\Notification;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/** Destructive Attendance test-data reset with normative and configuration boundaries. */
final class AttendanceDataResetService
{
    private const RECORD = 'EmployeeAttendanceRecord';
    private const MONTH = 'EmployeeAttendanceMonth';
    private const SCHEDULE = 'EmployeeAttendanceWorkSchedule';
    private const ARCHIVE_START_MONTH_CONFIG = 'employeeAttendanceArchiveStartMonth';
    private const SKIP_PROCESSING = ['skipHooks' => true, 'silent' => true];

    public function __construct(
        private EntityManager $entityManager,
        private Config $config,
        private ConfigWriter $configWriter,
        private DateTimeUtil $dateTime,
    ) {}

    /** @return array<string, int|string> */
    public function preview(): array
    {
        return [
            'attendanceRecords' => $this->count($this->attendanceRecords()),
            'monthStates' => $this->count($this->monthStates()),
            'frozenMonths' => $this->count($this->frozenMonthStates()),
            'reminderNotifications' => $this->count($this->attendanceReminderNotifications()),
            'workSchedulesPreserved' => $this->count($this->workSchedules()),
            'archiveStartMonth' => (string) $this->config->get(
                self::ARCHIVE_START_MONTH_CONFIG,
                '',
            ),
        ];
    }

    /** @return array<string, int|string> */
    public function reset(): array
    {
        $before = $this->preview();

        $this->entityManager->getTransactionManager()->run(function (): void {
            // Attendance commands lock the beneficiary User before records and month state.
            // Taking every eligible User lock in a stable order serializes the reset with them.
            foreach ($this->eligibleEmployeesForUpdate() as $_employee) {
                // The lock itself is the operation.
            }

            foreach ($this->attendanceRecords() as $record) {
                $this->entityManager->removeEntity($record, self::SKIP_PROCESSING);
            }

            foreach ($this->attendanceReminderNotifications() as $notification) {
                $this->entityManager->removeEntity($notification, self::SKIP_PROCESSING);
            }

            foreach ($this->monthStates() as $monthState) {
                // Preserve the month identity and immutable WTC norm snapshot because Holiday
                // snapshots refer to this shared source. Only operational completion is reset.
                $monthState->set([
                    'policies' => null,
                    'frozen' => false,
                    'frozenAt' => null,
                    'frozenById' => null,
                    'revision' => (int) $monthState->get('revision') + 1,
                ]);
                $this->entityManager->saveEntity($monthState, self::SKIP_PROCESSING);
            }
        });

        $archiveStartMonth = substr($this->dateTime->getToday()->toString(), 0, 7);
        $this->configWriter->set(self::ARCHIVE_START_MONTH_CONFIG, $archiveStartMonth);
        $this->configWriter->save();
        $before['newArchiveStartMonth'] = $archiveStartMonth;

        return $before;
    }

    /** @return iterable<Entity> */
    private function attendanceRecords(): iterable
    {
        return $this->entityManager->getRDBRepository(self::RECORD)->find();
    }

    /** @return iterable<Entity> */
    private function monthStates(): iterable
    {
        return $this->entityManager->getRDBRepository(self::MONTH)->order('month')->find();
    }

    /** @return iterable<Entity> */
    private function frozenMonthStates(): iterable
    {
        return $this->entityManager->getRDBRepository(self::MONTH)->where(['frozen' => true])->find();
    }

    /** @return iterable<Entity> */
    private function workSchedules(): iterable
    {
        return $this->entityManager->getRDBRepository(self::SCHEDULE)->find();
    }

    /** @return iterable<User> */
    private function eligibleEmployeesForUpdate(): iterable
    {
        return $this->entityManager
            ->getRDBRepositoryByClass(User::class)
            ->where(['type' => User::TYPE_REGULAR, 'isActive' => true])
            ->order('id')
            ->forUpdate()
            ->find();
    }

    /** @return iterable<Notification> */
    private function attendanceReminderNotifications(): iterable
    {
        $notifications = $this->entityManager
            ->getRDBRepositoryByClass(Notification::class)
            ->where(['type' => Notification::TYPE_MESSAGE])
            ->find();

        foreach ($notifications as $notification) {
            $data = $notification->get('data');
            $data = is_object($data) ? get_object_vars($data) : (is_array($data) ? $data : []);
            $month = $data['month'] ?? null;
            $explicitMarker = ($data['employeeManagementAttendanceReminder'] ?? false) === true;
            $legacyMarker = is_string($month) &&
                preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month) === 1 &&
                str_contains(
                    (string) $notification->get('message'),
                    '#EmployeeAttendance/month/' . $month,
                );

            if ($explicitMarker || $legacyMarker) {
                yield $notification;
            }
        }
    }

    /** @param iterable<mixed> $items */
    private function count(iterable $items): int
    {
        $count = 0;

        foreach ($items as $_item) {
            $count++;
        }

        return $count;
    }
}
