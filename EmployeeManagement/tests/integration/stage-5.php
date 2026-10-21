<?php
declare(strict_types=1);
// Never shipped. All fixture writes and controlled finalizations require this exact isolated lab.
if (getenv('EMPLOYEE_TEST_LAB') !== 'employee-feasibility-20261020') { throw new RuntimeException('Isolated lab only.'); }
chdir('/var/www/html'); require 'bootstrap.php';
$c = (new Espo\Core\Application())->getContainer(); $em = $c->get('entityManager'); $factory = $c->get('injectableFactory');
if ($c->get('config')->get('version') !== '10.0.1' || $c->get('config')->get('siteUrl') !== 'http://127.0.0.1:18089') { throw new RuntimeException('Wrong environment.'); }
$password = getenv('EMPLOYEE_TEST_PASSWORD') ?: throw new RuntimeException('Synthetic password required.');
$adminPassword = getenv('EMPLOYEE_TEST_ADMIN_PASSWORD') ?: throw new RuntimeException('Synthetic admin password required.');
$admin = $em->getRDBRepository('User')->where(['userName' => 'admin'])->findOne();
if (($argv[1]??'') !== 'child') { $c->getByClass(Espo\Core\ApplicationUser::class)->setUser($admin); }
$results = []; $failures = 0; $tag = uniqid('s5'); $users = [];
function check(string $test, bool $ok, mixed $observed = null): void { global $results, $failures; $r = ['test'=>$test,'status'=>$ok?'PASS':'FAIL','observed'=>$observed]; $results[]=$r; if (!$ok) {$failures++;} echo json_encode($r, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL; }
function fresh(string $type, array $values): Espo\ORM\Entity { global $em; $e=$em->getNewEntity($type); $e->set($values); $em->saveEntity($e); return $e; }
function http(string $kind, string $method, string $path, ?array $body=null): array { global $users,$password,$adminPassword; $name=$kind==='admin'?'admin':$users[$kind]->get('userName'); $pw=$kind==='admin'?$adminPassword:$password; $h=curl_init('http://127.0.0.1/api/v1/'.$path); $reason=''; curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Espo-Authorization: '.base64_encode($name.':'.$pw)],CURLOPT_TIMEOUT=>90,CURLOPT_HEADERFUNCTION=>static function($h,string $line)use(&$reason):int{if(str_starts_with(strtolower($line),'x-status-reason:')){$reason=trim(substr($line,16));}return strlen($line);}]); if ($body!==null) {curl_setopt($h,CURLOPT_POSTFIELDS,json_encode($body));} $raw=curl_exec($h); if ($raw===false) {throw new RuntimeException(curl_error($h));} $s=curl_getinfo($h,CURLINFO_HTTP_CODE); curl_close($h); return ['status'=>$s,'body'=>json_decode($raw,true),'raw'=>$raw,'reason'=>$reason]; }
function holiday(string $kind,string $operation,?array $body=null): array {return http($kind,$body===null?'GET':'POST','EmployeeManagement/holiday/'.$operation,$body);}
function attendance(string $kind,string $operation,?array $body=null): array {return http($kind,$body===null?'GET':'POST','EmployeeManagement/attendance/'.$operation,$body);}
function state(string $id): array { global $em; return json_decode(json_encode($em->getRDBRepository('EmployeeHolidayRequest')->where(['id'=>$id])->findOne()->getValueMap(),JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR); }
function command(string $id,string $key,array $extra=[]): array {return ['idempotencyKey'=>$key,'revision'=>(int)state($id)['accountingRevision']]+$extra;}
function balance(string $kind): float {global $em,$users;return (float)$em->getRDBRepository('EmployeeHolidayProfile')->where(['assignedUserId'=>$users[$kind]->getId()])->findOne()->get('balance');}
function ledgerCount(string $kind): int {global $em,$users;return $em->getRDBRepository('EmployeeHolidayLedger')->where(['assignedUserId'=>$users[$kind]->getId()])->count();}
function book(string $kind,string $start,string $end,?string $target=null): array {global $tag,$users;$payload=['dateStart'=>$start,'dateEnd'=>$end,'description'=>'PRIVATE Stage5 '.$tag,'idempotencyKey'=>uniqid($tag)];if($target!==null){$payload+=['userId'=>$users[$target]->getId(),'managed'=>true];}return holiday($kind,'create',$payload);}
function mark(string $kind,string $date,string $status='AtWork'): array {return attendance($kind,'mark',['date'=>$date,'status'=>$status]);}
function correct(string $kind,string $date,string $status='MedicalLeave'): array {global $users;return attendance('manager','managerMark',['userId'=>$users[$kind]->getId(),'date'=>$date,'status'=>$status,'reason'=>'Stage5 explicit audited conflict resolution']);}
function cell(array $overview,string $kind,string $date): array {global $users;foreach($overview['rows']??[] as $row){if($row['date']!==$date){continue;}foreach($row['cells'] as $cell){if($cell['userId']===$users[$kind]->getId()){return $cell;}}}return [];}
function overview(string $month='2026-10'): array {$r=attendance('manager','overview?month='.$month);if($r['status']!==200){throw new RuntimeException('Overview failed: '.$r['raw']);}return $r['body'];}
function normHash(string $month): string {global $em;return hash('sha256',json_encode($em->getRDBRepository('EmployeeAttendanceMonth')->where(['month'=>$month])->findOne()->get('norms')));}
function document(string $id): void {global $em,$factory;$request=$em->getRDBRepository('EmployeeHolidayRequest')->where(['id'=>$id])->findOne();$a=$em->getNewEntity('Attachment');$a->setName('stage5-historical-fixture.docx')->setType('application/vnd.openxmlformats-officedocument.wordprocessingml.document')->setRole(Espo\Entities\Attachment::ROLE_ATTACHMENT)->setParent($request)->setTargetField('approvalDocuments');$em->saveEntity($a);$factory->create(Espo\Core\FileStorage\Manager::class)->putContents($a,file_get_contents('custom/Espo/Modules/EmployeeManagement/Resources/templates/holiday-request.docx'));}
/** Lab-only historical fixture. Never invokes or enables Pending->Approved through the API. */
function approvedFixture(string $id,?string $historicalDate=null): void {global $em;document($id);$em->getTransactionManager()->run(function()use($id,$historicalDate,$em){$r=$em->getRDBRepository('EmployeeHolidayRequest')->where(['id'=>$id])->findOne();$em->getRDBRepository('User')->where(['id'=>$r->get('assignedUserId')])->forUpdate()->findOne();if($historicalDate!==null){$r->set(['dateStartDate'=>$historicalDate,'dateEndDate'=>$historicalDate]);}$r->set('status','Approved');$em->saveEntity($r);});}
function parallel(array $calls): array {global $users,$password;$multi=curl_multi_init();$handles=[];foreach($calls as [$kind,$area,$path,$body]){$h=curl_init('http://127.0.0.1/api/v1/EmployeeManagement/'.$area.'/'.$path);curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Espo-Authorization: '.base64_encode($users[$kind]->get('userName').':'.$password)],CURLOPT_TIMEOUT=>90]);curl_multi_add_handle($multi,$h);$handles[]=$h;}do{$s=curl_multi_exec($multi,$running);if($running){curl_multi_select($multi,1);}}while($running&&$s===CURLM_OK);$out=[];foreach($handles as $h){$out[]=['status'=>curl_getinfo($h,CURLINFO_HTTP_CODE),'body'=>json_decode(curl_multi_getcontent($h),true)];curl_multi_remove_handle($multi,$h);curl_close($h);}curl_multi_close($multi);return $out;}

// Deterministic independent PHP/DB connections. Uses real runtime commands/guard, never a public test endpoint.
if (($argv[1]??'')==='child') {
    $s=json_decode(file_get_contents('/feasibility/employee-stage5-state.json'),true);$kind=$argv[2];$mode=$argv[3];$date=$argv[4];$id=$argv[5];
    foreach($s['users'] as $key=>$v){$users[$key]=$em->getEntityById('User',$v['id']);}
    $actor=$mode==='mark'?$users[$kind]:$users['manager'];$c->getByClass(Espo\Core\ApplicationUser::class)->setUser($actor);
    echo "START\n";fflush(STDOUT);
    try {
        $out=$em->getTransactionManager()->run(function()use($kind,$mode,$date,$id,$em,$factory,$users){
            $em->getRDBRepository('User')->where(['id'=>$users[$kind]->getId()])->forUpdate()->findOne();
            if($mode==='cancel'){$r=state($id);$out=$factory->create(Espo\Modules\EmployeeManagement\Tools\HolidayBalance\HolidayRequestService::class)->command($id,'cancel',['idempotencyKey'=>'child-cancel:'.$id,'revision'=>(int)$r['accountingRevision'],'reason'=>'Controlled concurrent cancellation']);}
            echo "READY\n";fflush(STDOUT);if(trim((string)fgets(STDIN))!=='go'){throw new RuntimeException('Missing test barrier release.');}
            if($mode==='cancel'){return $out;}
            if($mode==='mark'){return $factory->create(Espo\Modules\EmployeeManagement\Tools\Attendance\AttendanceOverviewService::class)->markOwn($date,'AtWork',0);}
            // Controlled future-finalizer contract probe only, not a delivered Job or approval implementation.
            $r=$em->getRDBRepository('EmployeeHolidayRequest')->where(['id'=>$id])->forUpdate()->findOne();
            if($r->get('status')!=='Pending'){throw new Espo\Core\Exceptions\Conflict('Fixture state changed.');}
            $factory->create(Espo\Modules\EmployeeManagement\Tools\Attendance\AttendanceMonthService::class)->lockPeriods([[$r->get('dateStartDate'),$r->get('dateEndDate')]]);
            $factory->create(Espo\Modules\EmployeeManagement\Tools\Attendance\HolidayAttendanceGuard::class)->assertCompatible($r);
            $has=false;foreach($em->getRDBRepository('Attachment')->where(['parentId'=>$id,'parentType'=>'EmployeeHolidayRequest','field'=>'approvalDocuments'])->find() as $a){$has=$has||$factory->create(Espo\Core\FileStorage\Manager::class)->exists($a);}
            if(!$has){throw new RuntimeException('Persisted fixture document required.');}
            $r->set(['status'=>'Approved','accountingRevision'=>(int)$r->get('accountingRevision')+1]);$em->saveEntity($r);
            return ['fixtureOnly'=>true,'status'=>'Approved'];
        });
        echo json_encode(['status'=>200,'body'=>$out]).PHP_EOL;
    }catch(Espo\Core\Exceptions\Conflict $e){echo json_encode(['status'=>409,'message'=>$e->getMessage()]).PHP_EOL;}
    exit;
}
function child(string $kind,string $mode,string $date,string $id): array {$pipes=[];$p=proc_open([PHP_BINARY,__FILE__,'child',$kind,$mode,$date,$id],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($p)){throw new RuntimeException('Child unavailable.');}$start=trim((string)fgets($pipes[1]));if($start!=='START'){throw new RuntimeException('Child failed to start: '.$start.stream_get_contents($pipes[2]));}return [$p,$pipes];}
function ready(array $child): void {$line=trim((string)fgets($child[1][1]));if($line!=='READY'){throw new RuntimeException('Test barrier failed: '.$line.stream_get_contents($child[1][2]));}}
function release(array $child): void {fwrite($child[1][0],"go\n");fflush($child[1][0]);}
function finish(array $child): array {$output=stream_get_contents($child[1][1]);$error=stream_get_contents($child[1][2]);foreach($child[1] as $pipe){fclose($pipe);}$code=proc_close($child[0]);if($code!==0){throw new RuntimeException('Child exit '.$code.': '.$error.' '.$output);}foreach(array_reverse(explode("\n",trim($output))) as $line){$r=json_decode($line,true);if(is_array($r)){return $r;}}throw new RuntimeException('Child result missing '.$output.' '.$error);}

if (($argv[1]??'')==='extra') {
    $s=json_decode(file_get_contents('/feasibility/employee-stage5-state.json'),true);foreach($s['users'] as $kind=>$v){$users[$kind]=$em->getEntityById('User',$v['id']);}
    $r=book('manager','2026-10-15','2026-10-15','eight');check('edit-race-Pending-fixture',$r['status']===200,$r);$id=$r['body']['id'];$before=[balance('eight'),ledgerCount('eight')];
    $edit=command($id,'parallel-update-'.$tag,['dateStart'=>'2026-10-15','dateEnd'=>'2026-10-15','description'=>'Concurrent description update']);
    $race=parallel([['eight','holiday',$id.'/update',$edit],['eight','attendance','mark',['date'=>'2026-10-15','status'=>'AtWork']]]);
    check('HTTP-modification-vs-mark-serialized',in_array($race[0]['status'],[200,409],true)&&$race[1]['status']===200,$race);
    check('concurrent-description-edit-no-double-accounting',[balance('eight'),ledgerCount('eight')]===$before);
    $r=holiday('manager',$id.'/approve',command($id,'approval-after-edit-race-'.$tag));check('approval-rechecks-after-edit-mark-race',$r['status']===409&&str_contains($r['reason'],'attendance'),$r['reason']);
    $r=book('manager','2026-10-16','2026-10-16','eight');check('cancel-mark-race-Pending-fixture',$r['status']===200,$r);$id=$r['body']['id'];approvedFixture($id);$before=[balance('eight'),ledgerCount('eight')];
    $cancel=command($id,'parallel-cancel-'.$tag,['reason'=>'Concurrent cancellation vs attendance']);
    $race=parallel([['manager','holiday',$id.'/cancel',$cancel],['eight','attendance','mark',['date'=>'2026-10-16','status'=>'AtWork']]]);
    check('HTTP-cancellation-vs-mark-serialized',$race[0]['status']===200&&in_array($race[1]['status'],[200,409],true),$race);
    check('concurrent-cancel-final-state-and-exact-refund',state($id)['status']==='Cancelled'&&balance('eight')===$before[0]+1&&ledgerCount('eight')===$before[1]+1);
    $r=holiday('manager',$id.'/cancel',$cancel);check('concurrent-cancellation-retry-no-duplicate-ledger',$r['status']===200&&($r['body']['duplicate']??false)&&ledgerCount('eight')===$before[1]+1,$r['status']);
    check('matrix-after-concurrent-cancel-never-CO',cell(overview(),'eight','2026-10-16')['status']!=='Holiday');
    $r=mark('eight','2026-10-16');check('mark-after-final-cancellation-allowed',$r['status']===200,$r['status']);
    $r=holiday('eight',$id.'/update',command($id,'stale-terminal-update-'.$tag,['dateStart'=>'2026-10-16','dateEnd'=>'2026-10-16','description'=>'Forbidden terminal change']));check('Cancelled-not-editable-even-after-attendance',$r['status']===409,$r['status']);
    $sum=0.0;foreach($em->getRDBRepository('EmployeeHolidayLedger')->where(['assignedUserId'=>$users['eight']->getId()])->find() as $entry){$sum+=(float)$entry->get('delta');}check('extra-races-ledger-sum-equals-live-balance',$sum===balance('eight'),[$sum,balance('eight')]);
    file_put_contents('/feasibility/employee-stage5-extra-results.json',json_encode(['version'=>'0.5.0','results'=>$results,'assertions'=>count($results),'failures'=>$failures],JSON_PRETTY_PRINT));echo json_encode(['assertions'=>count($results),'failures'=>$failures]).PHP_EOL;exit($failures?1:0);
}

$s=json_decode(file_get_contents('/feasibility/employee-stage4-state.json'),true);foreach($s['users'] as $kind=>$v){$users[$kind]=$em->getEntityById('User',$v['id']);}
if($c->get('dateTime')->getToday()->toString()!=='2026-10-20'){throw new RuntimeException('Dated synthetic scenario requires 2026-10-20.');}
$original=[];foreach(['AttendanceRecord','AttendanceWorkSchedule','HolidayRequest','HolidayProfile','HolidayLedger'] as $type){$original[$type]=$em->getRDBRepository($type)->count();}
// Extend only the synthetic Stage4 Roles with native Holiday rights; no mandatory grants or admin business role.
foreach($users as $kind=>$user){foreach($em->getRDBRepository('User')->getRelation($user,'roles')->find() as $role){$data=(array)$role->get('data');foreach(['EmployeeHolidayBooking'=>!in_array($kind,['viewer','nomark']), 'EmployeeHolidayRequestManagement'=>$kind==='manager','EmployeeHolidayApproval'=>$kind==='manager','EmployeeHolidaySettings'=>$kind==='manager','EmployeeHolidayProfileManagement'=>$kind==='manager','EmployeeHolidayBalanceCorrection'=>$kind==='manager'] as $scope=>$value){$data[$scope]=$value;}foreach(['EmployeeHolidayRequest','EmployeeHolidayProfile','EmployeeHolidayLedger'] as $type){$data[$type]=(object)['read'=>$kind==='manager'?'all':'own','create'=>'no','edit'=>'no','delete'=>'no'];}$role->set('data',(object)$data);$em->saveEntity($role);}}
$writer=$factory->create(Espo\Core\Utils\Config\ConfigWriter::class);$writer->setMultiple(['employeeHolidayApproversIds'=>[$users['manager']->getId()],'employeeHolidayNegativeBalanceLimitDays'=>-21]);$writer->save();
// ORM fixtures do not run Role Record Service's AfterSave. Use its native ACL cache clearer explicitly.
foreach($users as $user){$factory->create(Espo\Core\Acl\Cache\Clearer::class)->clearForUser($user);}
foreach(['manager','four','six','eight','cic','remote','race','ui'] as $kind){$r=http('manager','POST','EmployeeManagement/administration/profiles/bulkInitialize',['items'=>[['userId'=>$users[$kind]->getId(),'annualEntitlement'=>21,'openingBalance'=>30,'nextResetDate'=>'2027-01-01','calendarColor'=>'#4F8A8B','idempotencyKey'=>'s5-init:'.$users[$kind]->getId()]]]);check('native-profile-initialization-'.$kind,$r['status']===200,$r['status']);}
file_put_contents('/feasibility/employee-stage5-state.json',json_encode(['users'=>$s['users'],'today'=>'2026-10-20'],JSON_PRETTY_PRINT));

foreach(['AtWork'=>'2026-10-01','BusinessTrip'=>'2026-10-02'] as $status=>$date){$r=mark('four',$date,$status);check('existing-attendance-'.$status,$r['status']===200,$r['status']);$before=[balance('four'),ledgerCount('four')];$r=book('manager',$date,$date,'four');check('create-rejects-'.$status,$r['status']===409&&str_contains($r['reason'],'attendance'),$r);check('create-conflict-no-accounting-effect-'.$status,[balance('four'),ledgerCount('four')]===$before);}
$r=book('manager','2026-10-05','2026-10-05','four');check('managed-Pending-before-attendance',$r['status']===200,$r);$id=$r['body']['id'];
$r=mark('four','2026-10-05');check('Pending-not-effective-absence-no-new-business-rule',$r['status']===200,$r['status']);$before=[balance('four'),ledgerCount('four'),state($id)];
$r=holiday('four',$id.'/update',command($id,'description-conflict-'.$tag,['dateStart'=>'2026-10-05','dateEnd'=>'2026-10-05','description'=>'Unchanged period, revalidate']));check('description-update-rechecks-attendance',$r['status']===409,$r);check('update-conflict-full-rollback',[balance('four'),ledgerCount('four'),state($id)]===$before);
$r=holiday('manager',$id.'/approve',command($id,'approval-conflict-'.$tag));check('approval-checks-real-conflict-before-Jobs-gate',$r['status']===409&&str_contains($r['reason'],'attendance'),$r);
$r=correct('four','2026-10-05');check('explicit-audited-manager-conflict-resolution',$r['status']===200,$r['status']);
$r=holiday('four',$id.'/update',command($id,'description-resolved-'.$tag,['dateStart'=>'2026-10-05','dateEnd'=>'2026-10-05','description'=>'Resolved, preserve norm']));check('Pending-edit-after-explicit-resolution',$r['status']===200,$r);
$r=holiday('manager',$id.'/approve',command($id,'approval-Jobs-'.$tag));check('compatible-approval-still-blocked-no-provisional-state',$r['status']===409&&str_contains($r['reason'],'Jobs')&&state($id)['status']==='Pending',$r);
$r=mark('four','2026-10-20');check('today-work-for-date-update-conflict',$r['status']===200,$r['status']);$before=[balance('four'),ledgerCount('four'),state($id)];
$r=holiday('four',$id.'/update',command($id,'dates-conflict-'.$tag,['dateStart'=>'2026-10-20','dateEnd'=>'2026-10-20','description'=>'New dates']));check('date-change-rechecks-attendance',$r['status']===409,$r);check('date-change-conflict-rolls-back-snapshot-ledger',[balance('four'),ledgerCount('four'),state($id)]===$before);
$withdraw=command($id,'withdraw-'.$tag);$r=holiday('four',$id.'/withdraw',$withdraw);check('withdraw-refundable-once',$r['status']===200&&balance('four')===30.0,$r['status']);$count=ledgerCount('four');$r=holiday('four',$id.'/withdraw',$withdraw);check('withdraw-retry-no-duplicate-ledger',$r['status']===200&&ledgerCount('four')===$count,$r['status']);
$r=book('manager','2026-10-05','2026-10-05','four');check('Cancelled-history-does-not-block-reservation',$r['status']===200,$r['status']);$rejected=$r['body']['id'];$r=holiday('manager',$rejected.'/reject',command($rejected,'reject-'.$tag));check('reject-refund',$r['status']===200&&balance('four')===30.0,$r['status']);$r=book('manager','2026-10-05','2026-10-05','four');check('Rejected-history-does-not-block-reservation',$r['status']===200,$r['status']);$r=holiday('four',$r['body']['id'].'/withdraw',command($r['body']['id'],'withdraw-rebook-'.$tag));

// A booking reads the captured month, not a subsequently reconfigured WTC.
$monthHash=normHash('2026-10');$expectedHours=cell(overview(),'four','2026-10-22')['normHours'];$r=book('four','2026-10-22','2026-10-22');check('shared-history-reservation',$r['status']===200,$r);$historyId=$r['body']['id'];$snap=(array)state($historyId)['calculationSnapshot'];$days=(array)$snap['days'];check('Holiday-uses-Attendance-historical-WTC-hours',(float)($days['2026-10-22']??0)===(float)$expectedHours,$snap);check('Holiday-never-rewrites-captured-month',normHash('2026-10')===$monthHash);
$calendar=$em->getEntityById('WorkingTimeCalendar',$users['four']->get('workingTimeCalendarId'));$calendar->set(['weekdays'=>[1,2,3,4],'timeRanges'=>[['07:00','15:00']]]);$em->saveEntity($calendar);
$r=book('four','2026-10-23','2026-10-23');check('changed-WTC-weekdays-do-not-diverge-from-captured-month',$r['status']===200,$r);$stableId=$r['body']['id'];
// First future booking captures a single normative month for both components, without personal availability.
$r=book('six','2026-11-23','2026-11-24');check('future-booking-captures-month-once',$r['status']===200,$r);$futureId=$r['body']['id'];$futureHash=normHash('2026-11');$r=holiday('six',$futureId.'/update',command($futureId,'future-description-'.$tag,['dateStart'=>'2026-11-23','dateEnd'=>'2026-11-24','description'=>'Stable future']));check('description-edit-never-recalculates-norm',$r['status']===200&&normHash('2026-11')===$futureHash,$r['status']);

// Real API cancellation transitions over documented historical fixtures, never API approval.
$r=book('four','2026-10-21','2026-10-21');$cycleId=$r['body']['id'];approvedFixture($cycleId);$before=[balance('four'),ledgerCount('four')];
check('Approved-projected-in-matrix',cell(overview(),'four','2026-10-21')['status']==='Holiday');
$r=holiday('four',$cycleId.'/requestCancellation',command($cycleId,'request-cancel-'.$tag,['reason'=>'Private cancellation']));check('Approved-to-CancellationPending',$r['status']===200&&state($cycleId)['status']==='CancellationPending',$r['status']);check('CancellationPending-no-refund',[balance('four'),ledgerCount('four')]===$before);check('CancellationPending-remains-projected',cell(overview(),'four','2026-10-21')['status']==='Holiday');
$r=book('four','2026-10-21','2026-10-21');check('CancellationPending-still-reserves',$r['status']===409,$r['status']);
$r=holiday('manager',$cycleId.'/decideCancellation',command($cycleId,'deny-cancel-'.$tag,['decision'=>'Approved']));check('documented-cancellation-refusal-keeps-CO-and-balance',$r['status']===200&&cell(overview(),'four','2026-10-21')['status']==='Holiday'&&[balance('four'),ledgerCount('four')]===$before,$r['status']);
$r=holiday('four',$cycleId.'/requestCancellation',command($cycleId,'request-cancel-again-'.$tag,['reason'=>'Final cancellation']));$cancel=command($cycleId,'final-cancel-'.$tag,['decision'=>'Cancelled']);$r=holiday('manager',$cycleId.'/decideCancellation',$cancel);check('final-cancellation-removes-CO',$r['status']===200&&cell(overview(),'four','2026-10-21')['status']===null,$r['status']);$count=ledgerCount('four');$r=holiday('manager',$cycleId.'/decideCancellation',$cancel);check('final-cancel-retry-one-ledger',$r['status']===200&&ledgerCount('four')===$count,$r['status']);
$r=book('four','2026-10-21','2026-10-21');check('same-period-bookable-after-final-cancellation',$r['status']===200,$r['status']);$r=holiday('four',$r['body']['id'].'/withdraw',command($r['body']['id'],'clear-cycle-'.$tag));

// CIC/CCC priority and medical retained through actual cancellation, not a second absence calculator.
$r=attendance('manager','schedule',['userId'=>$users['cic']->getId(),'remote'=>false,'cic'=>true,'cicStartDate'=>'2026-10-01','cicEndDate'=>'2026-10-31']);check('CIC-configured-inclusive-period',$r['status']===200,$r['status']);
$r=book('manager','2026-10-07','2026-10-07','cic');check('CO-booking-permitted-during-CIC',$r['status']===200,$r);$cicId=$r['body']['id'];approvedFixture($cicId);
check('CO-higher-priority-than-CIC',cell(overview(),'cic','2026-10-07')['status']==='Holiday');foreach(['AtWork','BusinessTrip','MedicalLeave'] as $status){$r=mark('cic','2026-10-07',$status);check('effective-CO-blocks-manual-'.$status,$r['status']===409,$r['status']);}
$r=holiday('manager',$cicId.'/cancel',command($cicId,'cancel-cic-'.$tag,['reason'=>'Restore CIC']));check('final-cancel-restores-CIC-without-manual-CO-record',$r['status']===200&&cell(overview(),'cic','2026-10-07')['status']==='ChildCareLeave',$r['status']);
$r=mark('cic','2026-10-07','MedicalLeave');check('medical-permitted-after-CO-cancel-during-CIC',$r['status']===200&&cell(overview(),'cic','2026-10-07')['status']==='MedicalLeave',$r['status']);foreach(['AtWork','BusinessTrip'] as $status){$r=mark('cic','2026-10-07',$status);check('CIC-still-blocks-work-after-medical-'.$status,$r['status']===409,$r['status']);}

// Frozen month, cancellation, audit and fresh regenerated exports share the same aggregate.
$r=correct('cic','2026-09-18');check('explicit-medical-correction-keeps-historical-CIC-period-complete',$r['status']===200,$r['status']);
$frozenHash=normHash('2026-09');$r=correct('four','2026-09-14');check('historical-fixture-first-resolves-work-auditably',$r['status']===200,$r['status']);$r=book('manager','2026-10-12','2026-10-12','four');$frozenId=$r['body']['id'];approvedFixture($frozenId,'2026-09-14');
check('frozen-CO-projection',cell(overview('2026-09'),'four','2026-09-14')['status']==='Holiday');$xlsxCO=attendance('manager','xlsx',['month'=>'2026-09']);check('frozen-CO-export',$xlsxCO['status']===200,$xlsxCO['status']);
$r=correct('four','2026-09-14','BusinessTrip');check('manager-cannot-override-effective-CO-in-frozen-month',$r['status']===409,$r['status']);
$r=holiday('manager',$frozenId.'/cancel',command($frozenId,'cancel-frozen-'.$tag,['reason'=>'Historical fixture cancellation']));check('frozen-cancel-restores-existing-medical',$r['status']===200&&cell(overview('2026-09'),'four','2026-09-14')['status']==='MedicalLeave',$r['status']);
$r=correct('four','2026-09-14','BusinessTrip');check('audited-manager-correction-after-final-cancel',$r['status']===200,$r['status']);
$xlsxNew=attendance('manager','xlsx',['month'=>'2026-09']);$pdf=attendance('manager','pdf',['month'=>'2026-09']);check('exports-refresh-after-cancellation-and-correction',$xlsxNew['status']===200&&$xlsxCO['body']['content']!==$xlsxNew['body']['content']&&$pdf['status']===200);
$file='/feasibility/employee-stage5.xlsx';file_put_contents($file,base64_decode($xlsxNew['body']['content']));$sheet=PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getActiveSheet();check('XLSX-real-regression-contains-CO-CIC-medical',str_contains(json_encode($sheet->toArray()),'CM')&&str_contains(json_encode($sheet->toArray()),'CO'));
$data=overview('2026-09');check('frozen-flag-and-historical-norm-never-reset',$data['frozen']===true&&normHash('2026-09')===$frozenHash);$r=mark('four','2026-09-14');check('employee-still-cannot-correct-frozen-sheet',$r['status']===409,$r['status']);

// Concurrent request/attendance commands: Pending is not an effective absence; approval must recheck.
$payload=['dateStart'=>'2026-10-06','dateEnd'=>'2026-10-06','description'=>'Race booking','userId'=>$users['remote']->getId(),'managed'=>true,'idempotencyKey'=>'race-book-'.$tag];
$race=parallel([['manager','holiday','create',$payload],['remote','attendance','mark',['date'=>'2026-10-06','status'=>'AtWork']]]);check('HTTP-create-vs-mark-valid-serialized-outcomes',in_array($race[0]['status'],[200,409],true)&&$race[1]['status']===200,$race);
if($race[0]['status']===200){$r=holiday('manager',$race[0]['body']['id'].'/approve',command($race[0]['body']['id'],'race-approve-'.$tag));check('concurrent-Pending-cannot-approve-over-work',$r['status']===409&&str_contains($r['reason'],'attendance'),$r['status']);}else{check('concurrent-create-loser-has-no-reservation',balance('remote')===30.0);}

foreach([['2026-10-08','finalize-first'],['2026-10-09','mark-first']] as [$date,$order]){
    $r=book('manager',$date,$date,'six');check('race-fixture-Pending-'.$order,$r['status']===200,$r);$rid=$r['body']['id'];document($rid);$before=[balance('six'),ledgerCount('six')];
    $firstMode=$order==='finalize-first'?'finalize':'mark';$secondMode=$order==='finalize-first'?'mark':'finalize';
    $first=child('six',$firstMode,$date,$rid);ready($first);$second=child('six',$secondMode,$date,$rid);release($first);$a=finish($first);ready($second);release($second);$b=finish($second);
    check('deterministic-independent-connections-'.$order,$a['status']===200&&$b['status']===409,[$a,$b]);check('controlled-finalization-no-second-accounting-'.$order,[balance('six'),ledgerCount('six')]===$before);
    $request=state($rid);$record=$em->getRDBRepository('EmployeeAttendanceRecord')->where(['assignedUserId'=>$users['six']->getId(),'date'=>$date])->findOne();check('never-Approved-plus-work-'.$order,!(in_array($request['status'],['Approved','CancellationPending'],true)&&$record?->get('status')==='AtWork'));
    if($order==='finalize-first'){$r=holiday('manager',$rid.'/cancel',command($rid,'race-cancel-'.$tag,['reason'=>'Clear controlled fixture']));check('controlled-approved-fixture-cancellable',$r['status']===200,$r['status']);}
}

// Native month lock held through cancellation + notifications: no mixed old CO / new accounting projection.
$r=book('manager','2026-10-14','2026-10-14','remote');$reminderId=$r['body']['id'];approvedFixture($reminderId);check('active-absence-not-missing',cell(overview(),'remote','2026-10-14')['isMissing']===false);
$cancelChild=child('remote','cancel','2026-10-14',$reminderId);ready($cancelChild);
$multi=curl_multi_init();$h=curl_init('http://127.0.0.1/api/v1/EmployeeManagement/attendance/remind');curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>'{}',CURLOPT_HTTPHEADER=>['Content-Type: application/json','Espo-Authorization: '.base64_encode($users['manager']->get('userName').':'.$password)],CURLOPT_TIMEOUT=>90]);curl_multi_add_handle($multi,$h);curl_multi_exec($multi,$running);release($cancelChild);$cancelResult=finish($cancelChild);do{$s=curl_multi_exec($multi,$running);if($running){curl_multi_select($multi,1);}}while($running&&$s===CURLM_OK);$reminderCode=curl_getinfo($h,CURLINFO_HTTP_CODE);curl_multi_remove_handle($multi,$h);curl_close($h);curl_multi_close($multi);
check('concurrent-cancel-reminder-completes-without-deadlock',$cancelResult['status']===200&&$reminderCode===200,[$cancelResult,$reminderCode]);check('final-cancel-creates-real-missing-day',cell(overview(),'remote','2026-10-14')['isMissing']===true);
$notification=$em->getRDBRepository('Notification')->where(['userId'=>$users['remote']->getId()])->order('createdAt','DESC')->findOne();check('native-reminder-uses-post-cancel-missing-dates',str_contains((string)$notification?->get('message'),'14.10.2026'));
$r=http('remote','GET','Notification?maxSize=20');check('reminder-delivered-through-native-Notification-API',$r['status']===200&&str_contains($r['raw'],'14.10.2026'),$r['status']);
$count=ledgerCount('remote');$r=holiday('manager',$reminderId.'/cancel',['idempotencyKey'=>'child-cancel:'.$reminderId,'revision'=>(int)state($reminderId)['accountingRevision']-1,'reason'=>'Controlled concurrent cancellation']);check('cross-process-cancel-receipt-idempotent',$r['status']===200&&($r['body']['duplicate']??false)&&ledgerCount('remote')===$count,$r);

foreach(['admin','viewer'] as $kind){$r=book($kind,'2026-10-26','2026-10-26');check('native-ACL-business-create-'.$kind,$r['status']===403,$r['status']);$r=holiday($kind,$historyId.'/approve',command($historyId,'deny-'.$kind.'-'.$tag));check('native-ACL-business-approval-'.$kind,$r['status']===403,$r['status']);}
$r=http('six','GET','EmployeeHolidayRequest/'.$historyId);check('private-colleague-request-not-exposed',$r['status']===403&&!str_contains($r['raw'],'PRIVATE'),$r['status']);
foreach(['manager','four','admin'] as $kind){$r=http($kind,'PUT','EmployeeHolidayRequest/'.$historyId,['status'=>'Approved']);check('no-API-bypass-to-Approved-'.$kind,$r['status']===403,$r['status']);}
$a=$em->getRDBRepository('Attachment')->where(['parentType'=>'EmployeeHolidayRequest','parentId'=>$cycleId])->findOne();$r=http('six','GET','Attachment/'.$a->getId());check('private-colleague-document-not-exposed',$r['status']===403,$r['status']);
foreach(['four','six','cic','remote'] as $kind){$sum=0;foreach($em->getRDBRepository('EmployeeHolidayLedger')->where(['assignedUserId'=>$users[$kind]->getId()])->find() as $entry){$sum+=(float)$entry->get('delta');}check('ledger-equals-live-balance-'.$kind,$sum===balance($kind),[$sum,balance($kind)]);}
foreach($original as $type=>$count){check('original-data-unchanged-'.$type,$em->getRDBRepository($type)->count()===$count);}
// Browser fixtures, deliberately retained after API assertions; no real approval endpoint enabled.
$r=book('ui','2026-10-26','2026-10-26');$browserCycle=$r['body']['id'];approvedFixture($browserCycle);$r=holiday('ui',$browserCycle.'/requestCancellation',command($browserCycle,'browser-cancel-'.$tag,['reason'=>'PRIVATE browser cancellation']));check('browser-CancellationPending-fixture',$r['status']===200,$r['status']);
$r=book('manager','2026-10-19','2026-10-19','ui');$browserConflict=$r['body']['id'];$r=mark('ui','2026-10-19');check('browser-Pending-with-later-work-fixture',$r['status']===200,$r['status']);
$s5=['users'=>json_decode(file_get_contents('/feasibility/employee-stage5-state.json'),true)['users'],'today'=>'2026-10-20','browserCycle'=>$browserCycle,'browserConflict'=>$browserConflict];file_put_contents('/feasibility/employee-stage5-state.json',json_encode($s5,JSON_PRETTY_PRINT));
file_put_contents('/feasibility/employee-stage5-api-results.json',json_encode(['version'=>'0.5.0','results'=>$results,'assertions'=>count($results),'failures'=>$failures,'approvalFixturesOnly'=>true],JSON_PRETTY_PRINT));echo json_encode(['assertions'=>count($results),'failures'=>$failures]).PHP_EOL;exit($failures?1:0);
