"""Historical targeted adaptations of the imported Attendance services; emits apply_patch."""
from pathlib import Path
import re
root=Path(__file__).resolve().parents[1];base=root/'files/custom/Espo/Modules/EmployeeManagement/Tools/Attendance'
patch=[]
def save(path,text):patch.append('*** Add File: '+str(path)+'\n'+'\n'.join('+'+line for line in text.rstrip().splitlines()))
def replace_method(text,name,body):
    m=re.search(r'    (?:public|private) function '+name+r'\(',text);start=text.index('{',m.end());depth=1;i=start+1
    while depth:
        if text[i]=='{':depth+=1
        if text[i]=='}':depth-=1
        i+=1
    return text[:m.start()]+body.rstrip()+text[i:]
p=base/'AttendanceOverviewService.php';text=p.read_text()
text=text.replace('private AttendanceAccessChecker $accessChecker,','private AttendanceAccessChecker $accessChecker,\n        private AttendanceMonthService $months,\n        private User $user,')
text=text.replace('public function getOverview(?string $month): array\n    {\n        $this->accessChecker->assertManager();', '''public function getOverview(?string $month): array
    {
        $this->accessChecker->assertOverview();
        return $this->entityManager->getTransactionManager()->run(fn (): array => $this->buildOverview($month));
    }

    /** Internal aggregate for completion under the month lock; never returned without authorization. */
    public function buildOverview(?string $month): array
    {''')
text=text.replace("$monthStart = $month . '-01';", "$monthState = $this->months->get($month);\n        $monthStart = $month . '-01';",1)
text=text.replace("$userIds[] = $userId;", "if ($monthState->get('frozen') && $this->months->norm($monthState,$userId) === null) { continue; }\n            $userIds[] = $userId;",1)
text=text.replace("$recordMap = $this->getRecordMap", '''if ($monthState->get('frozen')) { $scheduleMap = json_decode(json_encode($monthState->get('policies') ?? (object)[]),true); }
        $normMap=[];
        foreach ($userIds as $id) {
            $normMap[$id]=$this->months->norm($monthState,$id);
            $userRows[$id]['schedule']=$scheduleMap[$id] ?? ['remote'=>false,'cic'=>false];
            $userRows[$id]['normHours']=array_sum($normMap[$id]['days']??[]);
        }
        $recordMap = $this->getRecordMap''',1)
text=text.replace('''$holidayMap = [];

        foreach ($userIds as $userId) {
            $holidayMap[$userId] = $this->approvedHolidayProvider
                ->getDates($userId, $monthStart, $monthEnd);
        }''', '''$holidayMap = $this->approvedHolidayProvider->getDatesForUsers($userIds,$monthStart,$monthEnd);''')
text=text.replace('$dates = $this->buildWorkingDates($monthStart, $monthEnd, $nonWorkingDates);', '''$dateMap=[];foreach($normMap as $norm){foreach(array_keys($norm['days']??[]) as $date){$dateMap[$date]=true;}}
        $dates=array_keys($dateMap);sort($dates);''')
text=text.replace("$userRow['schedule'] === null", "$this->months->norm($monthState,$userRow['id']) === null").replace('static fn (array $userRow)', 'fn (array $userRow)')
text=text.replace('$record = $recordMap[$userId][$date] ?? null;', '''$record = $recordMap[$userId][$date] ?? null;
                $working=isset($normMap[$userId]['days'][$date]);
                $dailyRanges=$normMap[$userId]['ranges'][$date]??[];''')
text=text.replace('$isHoliday = isset($holidayMap[$userId][$date]);', '$isHoliday = $working && isset($holidayMap[$userId][$date]);')
text=text.replace('$isCic = $this->isCicDate($scheduleMap[$userId] ?? null, $date);', "$isCic = $working && $this->isCicDate($scheduleMap[$userId] ?? null, $date) && $record?->get('status') !== self::STATUS_MEDICAL_LEAVE;")
text=text.replace('$status = $isHoliday', '$status = !$working ? null : ($isHoliday').replace(": ($isCic ? self::STATUS_CHILD_CARE_LEAVE : $record?->get('status'));", ": ($isCic ? self::STATUS_CHILD_CARE_LEAVE : $record?->get('status')));")
text=text.replace('$source = $isHoliday', '$source = !$working ? null : ($isHoliday').replace(": ($isCic ? self::SOURCE_CIC_PERIOD : $record?->get('source'));", ": ($isCic ? self::SOURCE_CIC_PERIOD : $record?->get('source')));")
text=text.replace('$isMissing = !$isFuture && !$status;', '$isMissing = $working && !$isFuture && !$status;')
text=text.replace('if ($isFuture) {', 'if (!$working) {\n                    // Non-normative cells do not affect completeness or exports.\n                } elseif ($isFuture) {',1)
text=text.replace("'userId' => $userId,\n                    'date'", "'userId' => $userId,\n                    'date'") # DTO userId stays compatible with original UI.
text=text.replace("'assignedUserId' => $userId,\n                    'date' => $date,\n                    'status' => $status,\n                    'source' => $source,", "'userId' => $userId,\n                    'date' => $date,\n                    'status' => $status,\n                    'source' => $source,\n                    'isWorkingDay' => $working,\n                    'normHours' => $normMap[$userId]['days'][$date] ?? 0,\n                    'schedule' => ['startTime'=>implode(' / ',array_column($dailyRanges,0)), 'endTime'=>implode(' / ',array_column($dailyRanges,1)), 'remote'=>(bool)($scheduleMap[$userId]['remote']??false)],\n                    'canManage' => $working && !$isFuture && !$isHoliday && $this->accessChecker->isManager(),")
text=text.replace('$missingCount === 0 && $scheduleMissingCount === 0;', '$missingCount === 0 && $scheduleMissingCount === 0 && $futureCount === 0;')
text=text.replace("'isManager' => true,", "'isManager' => $this->accessChecker->isManager(),\n            'frozen' => (bool)$monthState->get('frozen'),\n            'monthRevision' => (int)$monthState->get('revision'),")
text=text.replace("'monthEditable' => $month >= $editableFromMonth,", "'monthEditable' => $month >= $editableFromMonth && !$monthState->get('frozen'),")
# Keep original public signature for manager marks; common command is the only write owner.
text=replace_method(text,'markForUser','''    public function markForUser(string $userId, string $date, string $status, string $reason = '', ?int $expectedRevision = null): array
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
        if(!in_array($status,[self::STATUS_AT_WORK,self::STATUS_BUSINESS_TRIP,self::STATUS_MEDICAL_LEAVE],true)){throw new BadRequest('Unsupported attendance status.');}
        if(!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D',$date)){throw new BadRequest('Invalid attendance date.');}
        $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if(!$parsed || $parsed->format('Y-m-d')!==$date){throw new BadRequest('Attendance date must use YYYY-MM-DD.');}
        $today=$this->dateTime->getToday()->toString();$month=substr($date,0,7);
        if($date>$today){throw new BadRequest('Attendance cannot be marked for a future date.');}
        if($month<$this->getAvailableFromMonth($today)){throw new BadRequest('Attendance date predates the available archive.');}
        if($manager && trim($reason)===''){throw new BadRequest('A reason is required for managerial attendance corrections.');}
        return $this->entityManager->getTransactionManager()->run(function()use($userId,$date,$status,$manager,$reason,$expectedRevision,$month,$today):array{
            $employee=$this->entityManager->getRDBRepositoryByClass(User::class)->where(['id'=>$userId,'type'=>User::TYPE_REGULAR,'isActive'=>true])->forUpdate()->findOne();
            if(!$employee){throw new NotFound('Active regular employee not found.');}
            $state=$this->months->get($month);
            if(!$manager && ($state->get('frozen') || $month<$this->getEditableFromMonth($today))){throw new Conflict('Attendance for this month is locked.');}
            $norm=$this->months->norm($state,$userId);
            if(!$norm || !isset($norm['days'][$date])){throw new BadRequest('An assigned WTC working day is required; no fallback schedule.');}
            if($this->approvedHolidayProvider->getDates($userId,$date,$date)!==[]){throw new Conflict('An active approved holiday controls attendance for this date.');}
            $policies=$state->get('frozen')?json_decode(json_encode($state->get('policies')??(object)[]),true):$this->getScheduleMap([$userId]);
            if($this->isCicDate($policies[$userId]??null,$date) && $status!==self::STATUS_MEDICAL_LEAVE){throw new Conflict('CIC controls attendance; only MedicalLeave may be marked.');}
            $record=$this->entityManager->getRDBRepository(self::ENTITY_TYPE)->where(['assignedUserId'=>$userId,'date'=>$date])->forUpdate()->findOne();
            $revision=(int)($record?->get('revision')??0);
            if($expectedRevision!==null && $expectedRevision!==$revision){throw new Conflict('Stale attendance revision. Reload the month.');}
            if(!$record){$record=$this->entityManager->getNewEntity(self::ENTITY_TYPE);}
            $record->set(['name'=>sprintf('%s - %s',$employee->get('name'),$date),'date'=>$date,'status'=>$status,
                'source'=>$manager?self::SOURCE_MANAGER:'Self','assignedUserId'=>$userId,'markedAt'=>DateTimeUtil::getSystemNowString(),
                'correctionReason'=>$manager?trim($reason):null,'revision'=>$revision+1]);
            $this->entityManager->saveEntity($record);
            $state->set('revision',(int)$state->get('revision')+1);$this->entityManager->saveEntity($state);
            $this->freezeIfComplete($month);
            return ['userId'=>$userId,'date'=>$date,'status'=>$status,'source'=>$record->get('source'),'revision'=>$revision+1];
        });
    }

    private function freezeIfComplete(string $month): void
    {
        $state=$this->months->get($month);
        if($state->get('frozen')){return;}
        $overview=$this->buildOverview($month);
        if(!$overview['registerComplete']){return;}
        $ids=array_column($overview['users'],'id');
        $state->set(['frozen'=>true,'frozenAt'=>DateTimeUtil::getSystemNowString(),'frozenById'=>$this->user->getId(),'policies'=>(object)$this->getScheduleMap($ids)]);
        $this->entityManager->saveEntity($state);
    }''')
text=replace_method(text,'saveSchedule','''    public function saveSchedule(string $userId,bool $remote,bool $cic,?string $cicStartDate,?string $cicEndDate): array
    {
        $this->accessChecker->assertScheduleEditor();
        $cicStartDate=$this->normalizeOptionalDate($cicStartDate);$cicEndDate=$this->normalizeOptionalDate($cicEndDate);
        if($cic && ($cicStartDate===null || $cicEndDate===null)){throw new BadRequest('CIC start and end dates are required when CIC is enabled.');}
        if($cic && $cicStartDate>$cicEndDate){throw new BadRequest('The CIC end date must be on or after the start date.');}
        if(!$cic){$cicStartDate=null;$cicEndDate=null;}
        return $this->entityManager->getTransactionManager()->run(function()use($userId,$remote,$cic,$cicStartDate,$cicEndDate):array{
            $employee=$this->entityManager->getRDBRepositoryByClass(User::class)->where(['id'=>$userId,'type'=>User::TYPE_REGULAR,'isActive'=>true])->forUpdate()->findOne();
            if(!$employee){throw new NotFound('Active regular employee not found.');}
            $schedule=$this->entityManager->getRDBRepository(self::SCHEDULE_ENTITY_TYPE)->where(['assignedUserId'=>$userId])->order('effectiveFrom','DESC')->forUpdate()->findOne();
            if(!$schedule){$schedule=$this->entityManager->getNewEntity(self::SCHEDULE_ENTITY_TYPE);}
            $schedule->set(['name'=>$employee->get('name').' - Attendance metadata','assignedUserId'=>$userId,
                'remote'=>$remote,'cic'=>$cic,'cicStartDate'=>$cicStartDate,'cicEndDate'=>$cicEndDate,
                'effectiveFrom'=>$schedule->get('effectiveFrom')?:$this->dateTime->getToday()->toString()]);
            $this->entityManager->saveEntity($schedule);
            return ['userId'=>$userId,'remote'=>$remote,'cic'=>$cic,'cicStartDate'=>$cicStartDate,'cicEndDate'=>$cicEndDate];
        });
    }''')
# Keep DTO and original notification generation, but separate authorization from overview read.
text=text.replace("$overview = $this->getOverview($month);\n        $sentUsers", "$this->accessChecker->assertManager();\n        $overview = $this->getOverview($month);\n        $sentUsers")
text=text.replace('[Deschide condica de prezenta](#Attendance/index/month=%s)', '[Deschide condica de prezenta](#EmployeeAttendance/index/month=%s)')
text=text.replace("'startTime' => (string) $schedule->get('startTime'),\n                'endTime' => (string) $schedule->get('endTime'),\n", '')
# Authoritative read during mark/freeze, serialized behind month data, not stale consistent-read snapshots.
text=text.replace("'date<=' => $dateEnd,\n            ])\n            ->find();", "'date<=' => $dateEnd,\n            ])\n            ->forUpdate()\n            ->find();")
text=text.replace("'isWorkingDay' => $working,", "'revision' => (int)($record?->get('revision')??0),\n                    'isWorkingDay' => $working,")
save(p,text)
# Original personal view DTO is preserved; the same aggregate drives all consumers and writes.
p=base/'AttendanceService.php';old=p.read_text()
start=old.index('    public function __construct(');end=old.index('    /** @return array<string, Entity> */')
body='''    public function __construct(private AttendanceOverviewService $overview,private AttendanceAccessChecker $access,
        private EntityManager $entityManager,private User $user,private DateTimeUtil $dateTime) {}

    public function getMine(?string $month): array
    {
        $this->access->assertSelf();
        return $this->entityManager->getTransactionManager()->run(function()use($month):array{
            $data=$this->overview->buildOverview($month);$id=(string)$this->user->getId();$days=[];$todayState=null;
            foreach($data['rows'] as $row){foreach($row['cells'] as $cell){
                if($cell['userId']!==$id || !$cell['isWorkingDay']){continue;}
                $locked=$data['frozen'] || !$data['monthEditable'];
                $holiday=$cell['source']==='ApprovedHoliday';$cic=$cell['source']==='CicPeriod';
                $day=['date'=>$cell['date'],'status'=>$cell['status'],'source'=>$cell['source'],'markedAt'=>null,
                    'canMark'=>!$cell['isFuture']&&!$locked&&!$holiday&&!$cic,
                    'canMarkMedical'=>!$cell['isFuture']&&!$locked&&!$holiday,'isLocked'=>$locked,'revision'=>$cell['revision'],'normHours'=>$cell['normHours']];
                $days[]=$day;if($day['date']===$data['today']){$todayState=$day;}
            }}
            if(substr($data['today'],0,7)!==$data['month']){$todayState=$this->getMine(substr($data['today'],0,7));$todayState=['status'=>$todayState['todayStatus'],'source'=>$todayState['todaySource'],'canMark'=>$todayState['todayCanMark'],'canMarkMedical'=>$todayState['todayCanMarkMedical']];}
            return ['month'=>$data['month'],'today'=>$data['today'],'currentMonth'=>$data['currentMonth'],'availableFromMonth'=>$data['availableFromMonth'],
                'editablePastMonths'=>0,'editableFromMonth'=>$data['availableFromMonth'],'monthLocked'=>!$data['monthEditable'],'frozen'=>$data['frozen'],
                'todayStatus'=>$todayState['status']??null,'todaySource'=>$todayState['source']??null,'todayCanMark'=>$todayState['canMark']??false,
                'todayCanMarkMedical'=>$todayState['canMarkMedical']??false,'holidayIntegrationAvailable'=>true,'days'=>$days];
        });
    }

    public function mark(string $date,string $status,?int $revision=null): array
    { return $this->overview->markOwn($date,$status,$revision); }
}
'''
# Obsolete fixed-weekday helpers are intentionally not installed, originals stay in pending.
save(p,old[:start]+body)
print('*** Begin Patch\n'+'\n'.join(patch)+'\n*** End Patch')
