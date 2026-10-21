<?php
declare(strict_types=1);
// Real native worker tests only in the explicitly isolated laboratory. No fault switches in the package.
if(getenv('EMPLOYEE_TEST_LAB')!=='employee-feasibility-20261020'){throw new RuntimeException('Isolated lab only.');}
chdir('/var/www/html');require 'bootstrap.php';$c=(new Espo\Core\Application())->getContainer();$em=$c->get('entityManager');$f=$c->get('injectableFactory');
if($c->get('config')->get('version')!=='10.0.1'||$c->get('config')->get('siteUrl')!=='http://127.0.0.1:18089'){throw new RuntimeException('Wrong environment.');}
$password=getenv('EMPLOYEE_TEST_PASSWORD')?:throw new RuntimeException('Password required.');$adminPassword=getenv('EMPLOYEE_TEST_ADMIN_PASSWORD')?:throw new RuntimeException('Admin password required.');
$admin=$em->getRDBRepository('User')->where(['userName'=>'admin'])->findOne();$c->getByClass(Espo\Core\ApplicationUser::class)->setUser($admin);
$path='/feasibility/employee-stage6-state.json';$phase=$argv[1]??'setup';$s=is_file($path)?json_decode(file_get_contents($path),true):[];$users=[];$results=[];$failures=0;$tag=uniqid('s6');
foreach($s['users']??[] as $kind=>$v){$users[$kind]=$em->getEntityById('User',$v['id']);}
function check(string $test,bool $ok,mixed $observed=null):void{global $results,$failures;$r=['test'=>$test,'status'=>$ok?'PASS':'FAIL','observed'=>$observed];$results[]=$r;if(!$ok){$failures++;}echo json_encode($r,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;}
function fresh(string $type,array $values):Espo\ORM\Entity{global $em;$e=$em->getNewEntity($type);$e->set($values);$em->saveEntity($e);return $e;}
function state(string $id):array{global $em;return json_decode(json_encode($em->getRDBRepository('EmployeeHolidayRequest')->where(['id'=>$id])->findOne()->getValueMap()),true);}
function http(string $kind,string $method,string $path,?array $data=null,bool $download=false):array{global $users,$password,$adminPassword;$name=$kind==='admin'?'admin':$users[$kind]->get('userName');$pw=$kind==='admin'?$adminPassword:$password;$h=curl_init('http://127.0.0.1/'.($download?'':'api/v1/').$path);$reason='';curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Espo-Authorization: '.base64_encode($name.':'.$pw)],CURLOPT_TIMEOUT=>90,CURLOPT_HEADERFUNCTION=>function($h,$line)use(&$reason){if(str_starts_with(strtolower($line),'x-status-reason:')){$reason=trim(substr($line,16));}return strlen($line);}]);if($data!==null){curl_setopt($h,CURLOPT_POSTFIELDS,json_encode($data));}$raw=curl_exec($h);if($raw===false){throw new RuntimeException(curl_error($h));}$code=curl_getinfo($h,CURLINFO_HTTP_CODE);curl_close($h);return ['status'=>$code,'body'=>json_decode($raw,true),'raw'=>$raw,'reason'=>$reason];}
function api(string $kind,string $op,?array $body=null):array{return http($kind,$body===null?'GET':'POST','EmployeeManagement/holiday/'.$op,$body);}
function command(string $id,string $key,array $more=[]):array{return ['idempotencyKey'=>$key,'revision'=>(int)state($id)['accountingRevision']]+$more;}
function book(string $kind,string $start,string $end):string{$r=api($kind,'create',['dateStart'=>$start,'dateEnd'=>$end,'description'=>'PRIVATE real Stage6 approval','idempotencyKey'=>uniqid('s6-book-')]);check('book-'.$kind.'-'.$start,$r['status']===200,$r['status']);if($r['status']!==200){throw new RuntimeException('Booking failed '.$r['reason']);}return $r['body']['id'];}
function accounting(string $kind):array{global $em,$users;$p=$em->getRDBRepository('EmployeeHolidayProfile')->where(['assignedUserId'=>$users[$kind]->getId()])->findOne();return ['balance'=>(float)$p->get('balance'),'ledger'=>$em->getRDBRepository('EmployeeHolidayLedger')->where(['profileId'=>$p->getId()])->count()];}
function waitState(string $id,string $wanted,int $seconds=45):array{$until=microtime(true)+$seconds;do{$r=state($id);if(($r['approvalState']??null)===$wanted){return $r;}usleep(150000);}while(microtime(true)<$until);return $r;}
function job(string $id):array{global $em;return (array)$em->getEntityById('Job',state($id)['approvalJobId'])->getValueMap();}
function waitJob(string $id,string $wanted,int $seconds=45):array{$until=microtime(true)+$seconds;do{$r=job($id);if($r['status']===$wanted){return $r;}usleep(200000);}while(microtime(true)<$until);return $r;}
function docs(string $id):array{global $em;$r=[];foreach($em->getRDBRepository('Attachment')->where(['parentType'=>'EmployeeHolidayRequest','parentId'=>$id])->order('name')->find() as $a){$r[]=(array)$a->getValueMap();}return $r;}
function trigger(string $id,string $mode):void{global $em;$em->getPDO()->exec('DROP TRIGGER IF EXISTS employee_stage6_fault');$condition='NEW.parent_id='.$em->getPDO()->quote($id)." AND NEW.name LIKE '%februarie%'";$effect=$mode==='fail'?"SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stage6 isolated second-document storage boundary failure'":"DO SLEEP(30)";$em->getPDO()->exec('CREATE TRIGGER employee_stage6_fault BEFORE INSERT ON attachment FOR EACH ROW BEGIN IF '.$condition.' THEN '.$effect.'; END IF; END');}
function parallelApprove(string $id,array $payload):array{global $users,$password;$multi=curl_multi_init();$handles=[];for($i=0;$i<2;$i++){$h=curl_init('http://127.0.0.1/api/v1/EmployeeManagement/holiday/'.$id.'/approve');curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Espo-Authorization: '.base64_encode($users['manager']->get('userName').':'.$password)],CURLOPT_TIMEOUT=>90]);curl_multi_add_handle($multi,$h);$handles[]=$h;}do{$status=curl_multi_exec($multi,$running);if($running){curl_multi_select($multi,1);}}while($running&&$status===CURLM_OK);$out=[];foreach($handles as $h){$out[]=['status'=>curl_getinfo($h,CURLINFO_HTTP_CODE),'body'=>json_decode(curl_multi_getcontent($h),true)];curl_multi_remove_handle($multi,$h);curl_close($h);}curl_multi_close($multi);return $out;}

if($phase==='setup'){
    $calendar=fresh('WorkingTimeCalendar',['name'=>'Stage6 native calendar '.$tag,'weekdays'=>[1,2,3,4,5],'timeRanges'=>[['08:00','14:00']],'timeZone'=>'Europe/Bucharest']);$hash=$f->create(Espo\Core\Utils\PasswordHash::class)->hash($password);
    foreach(['manager','employee','colleague','nodoc','noapproval','ui'] as $kind){$manager=$kind==='manager';$data=['EmployeeManagement'=>true,'EmployeeAdministration'=>$manager,'EmployeeHolidaySettings'=>$manager,'EmployeeHolidayProfileManagement'=>$manager,'EmployeeHolidayBalanceCorrection'=>$manager,'EmployeeHolidayBooking'=>true,'EmployeeHolidayRequestManagement'=>$manager,'EmployeeHolidayApproval'=>$manager,'EmployeeAttendance'=>true,'EmployeeAttendanceOverview'=>$manager,'EmployeeAttendanceManagement'=>$manager];foreach(['EmployeeHolidayRequest','EmployeeHolidayProfile','EmployeeHolidayLedger','EmployeeAttendanceRecord','EmployeeAttendanceWorkSchedule'] as $type){$data[$type]=(object)['read'=>$manager?'all':'own','create'=>'yes','edit'=>'all','delete'=>'all'];}$role=fresh('Role',['name'=>'Stage6 '.$tag.' '.$kind,'data'=>(object)$data,'exportPermission'=>'yes','fieldData'=>$kind==='nodoc'?(object)['EmployeeHolidayRequest'=>(object)['approvalDocuments'=>(object)['read'=>'no','edit'=>'no']]]:(object)[]]);$users[$kind]=fresh('User',['userName'=>'em_'.$tag.'_'.$kind,'firstName'=>'SnapshotFirst','lastName'=>'SnapshotLast '.$kind,'type'=>'regular','isActive'=>true,'password'=>$hash,'rolesIds'=>[$role->getId()],'workingTimeCalendarId'=>$calendar->getId()]);}
    $s=['users'=>array_map(fn($u)=>['id'=>$u->getId(),'name'=>$u->get('userName')],$users),'calendarId'=>$calendar->getId()];
    $writer=$f->create(Espo\Core\Utils\Config\ConfigWriter::class);$writer->setMultiple(['employeeHolidayApproversIds'=>[$users['manager']->getId()],'employeeHolidayApprovalBlock1Title'=>'Snapshot Director','employeeHolidayApprovalBlock1Name'=>'Snapshot Signature']);$writer->save();
    foreach($users as $kind=>$u){$r=http('manager','POST','EmployeeManagement/administration/profiles/bulkInitialize',['items'=>[['userId'=>$u->getId(),'annualEntitlement'=>21,'openingBalance'=>60,'nextResetDate'=>'2027-01-01','calendarColor'=>'#4F8A8B','idempotencyKey'=>'s6-init:'.$u->getId()]]]);check('profile-'.$kind,$r['status']===200,$r['status']);}
    $id=book('employee','2026-11-30','2026-12-02');$s['normal']=$id;$s['normalAccounting']=accounting('employee');$payload=command($id,'s6-approve-normal:'.$id);
    foreach(['employee','colleague','admin','noapproval'] as $kind){$r=api($kind,$id.'/approve',$payload);check('approval-ACL-'.$kind,$r['status']===403,$r['status']);}
    $race=parallelApprove($id,$payload);check('concurrent-identical-approval-receipt',array_column($race,'status')===[200,200]&&count(array_filter($race,fn($r)=>$r['body']['duplicate']))===1,$race);
    check('Processing-persisted-before-worker',state($id)['status']==='ApprovalProcessing'&&state($id)['approvalState']==='Pending');check('no-second-debit-on-initiation',accounting('employee')==$s['normalAccounting']);check('one-native-job-under-concurrent-initiation',$em->getRDBRepository('Job')->where(['targetId'=>$id,'className'=>Espo\Modules\EmployeeManagement\Jobs\GenerateHolidayApproval::class])->count()===1);
    $s['normalSnapshot']=state($id)['approvalSnapshot'];$s['approvedAt']=microtime(true);$r=api('manager',$id.'/retryApproval',command($id,'premature-retry:'.$id));check('cannot-retry-live-Pending-job',$r['status']===409,$r['status']);
    $own=book('manager','2026-11-05','2026-11-05');$s['self']=$own;$s['selfAccounting']=accounting('manager');$r=api('manager',$own.'/approve',command($own,'self:'.$own));check('manager-self-approval-initiates-native-job',$r['status']===200&&$r['body']['status']==='ApprovalProcessing',$r);
    $deny=book('nodoc','2026-11-06','2026-11-06');$r=api('manager',$deny.'/approve',command($deny,'nodoc:'.$deny));check('recipient-document-FLS-required',$r['status']===409&&state($deny)['status']==='Pending',$r['reason']);
    $r=http('manager','GET','Job');check('manager-no-global-Jobs-access',$r['status']===403,$r['status']);
    $before=$em->getRDBRepository('Job')->count();try{$em->getTransactionManager()->run(function()use($f){$f->create(Espo\Core\Job\JobSchedulerFactory::class)->create()->setClassName(Espo\Modules\EmployeeManagement\Jobs\GenerateHolidayApproval::class)->setGroup('s6-rollback-only')->setData(['requestId'=>'not-persisted'])->schedule();throw new RuntimeException('Controlled rollback');});}catch(RuntimeException){}check('native-scheduling-rolls-back-atomically',$em->getRDBRepository('Job')->count()===$before);
    // Freeze name/signatures/calendar at decision; subsequent changes must not alter the DOCX values.
    $users['employee']->set(['firstName'=>'ChangedLater','lastName'=>'ChangedLater']);$em->saveEntity($users['employee']);$writer->set('employeeHolidayApprovalBlock1Name','ChangedLater Signature');$writer->save();$calendar->set(['weekdays'=>[5],'timeRanges'=>[['10:00','14:00']]]);$em->saveEntity($calendar);
}
elseif($phase==='conflict-setup'){
    $calendar=$em->getEntityById('WorkingTimeCalendar',$s['calendarId']);$calendar->set('weekdays',[1,2,3,4,5]);$em->saveEntity($calendar);
    $id=book('ui','2026-10-20','2026-10-20');$s['attendanceConflict']=$id;$s['conflictAccounting']=accounting('ui');
    $r=api('manager',$id.'/approve',command($id,'processing-conflict:'.$id));check('real-Processing-before-attendance-conflict',$r['status']===200,$r['status']);
    $r=http('ui','POST','EmployeeManagement/attendance/mark',['date'=>'2026-10-20','status'=>'BusinessTrip']);check('Processing-is-not-yet-effective-CO',$r['status']===200,$r['status']);
    foreach(['withdraw','reject','cancel'] as $operation){$r=api($operation==='withdraw'?'ui':'manager',$id.'/'.$operation,command($id,'deny-'.$operation.':'.$id,$operation==='cancel'?['reason'=>'Must not bypass Processing']:[]));check('Processing-protected-from-'.$operation,$r['status']===409,$r['status']);}
    check('Processing-invalid-commands-no-accounting-effect',accounting('ui')==$s['conflictAccounting']);
}
elseif($phase==='normal'){
    $id=$s['normal'];$r=waitState($id,'Completed');check('native-worker-completes-approval',$r['status']==='Approved'&&$r['approvalState']==='Completed',[$r['status'],$r['approvalState'],job($id)['status']]);check('native-job-Success',waitJob($id,'Success')['status']==='Success');check('normal-and-self-no-second-accounting',accounting('employee')==$s['normalAccounting']&&accounting('manager')==$s['selfAccounting']);check('self-approval-completes',waitState($s['self'],'Completed')['status']==='Approved');$s['normalLatencySeconds']=microtime(true)-$s['approvedAt'];
    $files=docs($id);check('one-document-per-reserved-month',count($files)===count($s['normalSnapshot']['documents']),count($files));$s['normalIds']=array_column($files,'id');
    foreach($files as $a){$download=http('employee','GET','?entryPoint=download&id='.$a['id'],null,true);check('native-DOCX-download-'.$a['id'],$download['status']===200&&str_starts_with($download['raw'],'PK'),$download['status']);$tmp=tempnam('/tmp','s6-docx-');file_put_contents($tmp,$download['raw']);$zip=new ZipArchive();$ok=$zip->open($tmp)===true;$xml=$ok?$zip->getFromName('word/document.xml'):'';if($ok){$zip->close();}unlink($tmp);check('immutable-approval-name-signature-values-'.$a['id'],str_contains($xml,'SnapshotFirst')&&str_contains($xml,'Snapshot Signature')&&!str_contains($xml,'ChangedLater'));
        foreach(['colleague','noapproval'] as $kind){$deny=http($kind,'GET','?entryPoint=download&id='.$a['id'],null,true);check('document-download-ACL-'.$kind.'-'.$a['id'],$deny['status']===403,$deny['status']);}}
    $native=http('employee','GET','EmployeeHolidayRequest/'.$id);check('native-record-hides-operation-internals',$native['status']===200&&!str_contains($native['raw'],'approvalSnapshot')&&!str_contains($native['raw'],'approvalManifest')&&!str_contains($native['raw'],'approvalToken'),$native['status']);
    $r=api('employee','requests');$row=array_values(array_filter($r['body']['list'],fn($r)=>$r['id']===$id))[0];check('documents-visible-immediately-after-completion',count($row['documents'])===count($files));
    // Actual duplicate native Jobs, not direct calls to the worker method.
    $request=state($id);for($i=0;$i<2;$i++){$f->create(Espo\Core\Job\JobSchedulerFactory::class)->create()->setClassName(Espo\Modules\EmployeeManagement\Jobs\GenerateHolidayApproval::class)->setGroup('employee-holiday:'.$id)->setData(['requestId'=>$id,'operationKey'=>$request['approvalOperationKey'],'token'=>$request['approvalToken']])->schedule();}
    $r=api('manager',$id.'/approve',['idempotencyKey'=>'s6-approve-normal:'.$id,'revision'=>1]);check('approval-command-retry-is-receipt-not-new-debit',$r['status']===200&&$r['body']['duplicate']&&accounting('employee')==$s['normalAccounting'],$r['status']);
    $conflict=$s['attendanceConflict'];$r=waitState($conflict,'Failed');check('real-finalizer-rechecks-attendance-in-Processing',$r['status']==='ApprovalProcessing'&&$r['approvalState']==='Failed'&&str_contains($r['approvalError'],'attendance'),$r['approvalError']);waitJob($conflict,'Failed');
    $r=http('manager','POST','EmployeeManagement/attendance/managerMark',['userId'=>$users['ui']->getId(),'date'=>'2026-10-20','status'=>'MedicalLeave','reason'=>'Stage6 explicit audited conflict resolution']);check('explicit-correction-before-retry',$r['status']===200,$r['status']);
    $r=api('manager',$conflict.'/retryApproval',command($conflict,'resolved:'.$conflict));check('resolved-conflict-retry-same-reservation',$r['status']===200,$r['status']);check('resolved-conflict-Approved-after-document',waitState($conflict,'Completed')['status']==='Approved'&&accounting('ui')==$s['conflictAccounting']);
    // Real second-document DB failure, first tracked file must stay private and reusable.
    $failure=book('employee','2027-01-29','2027-02-02');$s['failure']=$failure;trigger($failure,'fail');$s['failureAccounting']=accounting('employee');$r=api('manager',$failure.'/approve',command($failure,'failure:'.$failure));check('failure-operation-started',$r['status']===200,$r['status']);
}
elseif($phase==='failure'){
    $id=$s['failure'];$r=waitState($id,'Failed');check('real-generation-error-is-visible-and-recoverable',$r['status']==='ApprovalProcessing'&&$r['approvalState']==='Failed'&&is_string($r['approvalError']),[$r['status'],$r['approvalState'],$r['approvalError']]);check('native-Job-Failed-not-false-Success',waitJob($id,'Failed')['status']==='Failed');check('failed-operation-keeps-reservation',accounting('employee')==$s['failureAccounting']);
    $files=docs($id);check('partial-result-one-tracked-document',count($files)===1,count($files));$s['partialId']=$files[0]['id']??'';$a=$files[0]??[];
    $r=http('employee','GET','?entryPoint=download&id='.($a['id']??''),null,true);check('partial-document-cannot-be-downloaded-even-by-owner',$r['status']===403,$r['status']);$r=http('manager','GET','Attachment/'.($a['id']??''));check('partial-attachment-metadata-denied-to-manager',$r['status']===403,$r['status']);
    $em->getPDO()->exec('DROP TRIGGER employee_stage6_fault');$r=api('employee',$id.'/retryApproval',command($id,'unauthorized-retry:'.$id));check('employee-cannot-retry-approval',$r['status']===403,$r['status']);
    $r=api('manager',$id.'/retryApproval',command($id,'retry:'.$id));check('controlled-retry-same-operation',$r['status']===200&&$r['body']['status']==='ApprovalProcessing',$r['status']);$r=api('manager',$id.'/retryApproval',command($id,'another-retry:'.$id));check('concurrent-second-retry-refused-while-job-live',$r['status']===409,$r['status']);
}
elseif($phase==='retried'){
    $id=$s['failure'];$r=waitState($id,'Completed');check('retry-completes-all-DOCX-before-Approved',$r['status']==='Approved'&&count(docs($id))===2,[$r['status'],count(docs($id))]);check('retry-reuses-first-attachment-id',in_array($s['partialId'],array_column(docs($id),'id'),true));check('retry-no-extra-accounting',accounting('employee')==$s['failureAccounting']);check('duplicate-native-Jobs-no-duplicate-documents',array_column(docs($s['normal']),'id')===$s['normalIds']);
    // Conflict arriving while ApprovalProcessing: hold the real worker on a native DB boundary.
    $crash=book('ui','2027-01-29','2027-02-02');$s['crash']=$crash;trigger($crash,'sleep');$s['crashAccounting']=accounting('ui');$r=api('manager',$crash.'/approve',command($crash,'crash:'.$crash));check('interruptible-real-job-scheduled',$r['status']===200,$r['status']);
    $writer=$f->create(Espo\Core\Utils\Config\ConfigWriter::class);$s['previousDetectionPeriod']=$c->get('config')->get('jobPeriodForNotExistingProcess',300);$writer->set('jobPeriodForNotExistingProcess',5);$writer->save();
}
elseif($phase==='crash-ready'){
    $id=$s['crash'];$until=microtime(true)+45;do{$r=state($id);if(($r['approvalState']??null)==='Processing'&&count(docs($id))===1){break;}usleep(100000);}while(microtime(true)<$until);
    check('real-worker-Processing-with-first-file-persisted',$r['approvalState']==='Processing'&&count(docs($id))===1);$s['crashPartialId']=docs($id)[0]['id']??'';check('native-job-really-Running-before-kill',job($id)['status']==='Running',job($id)['status']);
}
elseif($phase==='restart'){
    $id=$s['crash'];$em->getPDO()->exec('DROP TRIGGER IF EXISTS employee_stage6_fault');
    check('worker-interruption-did-not-publish-Approved',state($id)['status']==='ApprovalProcessing');check('interruption-keeps-tracked-first-file',count(docs($id))===1&&docs($id)[0]['id']===$s['crashPartialId']);check('interruption-keeps-balance-ledger',accounting('ui')==$s['crashAccounting']);
    $r=api('manager',$id.'/retryApproval',command($id,'too-early-after-crash:'.$id));check('Running-job-not-overridden-by-business-timeout',$r['status']===409,$r['status']);
}
elseif($phase==='recover'){
    $id=$s['crash'];$j=waitJob($id,'Failed',45);check('native-restart-detects-lost-process',$j['status']==='Failed',$j['status']);$r=api('ui','requests');$row=array_values(array_filter($r['body']['list'],fn($r)=>$r['id']===$id))[0];check('lost-worker-error-and-retry-visible-without-global-Jobs',$row['approvalState']==='Failed'&&$row['approvalRetryAvailable']&&is_string($row['approvalError']),$row['approvalState']);
    $r=api('manager',$id.'/retryApproval',command($id,'recover:'.$id));check('native-Job-reprogrammed-for-same-operation',$r['status']===200,$r['status']);
}
elseif($phase==='recovered'){
    $id=$s['crash'];$r=waitState($id,'Completed');check('restart-retry-finishes-Approved',$r['status']==='Approved',$r['status']);check('restart-retry-no-duplicate-documents',count(docs($id))===2&&in_array($s['crashPartialId'],array_column(docs($id),'id'),true));check('restart-retry-no-second-debit-ledger',accounting('ui')==$s['crashAccounting']);
    $writer=$f->create(Espo\Core\Utils\Config\ConfigWriter::class);$writer->set('jobPeriodForNotExistingProcess',$s['previousDetectionPeriod']);$writer->save();
    // Browser executes a real self approval, and retries a real failed worker operation.
    $s['browserSelf']=book('manager','2027-03-01','2027-03-02');
    $s['browserRetry']=book('manager','2027-01-29','2027-02-02');trigger($s['browserRetry'],'fail');$r=api('manager',$s['browserRetry'].'/approve',command($s['browserRetry'],'browser-fail:'.$s['browserRetry']));check('browser-real-failure-scheduled',$r['status']===200,$r['status']);
}
elseif($phase==='browser-ready'){
    $r=waitState($s['browserRetry'],'Failed');check('browser-real-failure-ready',$r['approvalState']==='Failed');waitJob($s['browserRetry'],'Failed');$em->getPDO()->exec('DROP TRIGGER employee_stage6_fault');
}
elseif($phase==='post'){
    $id=$s['normal'];$before=accounting('employee');$days=(int)state($id)['days'];$ids=array_column(docs($id),'id');
    $r=api('employee',$id.'/requestCancellation',command($id,'real-cancellation:'.$id,['reason'=>'Real approved operation cancellation']));check('real-Approved-to-CancellationPending',$r['status']===200&&$r['body']['status']==='CancellationPending',$r['status']);check('real-cancellation-pending-no-refund',accounting('employee')==$before);
    $payload=command($id,'real-final-cancellation:'.$id,['decision'=>'Cancelled']);$r=api('manager',$id.'/decideCancellation',$payload);check('real-document-approved-cancellation-refunds-once',$r['status']===200&&accounting('employee')['balance']===$before['balance']+$days&&accounting('employee')['ledger']===$before['ledger']+1,$r['status']);
    $r=api('manager',$id.'/decideCancellation',$payload);check('real-final-cancel-retry-no-second-refund',$r['status']===200&&$r['body']['duplicate']&&accounting('employee')['ledger']===$before['ledger']+1,$r['status']);check('real-cancellation-keeps-original-documents',array_column(docs($id),'id')===$ids);
    $r=http('employee','GET','?entryPoint=download&id='.$ids[0],null,true);check('cancelled-document-retained-private-history',$r['status']===200&&str_starts_with($r['raw'],'PK'),$r['status']);
    foreach(['employee','colleague'] as $kind){$r=http($kind,'PUT','Attachment/'.$ids[0],['name'=>'Unauthorized replacement']);check('native-attachment-mutation-denied-'.$kind,$r['status']===403,$r['status']);}
    $r=http('ui','POST','EmployeeManagement/attendance/mark',['date'=>'2026-10-20','status'=>'AtWork']);check('actual-Job-Approved-blocks-incompatible-attendance',$r['status']===409,$r['status']);
    foreach($users as $kind=>$u){$sum=0.0;foreach($em->getRDBRepository('EmployeeHolidayLedger')->where(['assignedUserId'=>$u->getId()])->find() as $l){$sum+=(float)$l->get('delta');}check('ledger-integrity-after-native-Jobs-'.$kind,$sum===accounting($kind)['balance'],[$sum,accounting($kind)['balance']]);}
    $r=api('manager','queue');$ownIds=array_map(fn($k)=>$s[$k],['normal','self','attendanceConflict','failure','crash','browserSelf','browserRetry']);$unfinished=array_filter($r['body']['list'],fn($row)=>in_array($row['id'],$ownIds,true)&&$row['status']==='ApprovalProcessing');check('private-queue-no-unfinished-current-cohort-operations',$r['status']===200&&count($unfinished)===0,$r['status']);
}
elseif($phase==='security'){
    $writer=$f->create(Espo\Core\Utils\Config\ConfigWriter::class);$s['restoreApprovers']=$c->get('config')->get('employeeHolidayApproversIds');$writer->set('employeeHolidayApproversIds',[$users['manager']->getId()]);$writer->save();
    $role=$em->getRDBRepository('User')->getRelation($users['manager'],'roles')->findOne();$savedFields=$role->get('fieldData');$role->set('fieldData',(object)['EmployeeHolidayRequest'=>(object)['approvalDocuments'=>(object)['read'=>'no','edit'=>'no']]]);$em->saveEntity($role);$f->create(Espo\Core\Acl\Cache\Clearer::class)->clearForUser($users['manager']);
    $id=$s['normal'];$r=api('manager',$id.'/decideCancellation',['idempotencyKey'=>'real-final-cancellation:'.$id,'revision'=>(int)state($id)['accountingRevision']-1,'decision'=>'Cancelled']);check('receipt-retry-respects-revoked-document-FLS',$r['status']===200&&$r['body']['duplicate']&&$r['body']['documents']===[],$r['status']);check('receipt-does-not-leak-previous-document-IDs',!str_contains($r['raw'],$s['normalIds'][0]));
    foreach(['manager','employee'] as $kind){$r=http($kind,'GET','EmployeeHolidayRequest/'.$id);check('internal-JSON-not-a-field-ACL-side-channel-'.$kind,$r['status']===200&&!str_contains($r['raw'],'operationReceipts')&&!str_contains($r['raw'],'calculationSnapshot')&&!str_contains($r['raw'],'approvalSnapshot')&&!str_contains($r['raw'],'approvalManifest'),$r['status']);}
    $r=http('manager','GET','?entryPoint=download&id='.$s['normalIds'][0],null,true);check('revoked-document-FLS-applies-to-native-download',$r['status']===403,$r['status']);
    $role->set('fieldData',$savedFields);$em->saveEntity($role);$f->create(Espo\Core\Acl\Cache\Clearer::class)->clearForUser($users['manager']);
    $self=state($s['self']);check('business-decider-not-system-worker',$self['decidedById']===$users['manager']->getId()&&$self['decidedAt']===$self['approvalSnapshot']['decidedAt']);check('technical-author-trace-is-system',$self['modifiedById']==='system',$self['modifiedById']);check('native-status-audit-includes-processing-and-approved',$em->getRDBRepository('Note')->where(['parentId'=>$s['self'],'type'=>'Update'])->count()>=2);
    $s['finalSmoke']=book('manager','2027-03-08','2027-03-09');$s['finalSmokeAccounting']=accounting('manager');$r=api('manager',$s['finalSmoke'].'/approve',command($s['finalSmoke'],'final-smoke:'.$s['finalSmoke']));check('final-package-real-approval-started',$r['status']===200,$r['status']);
}
elseif($phase==='smoke-complete'){
    $id=$s['finalSmoke'];$r=waitState($id,'Completed');check('final-package-real-job-Approved-after-files',$r['status']==='Approved'&&count(docs($id))===1,[$r['status'],count(docs($id))]);check('final-package-no-second-accounting',accounting('manager')==$s['finalSmokeAccounting']);$a=docs($id)[0];$r=http('manager','GET','?entryPoint=download&id='.$a['id'],null,true);check('final-package-native-private-download',$r['status']===200&&str_starts_with($r['raw'],'PK'),$r['status']);
    $writer=$f->create(Espo\Core\Utils\Config\ConfigWriter::class);$writer->set('employeeHolidayApproversIds',$s['restoreApprovers']);$writer->save();
}
elseif($phase==='browser-repeat'){
    // Repeat browser approval/retry on the final ZIP after security hardening; no runtime test endpoints.
    $writer=$f->create(Espo\Core\Utils\Config\ConfigWriter::class);$s['browserRestoreApprovers']=$c->get('config')->get('employeeHolidayApproversIds');$writer->set('employeeHolidayApproversIds',[$users['manager']->getId()]);$writer->save();
    $s['browserSelf']=book('manager','2027-04-05','2027-04-06');
    $s['browserRetry']=book('manager','2028-01-31','2028-02-02');trigger($s['browserRetry'],'fail');
    $r=api('manager',$s['browserRetry'].'/approve',command($s['browserRetry'],'browser-repeat:'.$s['browserRetry']));check('final-ZIP-browser-failure-scheduled',$r['status']===200,$r['status']);
}
elseif($phase==='browser-repeat-complete'){
    check('final-browser-operations-really-Approved',state($s['browserSelf'])['status']==='Approved'&&state($s['browserRetry'])['status']==='Approved');
    $writer=$f->create(Espo\Core\Utils\Config\ConfigWriter::class);$writer->set('employeeHolidayApproversIds',$s['browserRestoreApprovers']);$writer->save();
}
elseif($phase==='renderer-fail'){
    $writer=$f->create(Espo\Core\Utils\Config\ConfigWriter::class);$s['rendererRestoreApprovers']=$c->get('config')->get('employeeHolidayApproversIds');$writer->set('employeeHolidayApproversIds',[$users['manager']->getId()]);$writer->save();
    $id=book('colleague','2027-03-15','2027-03-16');$s['rendererFailure']=$id;$s['rendererAccounting']=accounting('colleague');$r=api('manager',$id.'/approve',command($id,'renderer-failure:'.$id));check('renderer-failure-operation-started',$r['status']===200,$r['status']);
    // Reversible fault only in the EmployeeManagement laboratory copy, never in original Holiday sources.
    $template='custom/Espo/Modules/EmployeeManagement/Resources/templates/holiday-request.docx';$backup='/feasibility/employee-stage6-template-backup.docx';if(is_file($backup)){throw new RuntimeException('Existing template backup requires recovery first.');}if(!rename($template,$backup)){throw new RuntimeException('Cannot create isolated template fault.');}
}
elseif($phase==='renderer-retry'){
    $id=$s['rendererFailure'];$r=waitState($id,'Failed');check('real-DOCX-template-failure-never-Approved',$r['status']==='ApprovalProcessing'&&$r['approvalState']==='Failed'&&count(docs($id))===0,[$r['status'],$r['approvalState']]);waitJob($id,'Failed');
    if(!rename('/feasibility/employee-stage6-template-backup.docx','custom/Espo/Modules/EmployeeManagement/Resources/templates/holiday-request.docx')){throw new RuntimeException('Template restore failed.');}
    $r=api('manager',$id.'/retryApproval',command($id,'renderer-retry:'.$id));check('renderer-failure-controlled-retry',$r['status']===200,$r['status']);
}
elseif($phase==='renderer-complete'){
    $id=$s['rendererFailure'];$r=waitState($id,'Completed');check('restored-original-template-completes-real-Job',$r['status']==='Approved'&&count(docs($id))===1,$r['status']);check('renderer-failure-retry-no-second-accounting',accounting('colleague')==$s['rendererAccounting']);
    $writer=$f->create(Espo\Core\Utils\Config\ConfigWriter::class);$writer->set('employeeHolidayApproversIds',$s['rendererRestoreApprovers']);$writer->save();
}
else{throw new RuntimeException('Unknown phase.');}
file_put_contents($path,json_encode($s,JSON_PRETTY_PRINT));file_put_contents('/feasibility/employee-stage6-'.$phase.'-results.json',json_encode(['version'=>'0.6.0','phase'=>$phase,'results'=>$results,'assertions'=>count($results),'failures'=>$failures],JSON_PRETTY_PRINT));echo json_encode(['phase'=>$phase,'assertions'=>count($results),'failures'=>$failures]).PHP_EOL;exit($failures?1:0);
