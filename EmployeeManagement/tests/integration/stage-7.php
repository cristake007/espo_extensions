<?php
declare(strict_types=1);
// Real native worker tests only in the explicitly isolated laboratory. No fault switches in the package.
if(getenv('EMPLOYEE_TEST_LAB')!=='employee-feasibility-20261020'){throw new RuntimeException('Isolated lab only.');}
chdir('/var/www/html');require 'bootstrap.php';$c=(new Espo\Core\Application())->getContainer();$em=$c->get('entityManager');$f=$c->get('injectableFactory');
if($c->get('config')->get('version')!=='10.0.1'||$c->get('config')->get('siteUrl')!=='http://127.0.0.1:18089'){throw new RuntimeException('Wrong environment.');}
if($em->getRDBRepository('Extension')->where(['name'=>'Employee Management','isInstalled'=>true])->findOne()?->get('version')!=='0.7.0'){throw new RuntimeException('Wrong package.');}
$password=getenv('EMPLOYEE_TEST_PASSWORD')?:throw new RuntimeException('Password required.');$adminPassword=getenv('EMPLOYEE_TEST_ADMIN_PASSWORD')?:throw new RuntimeException('Admin password required.');
$admin=$em->getRDBRepository('User')->where(['userName'=>'admin'])->findOne();$c->getByClass(Espo\Core\ApplicationUser::class)->setUser($admin);
$path='/feasibility/employee-stage7-state.json';$phase=$argv[1]??'setup';$s=is_file($path)?json_decode(file_get_contents($path),true):[];$users=[];$results=[];$failures=0;$tag=uniqid('s7');
foreach($s['users']??[] as $kind=>$v){$users[$kind]=$em->getEntityById('User',$v['id']);}
function check(string $test,bool $ok,mixed $observed=null):void{global $results,$failures;$r=['test'=>$test,'status'=>$ok?'PASS':'FAIL','observed'=>$observed];$results[]=$r;if(!$ok){$failures++;}echo json_encode($r,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;}
function fresh(string $type,array $values):Espo\ORM\Entity{global $em;$e=$em->getNewEntity($type);$e->set($values);$em->saveEntity($e);return $e;}
function state(string $id):array{global $em;return json_decode(json_encode($em->getRDBRepository('EmployeeHolidayRequest')->where(['id'=>$id])->findOne()->getValueMap()),true);}
function http(string $kind,string $method,string $path,?array $data=null,bool $download=false):array{global $users,$password,$adminPassword;$name=$kind==='admin'?'admin':$users[$kind]->get('userName');$pw=$kind==='admin'?$adminPassword:$password;$h=curl_init('http://127.0.0.1/'.($download?'':'api/v1/').$path);$reason='';curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Espo-Authorization: '.base64_encode($name.':'.$pw)],CURLOPT_TIMEOUT=>90,CURLOPT_HEADERFUNCTION=>function($h,$line)use(&$reason){if(str_starts_with(strtolower($line),'x-status-reason:')){$reason=trim(substr($line,16));}return strlen($line);}]);if($data!==null){curl_setopt($h,CURLOPT_POSTFIELDS,json_encode($data));}$raw=curl_exec($h);if($raw===false){throw new RuntimeException(curl_error($h));}$code=curl_getinfo($h,CURLINFO_HTTP_CODE);curl_close($h);return ['status'=>$code,'body'=>json_decode($raw,true),'raw'=>$raw,'reason'=>$reason];}
function api(string $kind,string $op,?array $body=null):array{return http($kind,$body===null?'GET':'POST','EmployeeManagement/holiday/'.$op,$body);}
function command(string $id,string $key,array $more=[]):array{return ['idempotencyKey'=>$key,'revision'=>(int)state($id)['accountingRevision']]+$more;}
function book(string $kind,string $start,string $end):string{$r=api($kind,'create',['dateStart'=>$start,'dateEnd'=>$end,'description'=>'PRIVATE real Stage7 approval','idempotencyKey'=>uniqid('s7-book-')]);check('book-'.$kind.'-'.$start,$r['status']===200,$r['status']);if($r['status']!==200){throw new RuntimeException('Booking failed '.$r['reason']);}return $r['body']['id'];}
function accounting(string $kind):array{global $em,$users;$p=$em->getRDBRepository('EmployeeHolidayProfile')->where(['assignedUserId'=>$users[$kind]->getId()])->findOne();return ['balance'=>(float)$p->get('balance'),'ledger'=>$em->getRDBRepository('EmployeeHolidayLedger')->where(['profileId'=>$p->getId()])->count()];}
function waitState(string $id,string $wanted,int $seconds=45):array{$until=microtime(true)+$seconds;do{$r=state($id);if(($r['approvalState']??null)===$wanted){return $r;}usleep(150000);}while(microtime(true)<$until);return $r;}
function job(string $id):array{global $em;return (array)$em->getEntityById('Job',state($id)['approvalJobId'])->getValueMap();}
function waitJob(string $id,string $wanted,int $seconds=45):array{$until=microtime(true)+$seconds;do{$r=job($id);if($r['status']===$wanted){return $r;}usleep(200000);}while(microtime(true)<$until);return $r;}
function docs(string $id):array{global $em;$r=[];foreach($em->getRDBRepository('Attachment')->where(['parentType'=>'EmployeeHolidayRequest','parentId'=>$id])->order('name')->find() as $a){$r[]=(array)$a->getValueMap();}return $r;}
function trigger(string $id,string $mode):void{global $em;$em->getPDO()->exec('DROP TRIGGER IF EXISTS employee_stage6_fault');$condition='NEW.parent_id='.$em->getPDO()->quote($id)." AND NEW.name LIKE '%februarie%'";$effect=$mode==='fail'?"SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stage7 isolated second-document storage boundary failure'":"DO SLEEP(30)";$em->getPDO()->exec('CREATE TRIGGER employee_stage6_fault BEFORE INSERT ON attachment FOR EACH ROW BEGIN IF '.$condition.' THEN '.$effect.'; END IF; END');}
function parallelApprove(string $id,array $payload):array{global $users,$password;$multi=curl_multi_init();$handles=[];for($i=0;$i<2;$i++){$h=curl_init('http://127.0.0.1/api/v1/EmployeeManagement/holiday/'.$id.'/approve');curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Espo-Authorization: '.base64_encode($users['manager']->get('userName').':'.$password)],CURLOPT_TIMEOUT=>90]);curl_multi_add_handle($multi,$h);$handles[]=$h;}do{$status=curl_multi_exec($multi,$running);if($running){curl_multi_select($multi,1);}}while($running&&$status===CURLM_OK);$out=[];foreach($handles as $h){$out[]=['status'=>curl_getinfo($h,CURLINFO_HTTP_CODE),'body'=>json_decode(curl_multi_getcontent($h),true)];curl_multi_remove_handle($multi,$h);curl_close($h);}curl_multi_close($multi);return $out;}


function calendar(string $kind,string $scope='EmployeeAbsence',?string $target=null):array {
    $q=['from'=>'2026-11-01 00:00:00','to'=>'2027-01-01 00:00:00','scopeList'=>$scope];
    if($target!==null){$q['userId']=$target;}
    return http($kind,'GET','Activities?'.http_build_query($q));
}
function absence(string $id):?Espo\ORM\Entity{global $em;return $em->getRDBRepository('EmployeeAbsence')->where(['sourceId'=>$id])->findOne();}
function nativeAnnual(string $group):string{global $f,$em;$f->create(Espo\Core\Job\JobSchedulerFactory::class)->create()->setClassName(Espo\Modules\EmployeeManagement\Jobs\ProcessEmployeeHolidayResets::class)->setGroup($group)->schedule();$j=$em->getRDBRepository('Job')->where(['group'=>$group])->order('number','DESC')->findOne();$j->set('attempts',0);$em->saveEntity($j);return $j->getId();}
function waitNative(string $id,int $seconds=50):array{global $em;$until=microtime(true)+$seconds;do{$j=$em->getEntityById('Job',$id);if($j&&in_array($j->get('status'),['Success','Failed'],true)){return (array)$j->getValueMap();}usleep(200000);}while(microtime(true)<$until);return $j?(array)$j->getValueMap():['status'=>'Missing'];}
function settings(bool $enabled):array{global $s;$v=$s['settings'];unset($v['isAdmin'],$v['employeeHolidayApproversNames']);$v['employeeHolidayAnnualEntitlementDays']=21;$v['employeeHolidayResetDate']='2027-01-01';$v['employeeHolidayAutomaticAnnualGrantEnabled']=$enabled;$v['employeeHolidayCarryOverLimitDays']=30;$v['employeeHolidayNegativeBalanceLimitDays']=-21;$v['employeeHolidayApproversIds']=[$s['users']['manager']['id']];return $v;}

if($phase==='setup'){
    $hash=$f->create(Espo\Core\Utils\PasswordHash::class)->hash($password);
    $cal=fresh('WorkingTimeCalendar',['name'=>'Stage7 WTC '.$tag,'weekdays'=>[1,2,3,4,5],'timeRanges'=>[['08:00','14:00']],'timeZone'=>'Europe/Bucharest']);
    foreach(['manager','employee','colleague','denied','fls'] as $kind){$m=$kind==='manager';$data=['Calendar'=>true,'EmployeeAbsence'=>(object)['read'=>$kind==='denied'?'no':'all','create'=>'yes','edit'=>'all','delete'=>'all'],'EmployeeManagement'=>true,'EmployeeAdministration'=>$m,'EmployeeHolidaySettings'=>$m,'EmployeeHolidayProfileManagement'=>$m,'EmployeeHolidayBalanceCorrection'=>$m,'EmployeeHolidayBooking'=>true,'EmployeeHolidayRequestManagement'=>$m,'EmployeeHolidayApproval'=>$m,'EmployeeAttendance'=>true,'EmployeeAttendanceOverview'=>$m,'EmployeeAttendanceManagement'=>$m,'EmployeeAttendanceSettings'=>$m];foreach(['EmployeeHolidayRequest','EmployeeHolidayProfile','EmployeeHolidayLedger','EmployeeAttendanceRecord','EmployeeAttendanceWorkSchedule'] as $type){$data[$type]=(object)['read'=>$m?'all':'own','create'=>'yes','edit'=>'all','delete'=>'all'];}
        $role=fresh('Role',['name'=>'Stage7 '.$tag.' '.$kind,'data'=>(object)$data,'userCalendarPermission'=>'all','exportPermission'=>'yes','fieldData'=>$kind==='fls'?(object)['EmployeeAbsence'=>(object)['dateEnd'=>(object)['read'=>'no','edit'=>'no']]]:(object)[]]);
        $users[$kind]=fresh('User',['userName'=>'em_'.$tag.'_'.$kind,'firstName'=>'Calendar '.$tag,'lastName'=>'Employee '.$kind,'type'=>'regular','isActive'=>true,'password'=>$hash,'rolesIds'=>[$role->getId()],'workingTimeCalendarId'=>$cal->getId()]);
    }
    $labConfig=array_intersect_key($s,array_flip(['calendarListBefore','zileViewOverride']));
    $s=['users'=>array_map(fn($u)=>['id'=>$u->getId(),'name'=>$u->get('userName'),'displayName'=>$u->get('name')],$users),'calendarId'=>$cal->getId()]+$labConfig;
    $s['settings']=http('manager','GET','EmployeeManagement/administration/holiday')['body'];
    $writer=$f->create(Espo\Core\Utils\Config\ConfigWriter::class);$writer->setMultiple(['employeeHolidayApproversIds'=>[$users['manager']->getId()],'employeeHolidayAutomaticAnnualGrantEnabled'=>false]);$writer->save();
    foreach($users as $kind=>$u){$r=http('manager','POST','EmployeeManagement/administration/profiles/bulkInitialize',['items'=>[['userId'=>$u->getId(),'annualEntitlement'=>21,'openingBalance'=>40,'nextResetDate'=>'2027-01-01','calendarColor'=>'#4F8A8B','idempotencyKey'=>'s7-init:'.$u->getId()]]]);check('profile-'.$kind,$r['status']===200,$r['status']);}
    $scheduled=$em->getRDBRepository('ScheduledJob')->where(['job'=>'ProcessEmployeeHolidayResets'])->findOne();$s['scheduledId']=$scheduled?->getId();$s['scheduling']=$scheduled?->get('scheduling');
    check('unique-native-scheduled-registration',$scheduled?->get('status')==='Active'&&$s['scheduling']==='5 0 * * *'&&$em->getRDBRepository('ScheduledJob')->where(['job'=>'ProcessEmployeeHolidayResets'])->count()===1);
    check('no-automatic-grants-enabled-by-install',$c->get('config')->get('employeeHolidayAutomaticAnnualGrantEnabled',false)===false);
    $until=microtime(true)+10;do{$visible=http('manager','GET','EmployeeManagement/administration/holiday');if(($visible['body']['employeeHolidayApproversIds']??[])===[$users['manager']->getId()]){break;}usleep(200000);}while(microtime(true)<$until);
    check('configuration-visible-to-http-before-decision',($visible['body']['employeeHolidayApproversIds']??[])===[$users['manager']->getId()]);
    $s['approved']=book('employee','2026-11-02','2026-11-04');$s['reserved']=accounting('employee');
    check('Pending-no-public-projection',absence($s['approved'])===null);
    $r=api('manager',$s['approved'].'/approve',command($s['approved'],'s7-approval:'.$s['approved']));check('native-approval-processing',$r['status']===200&&$r['body']['status']==='ApprovalProcessing',$r['status']);check('Processing-no-public-projection',absence($s['approved'])===null);
    $s['pending']=book('colleague','2026-12-07','2026-12-08');
    $r=calendar('colleague');check('native-Calendar-endpoint-before-worker',$r['status']===200,$r);
    $r=calendar('colleague','EmployeeHolidayRequest');check('private-own-Pending-calendar',$r['status']===200&&in_array($s['pending'],array_column($r['body']??[],'id')),$r);
    $r=calendar('employee','EmployeeHolidayRequest');check('private-Pending-not-visible-to-colleague',$r['status']===200&&!in_array($s['pending'],array_column($r['body']??[],'id')),$r);
    $s['disabledAccounting']=accounting('colleague');$s['disabledJob']=nativeAnnual('s7-disabled-'.$tag);
    check('source-registration-additive',in_array('ZileLibere',$c->get('config')->get('calendarEntityList'))&&in_array('EmployeeAbsence',$c->get('config')->get('calendarEntityList')));
}
if($phase==='calendar'){
    $id=$s['approved'];$r=waitState($id,'Completed');check('real-worker-approved-with-document',$r['status']==='Approved'&&count(docs($id))===1,$r['status']);
    check('approval-no-double-reservation',accounting('employee')==$s['reserved'],accounting('employee'));$a=absence($id);$s['absenceId']=$a?->getId();check('public-absence-created-in-finalization',$a!==null&&$a->get('name')===$users['employee']->get('name')&&$a->get('dateStart')==='2026-11-02'&&$a->get('dateEnd')==='2026-11-04');
    $r=calendar('colleague');$events=array_values(array_filter($r['body']??[],fn($e)=>($e['id']??null)===$s['absenceId']));check('colleague-sees-minimal-common-event',$r['status']===200&&count($events)===1,$events);
    $event=$events[0]??[];check('Calendar-no-private-values',!array_intersect(array_keys($event),['sourceId','requestId','profileId','description','reason','approvalDocumentsIds','balance'])&&($event['status']??null)===null&&($event['parentId']??null)===null&&!str_contains(json_encode($event),'PRIVATE'));
    $r=calendar('manager','EmployeeHolidayRequest');check('Approved-not-rendered-twice-as-private-request',!in_array($id,array_column($r['body']??[],'id')));
    $r=calendar('colleague','EmployeeAbsence',$users['employee']->getId());check('common-event-on-other-user-calendar',$r['status']===200&&in_array($s['absenceId'],array_column($r['body']??[],'id')),$r['status']);
    $r=http('colleague','GET','EmployeeAbsence/'.$s['absenceId']);check('native-record-by-id-minimal',$r['status']===200&&!isset($r['body']['sourceId'],$r['body']['createdAt'])&&!isset($r['body']['description'],$r['body']['approvalDocumentsIds']),$r);
    foreach(['denied','admin'] as $kind){$r=calendar($kind);check('common-feed-business-ACL-'.$kind,$r['status']===200&&!in_array($s['absenceId'],array_column($r['body']??[],'id')),$r['status']);}
    $r=http('denied','GET','EmployeeAbsence/'.$s['absenceId']);check('native-record-no-role-denied',$r['status']===403,$r['status']);
    $r=calendar('fls');check('Calendar-respects-native-date-FLS',$r['status']===200&&!in_array($s['absenceId'],array_column($r['body']??[],'id')),$r['status']);
    $r=http('fls','GET','EmployeeAbsence/'.$s['absenceId']);check('record-date-FLS-not-reexposed',$r['status']===200&&!isset($r['body']['dateEnd']),$r);
    foreach(['manager','employee','colleague','admin'] as $kind){foreach(['POST','PUT','DELETE'] as $method){$r=http($kind,$method,'EmployeeAbsence'.($method==='POST'?'':'/'.$s['absenceId']),$method==='DELETE'?null:['name'=>'FORGED','dateStart'=>'2026-11-01','dateEnd'=>'2026-11-10']);check('public-CRUD-closed-'.$kind.'-'.$method,in_array($r['status'],[400,403],true),$r['status']);}}
    $r=http('colleague','POST','EmployeeAbsence/'.$s['absenceId'].'/assignedUser',['id'=>$users['colleague']->getId()]);check('public-link-write-closed',in_array($r['status'],[400,403,404],true),$r['status']);
    $r=http('colleague','GET','EmployeeHolidayRequest/'.$id);check('projection-does-not-grant-private-source',$r['status']===403,$r['status']);
    $doc=docs($id)[0];$s['documentId']=$doc['id'];$storage=$f->create(Espo\Core\FileStorage\Manager::class);$s['documentHash']=hash('sha256',$storage->getContents($em->getEntityById('Attachment',$doc['id'])));
    foreach(['colleague','denied'] as $kind){$r=http($kind,'GET','?entryPoint=download&id='.$doc['id'],null,true);check('private-document-by-id-'.$kind,$r['status']===403,$r['status']);}
    $r=http('employee','GET','?entryPoint=download&id='.$doc['id'],null,true);check('owner-native-document-download',$r['status']===200&&str_starts_with($r['raw'],'PK'));
    $r=api('employee',$id.'/requestCancellation',command($id,'s7-cancel-request:'.$id,['reason'=>'PRIVATE cancellation reason']));check('CancellationPending-keeps-same-projection',$r['status']===200&&$r['body']['status']==='CancellationPending'&&absence($id)?->getId()===$s['absenceId'],$r['status']);
    $r=calendar('colleague');check('CancellationPending-still-public',in_array($s['absenceId'],array_column($r['body']??[],'id'))&&!str_contains($r['raw'],'PRIVATE cancellation'));
    check('disabled-real-native-job-no-mutation',waitNative($s['disabledJob'])['status']==='Success'&&accounting('colleague')==$s['disabledAccounting']);
    foreach(['employee','manager'] as $kind){$r=http($kind,'GET','ScheduledJob/'.$s['scheduledId']);check('no-global-ScheduledJob-read-'.$kind,$r['status']===403,$r['status']);$r=http($kind,'PUT','ScheduledJob/'.$s['scheduledId'],['scheduling'=>'* * * * *']);check('no-global-ScheduledJob-edit-'.$kind,$r['status']===403,$r['status']);}
}
if($phase==='annual-setup'){
    $s['previousActive']=$s['previousActive']??[];foreach($em->getRDBRepository('User')->where(['type'=>'regular','isActive'=>true])->find() as $u){if(!in_array($u->getId(),array_column($s['users'],'id'),true)){$s['previousActive'][]=$u->getId();}}
    $s['previousActive']=array_values(array_unique($s['previousActive']));file_put_contents($path,json_encode($s,JSON_PRETTY_PRINT));
    foreach($s['previousActive'] as $id){$u=$em->getEntityById('User',$id);if($u&&$u->isActive()){$u->set('isActive',false);$em->saveEntity($u);}}
    $r=http('manager','PUT','EmployeeManagement/administration/holiday',settings(true));check('manager-explicit-opt-in-native-settings',$r['status']===200&&$r['body']['employeeHolidayAutomaticAnnualGrantEnabled']===true,$r);
    foreach(['employee','admin'] as $kind){$r=http($kind,'PUT','EmployeeManagement/administration/holiday',settings(true));check('annual-opt-in-ACL-'.$kind,$r['status']===403,$r['status']);}
    $today=$c->get('dateTime')->getToday()->toString();$s['annualDate']=$today;
    $negative=$em->getRDBRepository('EmployeeHolidayProfile')->where(['assignedUserId'=>$users['denied']->getId()])->findOne();$r=http('manager','POST','EmployeeManagement/administration/profiles/correct',['profileId'=>$negative->getId(),'delta'=>-45,'reason'=>'Synthetic negative-balance regression','idempotencyKey'=>'s7-negative:'.$negative->getId()]);check('negative-balance-prepared-through-authorized-correction',$r['status']===200&&$r['body']['balance']==-5,$r['status']);
    $s['futureProfileBefore']=accounting('manager');
    foreach(['employee','colleague','denied'] as $kind){$p=$em->getRDBRepository('EmployeeHolidayProfile')->where(['assignedUserId'=>$users[$kind]->getId()])->findOne();$p->set('nextResetDate',$kind==='colleague'?((int)substr($today,0,4)-1).substr($today,4):$today);$em->saveEntity($p);$s['annualBefore'][$kind]=['id'=>$p->getId(),'balance'=>(float)$p->get('balance'),'ledger'=>$em->getRDBRepository('EmployeeHolidayLedger')->where(['profileId'=>$p->getId()])->count()];}
    $s['adminProfile']=($em->getRDBRepository('EmployeeHolidayProfile')->where(['assignedUserId'=>$admin->getId()])->findOne()??fresh('EmployeeHolidayProfile',['name'=>'Excluded admin fixture','assignedUserId'=>$admin->getId(),'balance'=>11,'annualEntitlement'=>21,'nextResetDate'=>$today,'isInitialized'=>true]))->getId();
    $inactive=fresh('User',['userName'=>'em_s7_inactive_'.$tag,'firstName'=>'Inactive','lastName'=>'Fixture','type'=>'regular','isActive'=>false]);$s['inactiveProfile']=fresh('EmployeeHolidayProfile',['name'=>'Excluded inactive fixture','assignedUserId'=>$inactive->getId(),'balance'=>12,'annualEntitlement'=>21,'nextResetDate'=>$today,'isInitialized'=>true])->getId();
    // Native scheduler, not a direct business-service invocation. Change timing only for this lab probe.
    $s['scheduledLogBefore']=$em->getRDBRepository('ScheduledJobLogRecord')->where(['scheduledJobId'=>$s['scheduledId'],'status'=>'Success'])->count();
    $j=$em->getEntityById('ScheduledJob',$s['scheduledId']);$j->set('scheduling','* * * * *');$em->saveEntity($j);$f->create(Espo\Core\Job\ScheduleProcessor::class)->process();$job=$em->getRDBRepository('Job')->where(['scheduledJobId'=>$s['scheduledId']])->order('number','DESC')->findOne();$s['annualScheduledJob']=$job?->getId();
    check('native-scheduler-enqueued-owned-ScheduledJob',$job!==null&&$job->get('status')==='Pending',$job?->getValueMap());
    $s['annualRaceJobs']=[nativeAnnual('s7-annual-race-a-'.$tag),nativeAnnual('s7-annual-race-b-'.$tag)];
}
if($phase==='annual-complete'){
    foreach($s['annualRaceJobs'] as $id){$j=waitNative($id);check('real-annual-native-worker-'.$id,$j['status']==='Success'&&($j['failedAttempts']??0)===0,['status'=>$j['status'],'startedAt'=>$j['startedAt']??null,'executedAt'=>$j['executedAt']??null,'failedAttempts'=>$j['failedAttempts']??null]);}
    foreach(['employee'=>1,'colleague'=>2,'denied'=>1] as $kind=>$years){$before=$s['annualBefore'][$kind];$p=$em->getEntityById('EmployeeHolidayProfile',$before['id']);$grants=$em->getRDBRepository('EmployeeHolidayLedger')->where(['profileId'=>$p->getId(),'type'=>'annualGrant'])->find();check('annual-original-additive-formula-'.$kind,(float)$p->get('balance')===(float)($before['balance']+21*$years),[$before['balance'],$p->get('balance')]);check('one-ledger-per-due-year-'.$kind,count($grants)===$years&&$em->getRDBRepository('EmployeeHolidayLedger')->where(['profileId'=>$p->getId()])->count()===$before['ledger']+$years);check('reset-date-advanced-original-next-year-'.$kind,$p->get('nextResetDate')===((int)substr($s['annualDate'],0,4)+1).substr($s['annualDate'],4),$p->get('nextResetDate'));foreach($grants as $g){check('ledger-audit-system-and-key-'.$g->getId(),$g->get('actorId')==='system'&&str_starts_with($g->get('idempotencyKey'),'scheduled-reset:')&&(float)$g->get('delta')===21.0);}}
    check('annual-excludes-admin-and-inactive',(float)$em->getEntityById('EmployeeHolidayProfile',$s['adminProfile'])->get('balance')===11.0&&(float)$em->getEntityById('EmployeeHolidayProfile',$s['inactiveProfile'])->get('balance')===12.0);
    $until=microtime(true)+40;do{$logs=$em->getRDBRepository('ScheduledJobLogRecord')->where(['scheduledJobId'=>$s['scheduledId'],'status'=>'Success'])->count();if($logs>($s['scheduledLogBefore']??0)){break;}usleep(200000);}while(microtime(true)<$until);
    check('native-ScheduledJob-success-log',$logs>($s['scheduledLogBefore']??0),$logs);
    $scheduled=$em->getEntityById('ScheduledJob',$s['scheduledId']);$scheduled->set('scheduling',$s['scheduling']);$em->saveEntity($scheduled);
    $storage=$f->create(Espo\Core\FileStorage\Manager::class);check('annual-grant-does-not-recalculate-approved-document',hash('sha256',$storage->getContents($em->getEntityById('Attachment',$s['documentId'])))===$s['documentHash']);
    foreach(['employee','colleague'] as $kind){$n=$em->getRDBRepository('Notification')->where(['userId'=>$users[$kind]->getId(),'relatedType'=>'EmployeeHolidayProfile'])->find();check('original-balance-warning-notifications-'.$kind,count($n)>0&&!str_contains(json_encode(array_map(fn($e)=>(array)$e->getValueMap(),iterator_to_array($n))),'#Admin/'));}
    $r=http('manager','PUT','EmployeeManagement/administration/holiday',settings(false));check('manager-can-disable-annual-processing',$r['status']===200&&!$r['body']['employeeHolidayAutomaticAnnualGrantEnabled']);
    check('future-reset-profile-not-credited',accounting('manager')==$s['futureProfileBefore']);
}
if($phase==='annual-failure-setup'){
    $r=http('manager','PUT','EmployeeManagement/administration/holiday',settings(true));check('annual-recovery-explicit-opt-in',$r['status']===200,$r['status']);
    $s['annualRecoveryProfiles']=[];for($i=0;$i<2;$i++){$u=fresh('User',['userName'=>'em_s7_recovery_'.$tag.'_'.$i,'firstName'=>'Annual','lastName'=>'Recovery '.$i,'type'=>'regular','isActive'=>true,'rolesIds'=>$users['employee']->get('rolesIds')]);$today=$c->get('dateTime')->getToday()->toString();$due=$i===0?(new DateTimeImmutable($today))->modify('-1 day')->format('Y-m-d'):$today;$r=http('manager','POST','EmployeeManagement/administration/profiles/bulkInitialize',['items'=>[['userId'=>$u->getId(),'annualEntitlement'=>21,'openingBalance'=>5,'nextResetDate'=>$due,'calendarColor'=>'#4F8A8B','idempotencyKey'=>'s7-recovery-init:'.$u->getId()]]]);check('recovery-profile-'.$i,$r['status']===200,$r['status']);$s['annualRecoveryProfiles'][]=$r['body']['list'][0]['profileId'];}
    $em->getPDO()->exec('DROP TRIGGER IF EXISTS employee_stage7_annual_fault');$em->getPDO()->exec('CREATE TRIGGER employee_stage7_annual_fault BEFORE INSERT ON employee_holiday_ledger FOR EACH ROW BEGIN IF NEW.profile_id='.$em->getPDO()->quote($s['annualRecoveryProfiles'][1])." AND NEW.type='annualGrant' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stage7 synthetic second-profile ledger failure'; END IF; END");
    $s['annualFailureJob']=nativeAnnual('s7-annual-failure-'.$tag);
}
if($phase==='annual-failure'){
    $j=waitNative($s['annualFailureJob']);check('native-annual-failure-detected',$j['status']==='Failed',$j['status']);
    $first=$em->getEntityById('EmployeeHolidayProfile',$s['annualRecoveryProfiles'][0]);$second=$em->getEntityById('EmployeeHolidayProfile',$s['annualRecoveryProfiles'][1]);check('completed-profile-committed-failed-profile-rolled-back',(float)$first->get('balance')===26.0&&(float)$second->get('balance')===5.0,[$first->get('balance'),$second->get('balance')]);
    $s['firstRecoveryLedger']=$em->getRDBRepository('EmployeeHolidayLedger')->where(['profileId'=>$first->getId(),'type'=>'annualGrant'])->findOne()?->getId();
    $em->getPDO()->exec('DROP TRIGGER IF EXISTS employee_stage7_annual_fault');$s['annualRetryJob']=nativeAnnual('s7-annual-retry-'.$tag);
}
if($phase==='annual-recovered'){
    $j=waitNative($s['annualRetryJob']);check('annual-native-retry-completes',$j['status']==='Success',$j['status']);foreach($s['annualRecoveryProfiles'] as $id){$p=$em->getEntityById('EmployeeHolidayProfile',$id);check('annual-recovery-single-credit-'.$id,(float)$p->get('balance')===26.0&&$em->getRDBRepository('EmployeeHolidayLedger')->where(['profileId'=>$id,'type'=>'annualGrant'])->count()===1);}
    check('annual-retry-retains-first-ledger-ID',$em->getRDBRepository('EmployeeHolidayLedger')->where(['profileId'=>$s['annualRecoveryProfiles'][0],'type'=>'annualGrant'])->findOne()?->getId()===$s['firstRecoveryLedger']);$r=http('manager','PUT','EmployeeManagement/administration/holiday',settings(false));check('annual-recovery-disables-lab-grants',$r['status']===200,$r['status']);
}
if($phase==='projection-failure-setup'){
    $s['projectionFailure']=book('manager','2027-02-01','2027-02-02');$id=$s['projectionFailure'];$s['projectionFailureAccounting']=accounting('manager');
    $em->getPDO()->exec('DROP TRIGGER IF EXISTS employee_stage7_projection_fault');$em->getPDO()->exec('CREATE TRIGGER employee_stage7_projection_fault BEFORE INSERT ON employee_absence FOR EACH ROW BEGIN IF NEW.source_id='.$em->getPDO()->quote($id)." THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stage7 synthetic projection failure'; END IF; END");
    $r=api('manager',$id.'/approve',command($id,'s7-projection-init:'.$id));check('projection-failure-initiated-real-Job',$r['status']===200,$r['status']);
}
if($phase==='projection-failure'){
    $id=$s['projectionFailure'];$r=waitState($id,'Failed');$j=waitJob($id,'Failed');check('projection-fault-rolls-back-Approved',$r['status']==='ApprovalProcessing'&&$j['status']==='Failed'&&absence($id)===null,[$r['status'],$j['status']]);
    $s['projectionStagingIds']=array_column(docs($id),'id');check('rendered-staged-document-tracked',count($s['projectionStagingIds'])===1);
    $r=http('manager','GET','?entryPoint=download&id='.$s['projectionStagingIds'][0],null,true);check('failed-projection-does-not-publish-document',$r['status']===403,$r['status']);
    check('failed-projection-keeps-one-reservation',accounting('manager')==$s['projectionFailureAccounting']);
    $em->getPDO()->exec('DROP TRIGGER IF EXISTS employee_stage7_projection_fault');$r=api('manager',$id.'/retryApproval',command($id,'s7-projection-retry:'.$id));check('controlled-retry-after-projection-error',$r['status']===200,$r['status']);
}
if($phase==='projection-recovered'){
    $id=$s['projectionFailure'];$r=waitState($id,'Completed');check('retry-publishes-Approved-and-one-projection',$r['status']==='Approved'&&absence($id)!==null&&$em->getRDBRepository('EmployeeAbsence')->where(['sourceId'=>$id])->count()===1);
    check('retry-reuses-same-document-and-accounting',array_column(docs($id),'id')===$s['projectionStagingIds']&&accounting('manager')==$s['projectionFailureAccounting']);
    $a=absence($s['approved']);$s['cancelAccounting']=accounting('employee');$em->getPDO()->exec('DROP TRIGGER IF EXISTS employee_stage7_cancel_fault');$em->getPDO()->exec('CREATE TRIGGER employee_stage7_cancel_fault BEFORE UPDATE ON employee_absence FOR EACH ROW BEGIN IF NEW.id='.$em->getPDO()->quote($a->getId())." AND NEW.deleted=1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stage7 synthetic cancellation projection failure'; END IF; END");
    $payload=command($s['approved'],'s7-final-cancel:'.$s['approved'],['decision'=>'Cancelled']);$r=api('manager',$s['approved'].'/decideCancellation',$payload);check('cancellation-projection-failure-rolls-back-source-and-refund',$r['status']===500&&state($s['approved'])['status']==='CancellationPending'&&absence($s['approved'])!==null&&accounting('employee')==$s['cancelAccounting'],$r['status']);
    $em->getPDO()->exec('DROP TRIGGER IF EXISTS employee_stage7_cancel_fault');
    $multi=curl_multi_init();$handles=[];for($i=0;$i<2;$i++){$h=curl_init('http://127.0.0.1/api/v1/EmployeeManagement/holiday/'.$s['approved'].'/decideCancellation');curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Espo-Authorization: '.base64_encode($users['manager']->get('userName').':'.$password)],CURLOPT_TIMEOUT=>60]);curl_multi_add_handle($multi,$h);$handles[]=$h;}do{$rc=curl_multi_exec($multi,$running);if($running){curl_multi_select($multi,1);}}while($running&&$rc===CURLM_OK);$out=[];foreach($handles as $h){$out[]=['status'=>curl_getinfo($h,CURLINFO_HTTP_CODE),'body'=>json_decode(curl_multi_getcontent($h),true)];curl_multi_remove_handle($multi,$h);curl_close($h);}curl_multi_close($multi);
    check('parallel-cancellation-one-effect-and-receipt',array_column($out,'status')===[200,200]&&count(array_filter($out,fn($r)=>$r['body']['duplicate']??false))===1&&accounting('employee')['ledger']===$s['cancelAccounting']['ledger']+1,$out);
    check('final-cancellation-removes-projection-and-refunds-once',state($s['approved'])['status']==='Cancelled'&&absence($s['approved'])===null&&accounting('employee')['balance']==$s['cancelAccounting']['balance']+3);
    $r=http('colleague','GET','EmployeeAbsence/'.$s['absenceId']);check('cancelled-public-ID-is-not-readable',$r['status']===404,$r['status']);$r=calendar('colleague');check('Cancelled-not-in-common-calendar',!in_array($s['absenceId'],array_column($r['body']??[],'id')));
    $r=http('employee','GET','?entryPoint=download&id='.$s['documentId'],null,true);check('cancelled-document-remains-private-history',$r['status']===200&&str_starts_with($r['raw'],'PK'));
    $pending=$s['pending'];$r=api('manager',$pending.'/reject',command($pending,'s7-reject:'.$pending));check('Rejected-has-no-common-event',$r['status']===200&&absence($pending)===null);
    foreach($s['users'] as $kind=>$info){$p=$em->getRDBRepository('EmployeeHolidayProfile')->where(['assignedUserId'=>$info['id']])->findOne();$sum=0;foreach($em->getRDBRepository('EmployeeHolidayLedger')->where(['profileId'=>$p->getId()])->find() as $g){$sum+=(float)$g->get('delta');}check('ledger-sum-equals-balance-'.$kind,abs($sum-(float)$p->get('balance'))<0.00001,[$sum,$p->get('balance')]);}
}
if($phase==='privacy-extra'){
    $id=$s['projectionFailure'];$a=absence($id);$r=http('colleague','GET','EmployeeAbsence?'.http_build_query(['where'=>[['type'=>'equals','attribute'=>'name','value'=>$a->get('name')]]]));check('public-filter-positive-control-valid-native-format',$r['status']===200&&in_array($a->getId(),array_column($r['body']['list']??[],'id')),$r['status']);
    $r=http('colleague','GET','EmployeeAbsence?'.http_build_query(['where'=>[['type'=>'equals','attribute'=>'sourceId','value'=>$id]]]));check('source-field-not-queryable-by-public-client',in_array($r['status'],[400,403],true),$r['status']);
    foreach(['source','request','approvalDocuments','profile'] as $link){$r=http('colleague','GET','EmployeeAbsence/'.$a->getId().'/'.$link);check('no-private-relationship-'.$link,in_array($r['status'],[400,403,404],true),$r['status']);}
    $feed='Activities?'.http_build_query(['from'=>'2027-02-01 00:00:00','to'=>'2027-02-05 00:00:00','scopeList'=>'EmployeeAbsence']);$r=http('colleague','GET',$feed);check('eligible-employee-present-before-type-change',in_array($a->getId(),array_column($r['body']??[],'id')));
    $owner=$users['manager'];$owner->set('type','admin');$em->saveEntity($owner);$r=http('colleague','GET',$feed);check('converted-technical-admin-excluded-from-common-feed',!in_array($a->getId(),array_column($r['body']??[],'id')));$r=http('colleague','GET','EmployeeAbsence/'.$a->getId());check('converted-technical-admin-excluded-by-ID',in_array($r['status'],[403,404],true),$r['status']);$owner->set('type','regular');$em->saveEntity($owner);
    $service=$f->create(Espo\Modules\EmployeeManagement\Tools\Calendar\EmployeeAbsenceService::class);$service->rebuild();$before=$em->getRDBRepository('EmployeeAbsence')->count();$service->rebuild();check('backfill-repeat-keeps-single-projection-and-ID',absence($id)?->getId()===$a->getId()&&$em->getRDBRepository('EmployeeAbsence')->count()===$before);
    $em->removeEntity($a);$r=http('colleague','GET','EmployeeAbsence/'.$a->getId());check('controlled-soft-deleted-projection-not-readable',$r['status']===404,$r['status']);$service->rebuild();check('backfill-restores-soft-deleted-ID-without-duplicate',absence($id)?->getId()===$a->getId()&&$em->getRDBRepository('EmployeeAbsence')->clone($em->getQueryBuilder()->select()->from('EmployeeAbsence')->where(['sourceId'=>$id])->withDeleted()->build())->count()===1);
}
if($phase==='default-acl'){
    $u=fresh('User',['userName'=>'em_s7_unconfigured_'.$tag,'firstName'=>'Unconfigured','lastName'=>'Calendar','type'=>'regular','isActive'=>true,'password'=>$f->create(Espo\Core\Utils\PasswordHash::class)->hash($password)]);$users['unconfigured']=$u;
    $a=absence($s['projectionFailure']);$r=http('unconfigured','GET','EmployeeAbsence/'.$a->getId());check('unconfigured-native-role-no-public-record-access',$r['status']===403,$r['status']);$r=calendar('unconfigured');check('unconfigured-native-role-no-public-feed',!in_array($a->getId(),array_column($r['body']??[],'id')));
}
if($phase==='export-acl'){
    foreach(['manager','employee','colleague'] as $kind){$r=http($kind,'POST','Export',['entityType'=>'EmployeeAbsence','format'=>'csv','attributeList'=>['name','dateStart','dateEnd','sourceId'],'ids'=>[absence($s['projectionFailure'])->getId()]]);check('native-export-backend-closed-'.$kind,$r['status']===403,$r['status']);}
}
if($phase==='restore-calendar-config'){
    if(isset($s['zileViewOverride'])&&is_file($s['zileViewOverride'])){$target=$s['zileViewOverride'];if(hash_file('sha256',$target)!==hash_file('sha256','custom/Espo/Modules/ZileSarbatoare/Resources/metadata/clientDefs/Calendar.json')){throw new RuntimeException('Calendar test overlay was changed externally.');}rename($target,'/feasibility/stage7-zile-overlay-used-'.$tag.'.json');}
    if(isset($s['calendarListBefore'])){$writer=$f->create(Espo\Core\Utils\Config\ConfigWriter::class);$writer->set('calendarEntityList',$s['calendarListBefore']);$writer->save();}
    $until=microtime(true)+10;do{$r=http('admin','GET','Settings');if(($r['body']['calendarEntityList']??null)===$s['calendarListBefore']){break;}usleep(200000);}while(microtime(true)<$until);
    check('temporary-Calendar-config-restored-without-touching-originals',!is_file($s['zileViewOverride']??'/nonexistent')&&($r['body']['calendarEntityList']??null)===$s['calendarListBefore']);
}
if($phase==='browser-ready'){
    foreach(['2026-11-09','2026-11-16','2026-11-23','2026-11-26'] as $date){if(!$em->getRDBRepository('EmployeeHolidayRequest')->where(['assignedUserId'=>$users['manager']->getId(),'dateStartDate'=>$date,'status'=>['Pending','ApprovalProcessing','Approved','CancellationPending']])->findOne()){break;}}
    $end=(new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d');$s['browserSelf']=book('manager',$date,$end);
    check('browser-fixture-normal-CancellationPending',state($s['approved'])['status']==='CancellationPending');
}
if($phase==='calendar-configure'){
    $s['calendarListBefore']=$s['calendarListBefore']??$c->get('config')->get('calendarEntityList');$list=array_values(array_filter($s['calendarListBefore'],fn($value)=>$value!=='FeasibilityAbsence'));$writer=$f->create(Espo\Core\Utils\Config\ConfigWriter::class);$writer->set('calendarEntityList',$list);$writer->save();
    $until=microtime(true)+10;do{$r=http('admin','GET','Settings');if(!in_array('FeasibilityAbsence',$r['body']['calendarEntityList']??[],true)){break;}usleep(200000);}while(microtime(true)<$until);
    check('lab-only-unsupported-probe-excluded-from-Calendar',$r['status']===200&&!in_array('FeasibilityAbsence',$r['body']['calendarEntityList']??[],true));
}
if($phase==='calendar-boundary-setup'){
    $s['singleDay']=book('manager','2026-11-04','2026-11-04');$r=api('manager',$s['singleDay'].'/approve',command($s['singleDay'],'s7-single-day:'.$s['singleDay']));check('single-day-native-approval-initiated',$r['status']===200,$r['status']);$s['singlePending']=book('manager','2026-12-08','2026-12-08');
}
if($phase==='calendar-boundary'){
    $r=waitState($s['singleDay'],'Completed');check('single-day-approved-after-real-document',$r['status']==='Approved');$a=absence($s['singleDay']);$s['singleDayAbsence']=$a?->getId();
    $r=http('colleague','GET','Activities?'.http_build_query(['from'=>'2026-11-03 22:00:00','to'=>'2026-11-04 22:00:00','scopeList'=>'EmployeeAbsence']));check('all-day-single-date-visible-Europe-Bucharest',in_array($a?->getId(),array_column($r['body']??[],'id')),$r['status']);
    $r=http('colleague','GET','Activities?'.http_build_query(['from'=>'2026-11-04 22:00:00','to'=>'2026-11-05 22:00:00','scopeList'=>'EmployeeAbsence']));check('all-day-previous-date-outside-exclusive-range',!in_array($a?->getId(),array_column($r['body']??[],'id')),$r['status']);
    $r=http('manager','GET','Activities?'.http_build_query(['from'=>'2026-12-07 22:00:00','to'=>'2026-12-08 22:00:00','scopeList'=>'EmployeeHolidayRequest']));check('own-Pending-single-date-visible-Europe-Bucharest',in_array($s['singlePending'],array_column($r['body']??[],'id')),$r['status']);
    $q='Activities?'.http_build_query(['from'=>'2026-11-03 22:00:00','to'=>'2026-11-04 12:00:00','scopeList'=>'EmployeeAbsence']);$r=http('colleague','GET',$q);check('all-day-partial-last-day-overlap',in_array($a?->getId(),array_column($r['body']??[],'id')),$r['status']);
    $prefs=http('manager','GET','Preferences/'.$users['manager']->getId());$zone=$prefs['body']['timeZone']??null;$r=http('manager','PUT','Preferences/'.$users['manager']->getId(),['timeZone'=>'America/New_York']);check('native-user-timezone-change-for-Calendar',$r['status']===200,$r['status']);
    $r=http('manager','GET','Activities?'.http_build_query(['from'=>'2026-11-04 05:00:00','to'=>'2026-11-05 05:00:00','scopeList'=>'EmployeeAbsence']));check('single-day-visible-with-negative-UTC-offset',in_array($a?->getId(),array_column($r['body']??[],'id')),$r['status']);$r=http('manager','PUT','Preferences/'.$users['manager']->getId(),['timeZone'=>$zone]);check('native-user-timezone-restored',$r['status']===200,$r['status']);
}
if($phase==='calendar-color'){
    $p=$em->getRDBRepository('EmployeeHolidayProfile')->where(['assignedUserId'=>$users['employee']->getId()])->findOne();$before=accounting('employee');$r=http('manager','POST','EmployeeManagement/administration/profiles/bulkInitialize',['items'=>[['userId'=>$users['employee']->getId(),'annualEntitlement'=>(int)$p->get('annualEntitlement'),'nextResetDate'=>$p->get('nextResetDate'),'calendarColor'=>'#A1B2C3','idempotencyKey'=>'s7-color:'.$tag]]]);check('original-profile-color-setting-updated-natively',$r['status']===200,$r['status']);
    $r=calendar('colleague');$events=array_values(array_filter($r['body']??[],fn($e)=>($e['id']??null)===$s['absenceId']));check('common-Calendar-retains-original-live-employee-color',($events[0]['color']??null)==='#A1B2C3',$events[0]??null);check('color-change-does-not-change-balance',accounting('employee')['balance']==$before['balance']);
    $role=fresh('Role',['name'=>'Calendar color FLS '.$tag,'data'=>(object)['Calendar'=>true,'EmployeeAbsence'=>(object)['read'=>'all','create'=>'no','edit'=>'no','delete'=>'no']],'fieldData'=>(object)['EmployeeAbsence'=>(object)['color'=>(object)['read'=>'no','edit'=>'no']]],'userCalendarPermission'=>'no']);
    $users['colorRestricted']=fresh('User',['userName'=>'em_s7_color_'.$tag,'firstName'=>'Color','lastName'=>'FLS','type'=>'regular','isActive'=>true,'rolesIds'=>[$role->getId()],'password'=>$f->create(Espo\Core\Utils\PasswordHash::class)->hash($password),'workingTimeCalendarId'=>$s['calendarId']]);
    $r=calendar('colorRestricted');$items=array_values(array_filter($r['body']??[],fn($e)=>($e['id']??null)===$s['absenceId']));check('shared-absence-needs-no-global-other-user-Calendar-permission',$r['status']===200&&count($items)===1,$r['status']);check('live-display-color-respects-native-field-ACL',count($items)===1&&($items[0]['color']??null)===null,$items[0]??null);
    $r=http('colorRestricted','GET','EmployeeHolidayRequest/'.$s['approved']);check('public-color-role-does-not-grant-private-request',$r['status']===403,$r['status']);
}
if($phase==='zile-view-setup'){
    $target='custom/Espo/Custom/Resources/metadata/clientDefs/Calendar.json';if(is_file($target)){throw new RuntimeException('Refuse to overwrite existing Calendar customization.');}
    if(!is_dir(dirname($target))){mkdir(dirname($target),0755,true);}file_put_contents($target,file_get_contents('custom/Espo/Modules/ZileSarbatoare/Resources/metadata/clientDefs/Calendar.json'));$s['zileViewOverride']=$target;
    check('lab-native-configuration-selects-original-ZileSarbatoare-view',true);
}
if($phase==='concurrency-setup'){
    $r=http('manager','PUT','EmployeeManagement/administration/holiday',settings(true));check('race-explicit-opt-in',$r['status']===200,$r['status']);
    $s['loggerBefore']=$c->get('config')->get('logger');$logger=(array)$s['loggerBefore'];$logger['sqlFailed']=true;$writer=$f->create(Espo\Core\Utils\Config\ConfigWriter::class);$writer->set('logger',$logger);$writer->save();
    $s['raceProfiles']=[];$s['raceUsers']=[];
    for($i=0;$i<3;$i++){$u=fresh('User',['userName'=>'em_s7_race_'.$tag.'_'.$i,'firstName'=>'Annual','lastName'=>'Concurrency '.$i,'type'=>'regular','isActive'=>true,'rolesIds'=>$users['employee']->get('rolesIds'),'workingTimeCalendarId'=>$s['calendarId']]);$s['raceUsers'][]=$u->getId();$r=http('manager','POST','EmployeeManagement/administration/profiles/bulkInitialize',['items'=>[['userId'=>$u->getId(),'annualEntitlement'=>21,'openingBalance'=>5,'nextResetDate'=>$c->get('dateTime')->getToday()->toString(),'calendarColor'=>'#4F8A8B','idempotencyKey'=>'s7-race-init:'.$u->getId()]]]);check('race-profile-'.$i,$r['status']===200,$r['status']);$s['raceProfiles'][]=$r['body']['list'][0]['profileId'];}
    $em->getPDO()->exec('DROP TRIGGER IF EXISTS employee_stage7_race');$em->getPDO()->exec('CREATE TRIGGER employee_stage7_race BEFORE UPDATE ON employee_holiday_profile FOR EACH ROW BEGIN IF NEW.id='.$em->getPDO()->quote($s['raceProfiles'][0]).' AND NEW.balance > OLD.balance THEN DO SLEEP(2); END IF; END');
    $s['newRaceJobs']=[nativeAnnual('s7-new-race-a-'.$tag),nativeAnnual('s7-new-race-b-'.$tag)];
}
if($phase==='concurrency-complete'){
    foreach($s['newRaceJobs'] as $id){$j=waitNative($id);check('annual-race-no-hidden-retry-'.$id,$j['status']==='Success'&&($j['failedAttempts']??0)===0,$j);}
    foreach($s['raceProfiles'] as $id){$p=$em->getEntityById('EmployeeHolidayProfile',$id);check('annual-concurrency-single-effect-'.$id,(float)$p->get('balance')===26.0&&$em->getRDBRepository('EmployeeHolidayLedger')->where(['profileId'=>$id,'type'=>'annualGrant'])->count()===1,$p->get('balance'));}
    $em->getPDO()->exec('DROP TRIGGER IF EXISTS employee_stage7_race');$writer=$f->create(Espo\Core\Utils\Config\ConfigWriter::class);$writer->set('logger',(array)$s['loggerBefore']);$writer->set('employeeHolidayAutomaticAnnualGrantEnabled',false);$writer->save();
}
if($phase==='cleanup'){
    foreach(['employee_stage7_projection_fault','employee_stage7_cancel_fault','employee_stage7_race','employee_stage7_annual_fault'] as $trigger){$em->getPDO()->exec('DROP TRIGGER IF EXISTS '.$trigger);}
    foreach($s['previousActive']??[] as $id){$u=$em->getEntityById('User',$id);if($u&&!$u->isActive()){$u->set('isActive',true);$em->saveEntity($u);}}
    $writer=$f->create(Espo\Core\Utils\Config\ConfigWriter::class);$writer->set('employeeHolidayAutomaticAnnualGrantEnabled',false);if(isset($s['loggerBefore'])){$writer->set('logger',(array)$s['loggerBefore']);}$writer->save();
    $scheduled=$em->getEntityById('ScheduledJob',$s['scheduledId']);$scheduled->set('scheduling',$s['scheduling']);$em->saveEntity($scheduled);
    $notRestored=$em->getRDBRepository('User')->where(['id'=>$s['previousActive']??[],'isActive'=>false])->count();$cfg=http('manager','GET','EmployeeManagement/administration/holiday');
    check('lab-cleanup-restores-cohort-and-disables-grants',$notRestored===0&&($cfg['body']['employeeHolidayAutomaticAnnualGrantEnabled']??true)===false,['restored'=>count($s['previousActive']??[]),'inactive'=>$notRestored]);
}
file_put_contents($path,json_encode($s,JSON_PRETTY_PRINT));
file_put_contents('/feasibility/employee-stage7-'.$phase.'-results.json',json_encode(['version'=>'0.7.0','phase'=>$phase,'results'=>$results,'assertions'=>count($results),'failures'=>$failures],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode(['assertions'=>count($results),'failures'=>$failures]).PHP_EOL;exit($failures?1:0);
