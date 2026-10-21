<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Tools\Attendance;

use DateTimeImmutable;
use DateTimeInterface;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Entities\Notification;
use Espo\Entities\User;
use Espo\ORM\EntityManager;
use Espo\Modules\EmployeeManagement\Tools\EmployeeHolidayRequest\NonWorkingDayProvider;

final class AttendanceOverviewService
{
    private const ENTITY_TYPE = 'EmployeeAttendanceRecord';
    private const SCHEDULE_ENTITY_TYPE = 'EmployeeAttendanceWorkSchedule';
    private const STATUS_AT_WORK = 'AtWork';
    private const STATUS_HOLIDAY = 'Holiday';
    private const STATUS_BUSINESS_TRIP = 'BusinessTrip';
    private const STATUS_MEDICAL_LEAVE = 'MedicalLeave';
    private const STATUS_CHILD_CARE_LEAVE = 'ChildCareLeave';
    private const SOURCE_MANAGER = 'Manager';
    private const SOURCE_APPROVED_HOLIDAY = 'ApprovedHoliday';
    private const SOURCE_CIC_PERIOD = 'CicPeriod';
    private const EDITABLE_PAST_MONTHS_CONFIG = 'employeeAttendanceEditablePastMonths';
    private const ALLOW_INCOMPLETE_EXPORTS_CONFIG = 'employeeAttendanceAllowIncompleteExports';
    private const ARCHIVE_START_MONTH_CONFIG = 'employeeAttendanceArchiveStartMonth';

    public function __construct(
        private EntityManager $entityManager,
        private DateTimeUtil $dateTime,
        private Config $config,
        private NonWorkingDayProvider $nonWorkingDayProvider,
        private ApprovedHolidayProvider $approvedHolidayProvider,
        private AttendanceAccessChecker $accessChecker,
        private AttendanceMonthService $months,
        private User $user,
    ) {}

    /** @return array<string, mixed> */
    public function getOverview(?string $month): array
    {
        $this->accessChecker->assertOverview();
        return $this->entityManager->getTransactionManager()->run(fn (): array => $this->buildOverview($month));
    }

    /** Internal aggregate for completion under the month lock; never returned without authorization. */
    public function buildOverview(?string $month): array
    {
        $today = $this->dateTime->getToday()->toString();
        $availableFromMonth = $this->getAvailableFromMonth($today);
        $month = $this->normalizeMonth($month, $today, $availableFromMonth);
        $editableFromMonth = $this->getEditableFromMonth($today);
        $monthState = $this->months->get($month);
        $monthStart = $month . '-01';
        $monthEnd = (new DateTimeImmutable($monthStart))->modify('last day of this month')->format('Y-m-d');
        $users = $this->entityManager
            ->getRDBRepositoryByClass(User::class)
            ->where([
                'type' => User::TYPE_REGULAR,
                'isActive' => true,
            ])
            ->order('name')
            ->find();
        $userIds = [];
        $userRows = [];

        foreach ($users as $user) {
            $userId = (string) $user->getId();
            if ($monthState->get('frozen') && $this->months->norm($monthState,$userId) === null) { continue; }
            $userIds[] = $userId;
            $userRows[$userId] = [
                'id' => $userId,
                'name' => (string) $user->get('name'),
                'signedDays' => 0,
                'missingDays' => 0,
                'schedule' => null,
            ];
        }

        $scheduleMap = $this->getScheduleMap($userIds);

        foreach ($scheduleMap as $userId => $schedule) {
            $userRows[$userId]['schedule'] = $schedule;
        }

        // Preserve original explicit Remote/CIC administration; only normative WTC results are immutable.
        // Authorized metadata corrections remain audited and never unfreeze the sheet.
        $normMap=[];
        foreach ($userIds as $id) {
            $normMap[$id]=$this->months->norm($monthState,$id);
            $userRows[$id]['normConfigured']=$normMap[$id]!==null;
            $userRows[$id]['schedule']=$scheduleMap[$id] ?? ['remote'=>false,'cic'=>false];
            $userRows[$id]['normHours']=array_sum($normMap[$id]['days']??[]);
            $patterns = [];
            foreach ($normMap[$id]['ranges'] ?? [] as $date => $ranges) {
                if (!$ranges) { continue; }
                $key = json_encode($ranges, JSON_THROW_ON_ERROR);
                $patterns[$key] ??= ['ranges' => $ranges, 'dates' => []];
                $patterns[$key]['dates'][] = $date;
            }
            $userRows[$id]['schedulePatterns'] = array_values($patterns);
        }
        $recordMap = $this->getRecordMap($userIds, $monthStart, $monthEnd);
        $holidayMap = $this->approvedHolidayProvider->getDatesForUsers($userIds,$monthStart,$monthEnd);

        $nonWorkingDates = array_fill_keys(
            $this->nonWorkingDayProvider->getDates($monthStart, $monthEnd),
            true,
        );
        $dateMap=[];foreach($normMap as $norm){foreach(array_keys($norm['days']??[]) as $date){$dateMap[$date]=true;}}
        $dates=array_keys($dateMap);sort($dates);
        $rows = [];
        $missingByUser = [];
        $missingCount = 0;
        $signedCount = 0;
        $futureCount = 0;
        $scheduleMissingCount = count(array_filter(
            $userRows,
            fn (array $userRow): bool => $this->months->norm($monthState,$userRow['id']) === null,
        ));

        foreach ($dates as $date) {
            $isFuture = $date > $today;
            $cells = [];

            foreach ($userIds as $userId) {
                $record = $recordMap[$userId][$date] ?? null;
                $working=isset($normMap[$userId]['days'][$date]);
                $dailyRanges=$normMap[$userId]['ranges'][$date]??[];
                $isHoliday = $working && isset($holidayMap[$userId][$date]);
                $isCic = $working && $this->isCicDate($scheduleMap[$userId] ?? null, $date) && $record?->get('status') !== self::STATUS_MEDICAL_LEAVE;
                $status = !$working ? null : ($isHoliday
                    ? self::STATUS_HOLIDAY
                    : ($isCic ? self::STATUS_CHILD_CARE_LEAVE : $record?->get('status')));
                $source = !$working ? null : ($isHoliday
                    ? self::SOURCE_APPROVED_HOLIDAY
                    : ($isCic ? self::SOURCE_CIC_PERIOD : $record?->get('source')));
                $isMissing = $working && !$isFuture && !$status;

                if (!$working) {
                    // Non-normative cells do not affect completeness or exports.
                } elseif ($isFuture) {
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
                    'date' => $date,
                    'status' => $status,
                    'source' => $source,
                    'revision' => (int)($record?->get('revision')??0),
                    'markedAt' => $record?->get('markedAt'),
                    'isWorkingDay' => $working,
                    'cicActive' => $working && $this->isCicDate($scheduleMap[$userId]??null,$date),
                    'normHours' => $normMap[$userId]['days'][$date] ?? 0,
                    'schedule' => ['startTime'=>implode(' / ',array_column($dailyRanges,0)), 'endTime'=>implode(' / ',array_column($dailyRanges,1)), 'remote'=>(bool)($scheduleMap[$userId]['remote']??false)],
                    'canManage' => $working && !$isFuture && !$isHoliday && $this->accessChecker->isManager(),
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

        $hasExportData = $rows !== [] && $userRows !== [];
        $registerComplete = $hasExportData && $signedCount > 0 &&
            $missingCount === 0 && $scheduleMissingCount === 0 && $futureCount === 0;
        if ($registerComplete && !$monthState->get('frozen')) {
            $monthState->set(['frozen'=>true,'frozenAt'=>DateTimeUtil::getSystemNowString(),
                'frozenById'=>$this->user->getId(),'policies'=>(object)$scheduleMap]);
            $this->entityManager->saveEntity($monthState);
        }
        $incompleteExportsAllowed = (bool) $this->config->get(
            self::ALLOW_INCOMPLETE_EXPORTS_CONFIG,
        );
        $exportAllowed=false;
        try {$this->accessChecker->assertExport();$exportAllowed=true;} catch (\Espo\Core\Exceptions\Forbidden) {}
        $exportDisabledReason = $exportAllowed ? null : ($this->config->get('exportDisabled')
            ? 'Global Export Disabled' : 'Export Permission Required');
        $pdfDownloadAllowed = false;
        try { $this->accessChecker->assertPdfDownload(); $pdfDownloadAllowed = true; } catch (\Espo\Core\Exceptions\Forbidden) {}
        $pdfDownloadDisabledReason = $pdfDownloadAllowed ? null : ($this->config->get('exportDisabled')
            ? 'Global Export Disabled' : 'Export Permission Required');

        return [
            'isManager' => $this->accessChecker->isManager(),
            'canManageSchedules' => $this->accessChecker->canManageSchedules(),
            'frozen' => (bool)$monthState->get('frozen'),
            'monthRevision' => (int)$monthState->get('revision'),
            'editableFromMonth' => $editableFromMonth,
            'editablePastMonths' => max(0,min(120,(int)$this->config->get(self::EDITABLE_PAST_MONTHS_CONFIG,1))),
            'month' => $month,
            'today' => $today,
            'currentMonth' => substr($today, 0, 7),
            'availableFromMonth' => $availableFromMonth,
            'monthEditable' => $month >= $editableFromMonth && !$monthState->get('frozen'),
            'users' => array_values($userRows),
            'rows' => $rows,
            'missingUsers' => $missingUsers,
            'signedCount' => $signedCount,
            'missingCount' => $missingCount,
            'futureCount' => $futureCount,
            'scheduleMissingCount' => $scheduleMissingCount,
            'registerComplete' => $registerComplete,
            'incompleteExportsAllowed' => $incompleteExportsAllowed,
            'exportAllowed' => $exportAllowed,
            'exportDisabledReason' => $exportDisabledReason,
            'pdfDownloadAllowed' => $pdfDownloadAllowed,
            'pdfDownloadDisabledReason' => $pdfDownloadDisabledReason,
            'downloadReady' => $hasExportData &&
                ($registerComplete || $incompleteExportsAllowed),
        ];
    }

    /** @return array<string, mixed> */
    public function saveSchedule(string $userId,bool $remote,bool $cic,?string $cicStartDate,?string $cicEndDate): array
    {
        $this->accessChecker->assertScheduleWrite();
        $cicStartDate=$this->normalizeOptionalDate($cicStartDate);$cicEndDate=$this->normalizeOptionalDate($cicEndDate);
        if($cic && ($cicStartDate===null || $cicEndDate===null)){throw new BadRequest('CIC start and end dates are required when CIC is enabled.');}
        if($cic && $cicStartDate>$cicEndDate){throw new BadRequest('The CIC end date must be on or after the start date.');}
        if(!$cic){$cicStartDate=null;$cicEndDate=null;}
        return $this->entityManager->getTransactionManager()->run(function()use($userId,$remote,$cic,$cicStartDate,$cicEndDate):array{
            $employee=$this->entityManager->getRDBRepositoryByClass(User::class)->where(['id'=>$userId,'type'=>User::TYPE_REGULAR,'isActive'=>true])->select(['id','name','type','isActive','firstName','lastName','workingTimeCalendarId'])->forUpdate()->findOne();
            if(!$employee){throw new NotFound('Active regular employee not found.');}
            $schedule=$this->entityManager->getRDBRepository(self::SCHEDULE_ENTITY_TYPE)->where(['assignedUserId'=>$userId])->order('effectiveFrom','DESC')->select(['id','name','assignedUserId','remote','cic','cicStartDate','cicEndDate','effectiveFrom','createdById','modifiedById'])->forUpdate()->findOne();
            if(!$schedule){$schedule=$this->entityManager->getNewEntity(self::SCHEDULE_ENTITY_TYPE);}
            $schedule->set(['name'=>$employee->get('name').' - Attendance metadata','assignedUserId'=>$userId,
                'remote'=>$remote,'cic'=>$cic,'cicStartDate'=>$cicStartDate,'cicEndDate'=>$cicEndDate,
                'effectiveFrom'=>$schedule->get('effectiveFrom')?:$this->dateTime->getToday()->toString()]);
            $this->entityManager->saveEntity($schedule);
            return ['userId'=>$userId,'remote'=>$remote,'cic'=>$cic,'cicStartDate'=>$cicStartDate,'cicEndDate'=>$cicEndDate];
        });
    }

    /** @return array<string, mixed> */
    public function markForUser(string $userId, string $date, string $status, string $reason = '', ?int $expectedRevision = null): array
    {
        $this->accessChecker->assertManager();
        return $this->markEmployee($userId,$date,$status,true,$reason,$expectedRevision);
    }

    public function markOwn(string $date,string $status,?int $expectedRevision=null): array
    {
        $this->accessChecker->assertSelf();
        return $this->markEmployee((string)$this->user->getId(),$date,$status,false,'',$expectedRevision);
    }

    private function markEmployee(string $userId,string $date,string $status,bool $manager,string $reason,?int $expectedRevision): array
    {
        $this->accessChecker->assertRecordWrite();
        if(!in_array($status,[self::STATUS_AT_WORK,self::STATUS_BUSINESS_TRIP,self::STATUS_MEDICAL_LEAVE],true)){throw new BadRequest('Unsupported attendance status.');}
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)){throw new BadRequest('Invalid attendance date.');}
        $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if(!$parsed || $parsed->format('Y-m-d')!==$date){throw new BadRequest('Attendance date must use YYYY-MM-DD.');}
        $today=$this->dateTime->getToday()->toString();$month=substr($date,0,7);
        if($date>$today){throw new BadRequest('Attendance cannot be marked for a future date.');}
        if($month<$this->getAvailableFromMonth($today)){throw new BadRequest('Attendance date predates the available archive.');}
        if($manager && trim($reason)===''){throw new BadRequest('A reason is required for managerial attendance corrections.');}
        return $this->entityManager->getTransactionManager()->run(function()use($userId,$date,$status,$manager,$reason,$expectedRevision,$month,$today):array{
            $employee=$this->entityManager->getRDBRepositoryByClass(User::class)->where(['id'=>$userId,'type'=>User::TYPE_REGULAR,'isActive'=>true])->select(['id','name','type','isActive','firstName','lastName','workingTimeCalendarId'])->forUpdate()->findOne();
            if(!$employee){throw new NotFound('Active regular employee not found.');}
            $state=$this->months->get($month);
            if(!$manager && ($state->get('frozen') || $month<$this->getEditableFromMonth($today))){throw new Conflict('Attendance for this month is locked.');}
            $norm=$this->months->norm($state,$userId);
            if(!$norm || !isset($norm['days'][$date])){throw new BadRequest('An assigned WTC working day is required; no fallback schedule.');}
            if($this->approvedHolidayProvider->getDates($userId,$date,$date)!==[]){throw new Conflict('An active approved holiday controls attendance for this date.');}
            $policies=$this->getScheduleMap([$userId]);
            if($this->isCicDate($policies[$userId]??null,$date) && $status!==self::STATUS_MEDICAL_LEAVE){throw new Conflict('CIC controls attendance; only MedicalLeave may be marked.');}
            $record=$this->entityManager->getRDBRepository(self::ENTITY_TYPE)->where(['assignedUserId'=>$userId,'date'=>$date])->select(['id','name','assignedUserId','date','status','source','markedAt','revision','correctionReason','createdById','modifiedById'])->forUpdate()->findOne();
            $revision=(int)($record?->get('revision')??0);
            if($expectedRevision!==null && $expectedRevision!==$revision){throw new Conflict('Stale attendance revision. Reload the month.');}
            if(!$record){$record=$this->entityManager->getNewEntity(self::ENTITY_TYPE);}
            $record->set(['name'=>sprintf('%s - %s',$employee->get('name'),$date),'date'=>$date,'status'=>$status,
                'source'=>$manager?self::SOURCE_MANAGER:'Self','assignedUserId'=>$userId,'markedAt'=>DateTimeUtil::getSystemNowString(),
                'correctionReason'=>$manager?trim($reason):null,'revision'=>$revision+1]);
            $this->entityManager->saveEntity($record);
            $state->set('revision',(int)$state->get('revision')+1);$this->entityManager->saveEntity($state);
            $this->buildOverview($month);
            return ['userId'=>$userId,'date'=>$date,'status'=>$status,'source'=>$record->get('source'),'revision'=>$revision+1];
        });
    }



    /** @return array{users: list<array<string, mixed>>} */
    public function getScheduleSettings(): array
    {
        $this->accessChecker->assertScheduleEditor();
        $users = $this->entityManager
            ->getRDBRepositoryByClass(User::class)
            ->where([
                'type' => User::TYPE_REGULAR,
                'isActive' => true,
            ])
            ->order('name')
            ->find();
        $userIds = [];
        $rows = [];

        foreach ($users as $user) {
            $userId = (string) $user->getId();
            $userIds[] = $userId;
            $rows[$userId] = [
                'id' => $userId,
                'name' => (string) $user->get('name'),
                'schedule' => null,
            ];
        }

        foreach ($this->getScheduleMap($userIds) as $userId => $schedule) {
            $rows[$userId]['schedule'] = $schedule;
        }

        return ['users' => array_values($rows)];
    }

    /** @return array{sent: int, users: list<array{id: string, name: string}>} */
    public function sendReminders(?string $month): array
    {
        $this->accessChecker->assertManager();
        return $this->entityManager->getTransactionManager()->run(fn (): array => $this->sendRemindersLocked($month));
    }

    private function sendRemindersLocked(?string $month): array
    {
        $overview = $this->getOverview($month);
        $sentUsers = [];

        if (!$overview['monthEditable']) {
            throw new Conflict('Reminders cannot be sent for a locked attendance month.');
        }

        foreach ($overview['missingUsers'] as $missingUser) {
            $dates = array_map(
                static fn (string $date): string => (new DateTimeImmutable($date))->format('d.m.Y'),
                $missingUser['missingDates'],
            );
            /** @var Notification $notification */
            $notification = $this->entityManager->getNewEntity(Notification::ENTITY_TYPE);
            $notification->setUserId($missingUser['id']);
            $notification->set([
                'type' => Notification::TYPE_MESSAGE,
                'read' => false,
                'message' => sprintf(
                    "Va rugam sa completati condica de prezenta pentru: %s.\n\n" .
                    '[Deschide condica de prezenta](#EmployeeAttendance/month/%s)',
                    implode(', ', $dates),
                    $overview['month'],
                ),
                'data' => [
                    'month' => $overview['month'],
                    'employeeManagementAttendanceReminder' => true,
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
                'assignedUserId' => $userIds,
                'date>=' => $dateStart,
                'date<=' => $dateEnd,
            ])
            ->find();
        $map = [];

        foreach ($records as $record) {
            $map[(string) $record->get('assignedUserId')][(string) $record->get('date')] = $record;
        }

        return $map;
    }

    /**
     * @param list<string> $userIds
     * @return array<string, array{startTime: string, endTime: string, remote: bool, cic: bool, cicStartDate: ?string, cicEndDate: ?string}>
     */
    private function getScheduleMap(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $schedules = $this->entityManager
            ->getRDBRepository(self::SCHEDULE_ENTITY_TYPE)
            ->where([
                'assignedUserId' => $userIds,
            ])
            ->order('effectiveFrom', 'DESC')
            ->find();
        $map = [];

        foreach ($schedules as $schedule) {
            $userId = (string) $schedule->get('assignedUserId');

            if (isset($map[$userId])) {
                continue;
            }

            $map[$userId] = [
                                'remote' => (bool) $schedule->get('remote'),
                'cic' => (bool) $schedule->get('cic'),
                'cicStartDate' => $this->stringOrNull($schedule->get('cicStartDate')),
                'cicEndDate' => $this->stringOrNull($schedule->get('cicEndDate')),
            ];
        }

        return $map;
    }

    /** @param array<string, true> $nonWorkingDates @return list<string> */


    /** @param array<string, true> $nonWorkingDates */


    private function normalizeMonth(
        ?string $month,
        string $today,
        string $availableFromMonth,
    ): string
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

        if ($month < $availableFromMonth) {
            throw new BadRequest('Attendance month predates the available archive.');
        }

        return $month;
    }

    private function getAvailableFromMonth(string $today): string
    {
        $configuredMonth = trim((string) $this->config->get(self::ARCHIVE_START_MONTH_CONFIG, ''));
        $currentMonth = substr($today, 0, 7);

        if (
            preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $configuredMonth) === 1 &&
            $configuredMonth <= $currentMonth
        ) {
            return $configuredMonth;
        }

        $monthList = [$currentMonth];
        $eligibleIds=[];
        foreach($this->entityManager->getRDBRepositoryByClass(User::class)->where(['type'=>User::TYPE_REGULAR,'isActive'=>true])->find() as $employee){$eligibleIds[]=$employee->getId();}
        $record = $this->entityManager
            ->getRDBRepository(self::ENTITY_TYPE)
            ->where(['assignedUserId'=>$eligibleIds])
            ->order('date', 'ASC')
            ->findOne();
        $schedule = $this->entityManager
            ->getRDBRepository(self::SCHEDULE_ENTITY_TYPE)
            ->where(['assignedUserId'=>$eligibleIds])
            ->order('effectiveFrom', 'ASC')
            ->findOne();
        $cicSchedule = $this->entityManager
            ->getRDBRepository(self::SCHEDULE_ENTITY_TYPE)
            ->where(['cic' => true, 'assignedUserId'=>$eligibleIds])
            ->order('cicStartDate', 'ASC')
            ->findOne();

        if ($record && is_string($record->get('date'))) {
            $monthList[] = substr((string) $record->get('date'), 0, 7);
        }

        if ($schedule && is_string($schedule->get('effectiveFrom'))) {
            $monthList[] = substr((string) $schedule->get('effectiveFrom'), 0, 7);
        }

        if ($cicSchedule && is_string($cicSchedule->get('cicStartDate'))) {
            $monthList[] = substr((string) $cicSchedule->get('cicStartDate'), 0, 7);
        }

        sort($monthList);

        return $monthList[0];
    }



    private function normalizeOptionalDate(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new BadRequest('CIC dates must use the YYYY-MM-DD format.');
        }

        return $value;
    }

    /** @param array<string, mixed>|null $schedule */
    private function isCicDate(?array $schedule, string $date): bool
    {
        return ($schedule['cic'] ?? false) === true &&
            is_string($schedule['cicStartDate'] ?? null) &&
            is_string($schedule['cicEndDate'] ?? null) &&
            $date >= $schedule['cicStartDate'] &&
            $date <= $schedule['cicEndDate'];
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function getEditableFromMonth(string $today): string
    {
        $value = $this->config->get(self::EDITABLE_PAST_MONTHS_CONFIG);
        $editablePastMonths = is_int($value) || is_numeric($value)
            ? max(0, min(120, (int) $value))
            : 1;

        return (new DateTimeImmutable(substr($today, 0, 7) . '-01'))
            ->modify(sprintf('-%d months', $editablePastMonths))
            ->format('Y-m');
    }
}
