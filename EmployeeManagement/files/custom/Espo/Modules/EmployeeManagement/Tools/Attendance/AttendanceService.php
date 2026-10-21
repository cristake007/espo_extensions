<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Tools\Attendance;

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
    private const ENTITY_TYPE = 'EmployeeAttendanceRecord';
    private const SCHEDULE_ENTITY_TYPE = 'EmployeeAttendanceWorkSchedule';
    private const STATUS_AT_WORK = 'AtWork';
    private const STATUS_HOLIDAY = 'Holiday';
    private const STATUS_BUSINESS_TRIP = 'BusinessTrip';
    private const STATUS_MEDICAL_LEAVE = 'MedicalLeave';
    private const STATUS_CHILD_CARE_LEAVE = 'ChildCareLeave';
    private const SOURCE_SELF = 'Self';
    private const SOURCE_APPROVED_HOLIDAY = 'ApprovedHoliday';
    private const SOURCE_CIC_PERIOD = 'CicPeriod';
    private const EDITABLE_PAST_MONTHS_CONFIG = 'employeeAttendanceEditablePastMonths';
    private const ARCHIVE_START_MONTH_CONFIG = 'employeeAttendanceArchiveStartMonth';
    private const DEFAULT_EDITABLE_PAST_MONTHS = 1;

    public function __construct(private AttendanceOverviewService $overview,private AttendanceAccessChecker $access,
        private EntityManager $entityManager,private User $user,private DateTimeUtil $dateTime) {}

    public function getMine(?string $month): array
    {
        $this->access->assertSelf();
        return $this->entityManager->getTransactionManager()->run(function()use($month):array{
            $data=$this->overview->buildOverview($month);$id=(string)$this->user->getId();$days=[];$todayState=null;
            foreach($data['rows'] as $row){foreach($row['cells'] as $cell){
                if($cell['userId']!==$id || !$cell['isWorkingDay']){continue;}
                $locked=$data['frozen'] || !$data['monthEditable'];
                $holiday=$cell['source']==='ApprovedHoliday';$cic=$cell['cicActive'];
                $day=['date'=>$cell['date'],'status'=>$cell['status'],'source'=>$cell['source'],'markedAt'=>$cell['markedAt'],
                    'canMark'=>!$cell['isFuture']&&!$locked&&!$holiday&&!$cic,
                    'canMarkMedical'=>!$cell['isFuture']&&!$locked&&!$holiday,'isLocked'=>$locked,'revision'=>$cell['revision'],'normHours'=>$cell['normHours']];
                $days[]=$day;if($day['date']===$data['today']){$todayState=$day;}
            }}
            if(substr($data['today'],0,7)!==$data['month']){$todayState=$this->getMine(substr($data['today'],0,7));$todayState=['status'=>$todayState['todayStatus'],'source'=>$todayState['todaySource'],'canMark'=>$todayState['todayCanMark'],'canMarkMedical'=>$todayState['todayCanMarkMedical'],'revision'=>$todayState['todayRevision']];}
            $own=array_values(array_filter($data['users'],fn(array $row):bool=>$row['id']===$id));
            return ['normConfigured'=>$own[0]['normConfigured']??false,'month'=>$data['month'],'today'=>$data['today'],'currentMonth'=>$data['currentMonth'],'availableFromMonth'=>$data['availableFromMonth'],
                'editablePastMonths'=>$data['editablePastMonths'],'editableFromMonth'=>$data['editableFromMonth'],'monthLocked'=>!$data['monthEditable'],'frozen'=>$data['frozen'],
                'todayStatus'=>$todayState['status']??null,'todaySource'=>$todayState['source']??null,'todayCanMark'=>$todayState['canMark']??false,
                'todayCanMarkMedical'=>$todayState['canMarkMedical']??false,'todayRevision'=>$todayState['revision']??0,'holidayIntegrationAvailable'=>true,'days'=>$days];
        });
    }

    public function mark(string $date,string $status,?int $revision=null): array
    { return $this->overview->markOwn($date,$status,$revision); }
}
