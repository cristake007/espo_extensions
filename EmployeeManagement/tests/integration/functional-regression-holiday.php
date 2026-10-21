<?php
declare(strict_types=1);
// Stage 6 regression: replace only the two obsolete Jobs-disabled probes with stale-revision probes; real approval is covered by stage-6.php.
// Synthetic data only, opt-in and exact environment validation before any write.
if (getenv('EMPLOYEE_TEST_LAB') !== 'employee-feasibility-20261020') { throw new RuntimeException('Isolated lab only.'); }
chdir('/var/www/html'); require 'bootstrap.php';
$c=(new Espo\Core\Application())->getContainer();$em=$c->get('entityManager');$factory=$c->get('injectableFactory');
if($c->get('config')->get('version')!=='10.0.1'||$c->get('config')->get('siteUrl')!=='http://127.0.0.1:18089')throw new RuntimeException('Wrong environment.');
$admin=$em->getRDBRepository('User')->where(['userName'=>'admin'])->findOne();$c->getByClass(Espo\Core\ApplicationUser::class)->setUser($admin);
$password=getenv('EMPLOYEE_TEST_PASSWORD')?:throw new RuntimeException('Synthetic password required.');
$adminPassword=getenv('EMPLOYEE_TEST_ADMIN_PASSWORD')?:throw new RuntimeException('Synthetic admin password required.');
$results=[];$failures=0;$tag=uniqid('s3');$users=[];
function check(string $test,bool $ok,mixed $observed=null):void{global $results,$failures;$r=['test'=>$test,'status'=>$ok?'PASS':'FAIL','observed'=>$observed];$results[]=$r;if(!$ok)$failures++;echo json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;}
function fresh(string $type,array $values):Espo\ORM\Entity{global $em;$r=$em->getNewEntity($type);$r->set($values);$em->saveEntity($r);return $r;}
function http(string $kind,string $method,string $path,?array $data=null):array{global $users,$password,$adminPassword;$name=$kind==='admin'?'admin':$users[$kind]->get('userName');$pw=$kind==='admin'?$adminPassword:$password;$ch=curl_init('http://127.0.0.1/api/v1/'.$path);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Espo-Authorization: '.base64_encode($name.':'.$pw)],CURLOPT_TIMEOUT=>120]);if($data!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($data));$raw=curl_exec($ch);if($raw===false)throw new RuntimeException(curl_error($ch));$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return ['status'=>$code,'body'=>json_decode($raw,true),'raw'=>$raw];}
function call(string $kind,string $path,array $data):array{return http($kind,'POST','EmployeeManagement/holiday/'.$path,$data);}
function balance(string $kind):float{global $users,$em;return(float)$em->getRDBRepository('EmployeeHolidayProfile')->where(['assignedUserId'=>$users[$kind]->getId()])->findOne()->get('balance');}
function booking(string $key,string $start='2026-11-09',string $end='2026-11-13'):array{return ['idempotencyKey'=>$key,'dateStart'=>$start,'dateEnd'=>$end,'description'=>'PRIVATE synthetic stage 3'];}
function command(array $record,string $key,array $extra=[]):array{return ['idempotencyKey'=>$key,'revision'=>(int)$record['accountingRevision']]+$extra;}
function requestState(string $id):array{global $em;$r=$em->getRDBRepository('EmployeeHolidayRequest')->where(['id'=>$id])->findOne();return ['id'=>$id,'accountingRevision'=>(int)$r->get('accountingRevision'),'status'=>$r->get('status')];}
function parallel(array $calls):array{global $users,$password;$m=curl_multi_init();$hs=[];foreach($calls as [$kind,$path,$body]){$ch=curl_init('http://127.0.0.1/api/v1/EmployeeManagement/holiday/'.$path);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Espo-Authorization: '.base64_encode($users[$kind]->get('userName').':'.$password)],CURLOPT_TIMEOUT=>120]);curl_multi_add_handle($m,$ch);$hs[]=$ch;}do{$s=curl_multi_exec($m,$running);if($running)curl_multi_select($m,1);}while($running&&$s===CURLM_OK);$out=[];foreach($hs as $ch){$out[]=['status'=>curl_getinfo($ch,CURLINFO_HTTP_CODE),'body'=>json_decode(curl_multi_getcontent($ch),true)];curl_multi_remove_handle($m,$ch);curl_close($ch);}curl_multi_close($m);return $out;}
$original=[];foreach(['AttendanceRecord','AttendanceWorkSchedule','HolidayRequest','HolidayProfile','HolidayLedger'] as $type)$original[$type]=$em->getRDBRepository($type)->count();
$calendar=fresh('WorkingTimeCalendar',['name'=>'Synthetic Stage3 '.$tag,'weekdays'=>[1,2,3,4,5],'timeRanges'=>[['08:00','14:00']],'timeZone'=>'Europe/Bucharest']);
$hash=$factory->create(Espo\Core\Utils\PasswordHash::class)->hash($password);
foreach(['manager','employee','colleague','noentry','nobook','uncfg','fls','weak','nowtc','inactive','zero','race','ui'] as $kind){
    $data=['EmployeeManagement'=>$kind!=='noentry','EmployeeAdministration'=>$kind==='manager','EmployeeHolidaySettings'=>$kind==='manager','EmployeeHolidayProfileManagement'=>$kind==='manager','EmployeeHolidayBalanceCorrection'=>$kind==='manager','EmployeeHolidayBooking'=>$kind!=='nobook','EmployeeHolidayRequestManagement'=>$kind==='manager','EmployeeHolidayApproval'=>in_array($kind,['manager','uncfg'])];
    foreach(['EmployeeHolidayRequest','EmployeeHolidayProfile','EmployeeHolidayLedger','EmployeeAttendanceRecord'] as $type)$data[$type]=(object)['read'=>in_array($kind,['manager','uncfg','fls'])?'all':'own','create'=>'yes','edit'=>'all','delete'=>'all'];
    $role=fresh('Role',['name'=>'Synthetic Stage3 '.$kind.' '.$tag,'data'=>(object)$data,'fieldData'=>$kind==='fls'?(object)['EmployeeHolidayRequest'=>(object)['description'=>(object)['read'=>'no','edit'=>'no']]]:(object)[]]);
    $users[$kind]=fresh('User',['userName'=>'em_'.$tag.'_'.$kind,'firstName'=>'Synthetic','lastName'=>'Stage3 '.$kind,'password'=>$hash,'type'=>'regular','isActive'=>$kind!=='inactive','rolesIds'=>[$role->getId()],'workingTimeCalendarId'=>$kind==='nowtc'?null:$calendar->getId()]);
}
$settings=['employeeHolidayAnnualEntitlementDays'=>21,'employeeHolidayResetDate'=>'2027-01-01','employeeHolidayAutomaticAnnualGrantEnabled'=>false,'employeeHolidayCarryOverLimitDays'=>90,'employeeHolidayNegativeBalanceLimitDays'=>-21,'employeeHolidayApprovalBlock1Title'=>'Synthetic Director','employeeHolidayApprovalBlock1Name'=>'Synthetic Manager','employeeHolidayApprovalBlock2Title'=>'','employeeHolidayApprovalBlock2Name'=>''];
$r=http('manager','PUT','EmployeeManagement/administration/holiday',$settings);check('native-authorized-manager-settings',$r['status']===200,$r);
foreach($users as $kind=>$user){if($kind==='inactive')continue;$r=http('manager','POST','EmployeeManagement/administration/profiles/bulkInitialize',['items'=>[['userId'=>$user->getId(),'annualEntitlement'=>21,'openingBalance'=>$kind==='zero'?-21:30,'nextResetDate'=>'2027-01-01','calendarColor'=>'#4F8A8B','idempotencyKey'=>'init-'.$user->getId()]]]);check('initialize-'.$kind,$r['status']===200,$r['status']);}
// Native technical configuration creates normative exceptions and personal absence; commands must not edit these.
$range=fresh('WorkingTimeRange',['name'=>'Synthetic Stage3 normative nonwork '.$tag,'dateStart'=>'2026-11-12','dateEnd'=>'2026-11-12','type'=>'Non-working','calendarsIds'=>[$calendar->getId()]]);
$personal=fresh('WorkingTimeRange',['name'=>'Synthetic Stage3 personal unavailable '.$tag,'dateStart'=>'2026-11-09','dateEnd'=>'2026-11-09','type'=>'Non-working','usersIds'=>[$users['employee']->getId()]]);
$public=fresh('ZileLibere',['name'=>'Synthetic Stage3 legal holiday '.$tag,'dateStart'=>'2026-11-11','countryCode'=>'RO']);
$nativeCounts=[];foreach(['WorkingTimeCalendar','WorkingTimeRange','ZileLibere'] as $type)$nativeCounts[$type]=$em->getRDBRepository($type)->count();
foreach(['admin','noentry','nobook','fls'] as $kind){$r=call($kind,'create',booking('deny-'.$tag.'-'.$kind));check('create-deny-'.$kind,$r['status']===403,$r['status']);}
$r=call('nowtc','create',booking('no-wtc-'.$tag));check('missing-explicit-WTC-no-fallback',$r['status']===400,$r);
$r=call('zero','create',booking('negative-limit-'.$tag));check('negative-limit-preserved',$r['status']===409,$r['status']);check('negative-limit-no-partial-request',$em->getRDBRepository('EmployeeHolidayRequest')->where(['assignedUserId'=>$users['zero']->getId()])->count()===0);
foreach([['dateStart'=>'2026-02-30'],['dateStart'=>'2026-11-15','dateEnd'=>'2026-11-13'],['dateEnd'=>'2028-11-13'],['dateStart'=>'2026-11-14','dateEnd'=>'2026-11-15'],['dateStart'=>'2026-10-01','dateEnd'=>'2026-10-02'],['status'=>'Approved'],['days'=>0],['approvalDocumentsIds'=>['fake']],['description'=>['bad']],['managed'=>'true'],['dateStart'=>123]] as $i=>$change){$r=call('employee','create',array_replace(booking('invalid-'.$tag.'-'.$i),$change));check('validation-'.$i,$r['status']===400,$r['status']);}
$payload=booking('booking-'.$tag);$r=call('employee','create',$payload);check('create-native-WTC-Zile-request',$r['status']===200,$r);
if($r['status']!==200){file_put_contents('/feasibility/employee-stage3-api-results.json',json_encode(['results'=>$results,'assertions'=>count($results),'failures'=>$failures],JSON_PRETTY_PRINT));exit(1);}
$record=$r['body'];$id=$record['id'];check('WTC-norm-not-personal-availability',$record['days']===3,$record['days']);check('reservation-debits-once',balance('employee')===27.0,balance('employee'));
$saved=$em->getEntityById('EmployeeHolidayRequest',$id);$snapshot=json_encode($saved->get('calculationSnapshot'));check('immutable-snapshot-has-days-hours-months',str_contains($snapshot,'2026-11-09')&&str_contains($snapshot,'monthlyDays')&&str_contains($snapshot,'6'),$saved->get('calculationSnapshot'));
$r=call('employee','create',$payload);check('create-retry-one-reservation',$r['status']===200&&($r['body']['duplicate']??false)&&balance('employee')===27.0,$r);
$r=http('employee','GET','EmployeeManagement/holiday/balance');check('original-balance-pending-semantics',$r['status']===200&&$r['body']['pendingDays']==3&&$r['body']['availableDays']==30,$r['body']);
$r=call('employee','create',array_replace($payload,['description'=>'changed']));check('create-key-payload-conflict',$r['status']===409,$r['status']);
$r=call('employee','create',booking('overlap-'.$tag));check('overlap-active-request',$r['status']===409,$r['status']);
foreach(['colleague','admin','fls'] as $kind){$r=call($kind,$id.'/withdraw',command($record,'deny-withdraw-'.$kind.'-'.$tag));check('request-command-privacy-'.$kind,$r['status']===403,$r['status']);}
$r=http('colleague','GET','EmployeeHolidayRequest/'.$id);check('native-record-private',$r['status']===403&&!str_contains($r['raw'],'PRIVATE synthetic'),$r['status']);
$r=http('colleague','GET','EmployeeManagement/holiday/requests');check('own-query-no-colleague-data',$r['status']===200&&$r['body']['total']===0,$r['status']);
$r=http('fls','GET','EmployeeManagement/holiday/queue');check('field-ACL-custom-query-no-bypass',$r['status']===403,$r['status']);
foreach(['employee','admin'] as $kind){$r=call($kind,$id.'/reject',command($record,'reject-deny-'.$tag.'-'.$kind));check('reject-native-authority-'.$kind,$r['status']===403,$r['status']);}
// A native approver omitted from the obsolete routing list is now authorized, including for self.
$auto=call('uncfg','create',booking('auto-role-'.$tag,'2026-11-26','2026-11-26'));check('native-role-without-routing-create',$auto['status']===200,$auto['status']);
$r=call('uncfg',$auto['body']['id'].'/reject',command($auto['body'],'auto-role-reject-'.$tag));check('native-role-without-routing-self-decision',$r['status']===200,$r['status']);
$r=call('manager',$id.'/approve',array_replace(command($record,'approval-deferred-'.$tag),['revision'=>$record['accountingRevision']+1]));check('stale-approval-preserves-Pending-and-reservation',$r['status']===409&&requestState($id)['status']==='Pending'&&balance('employee')===27.0,$r);
// Later technical WTC changes cannot change a booked snapshot or a description-only edit.
$calendar->set('timeRanges',[['08:00','16:00']]);$em->saveEntity($calendar);
$update=command($record,'description-'.$tag,['dateStart'=>'2026-11-09','dateEnd'=>'2026-11-13','description'=>'PRIVATE updated note']);
$r=call('employee',$id.'/update',$update);check('description-edit-preserves-reservation',$r['status']===200&&$r['body']['days']===3&&balance('employee')===27.0,$r);
$after=$em->getRDBRepository('EmployeeHolidayRequest')->where(['id'=>$id])->findOne();check('snapshot-stable-after-WTC-edit',json_encode($after->get('calculationSnapshot'))===$snapshot);
$r=call('employee',$id.'/update',$update);check('edit-retry-idempotent',$r['status']===200&&($r['body']['duplicate']??false),$r['status']);
$r=call('employee',$id.'/update',array_replace($update,['description'=>'different retry']));check('command-idempotency-payload-conflict',$r['status']===409,$r['status']);
$r=call('employee',$id.'/withdraw',command($record,'stale-'.$tag));check('stale-revision-blocked',$r['status']===409,$r['status']);
$change=command(requestState($id),'extend-'.$tag,['dateStart'=>'2026-11-09','dateEnd'=>'2026-11-16','description'=>'PRIVATE extended']);$r=call('employee',$id.'/update',$change);check('date-edit-reconciles-delta',$r['status']===200&&$r['body']['days']===4&&balance('employee')===26.0,$r);
$withdraw=command(requestState($id),'withdraw-'.$tag);$r=call('employee',$id.'/withdraw',$withdraw);check('withdraw-restores-and-retains-history',$r['status']===200&&$r['body']['status']==='Cancelled'&&balance('employee')===30.0,$r);
$r=call('employee',$id.'/withdraw',$withdraw);check('withdraw-retry-no-double-refund',$r['status']===200&&($r['body']['duplicate']??false)&&balance('employee')===30.0,$r['status']);
$r=call('employee','create',booking('after-cancel-'.$tag));check('cancelled-no-longer-blocks-interval',$r['status']===200,$r['status']);$second=$r['body'];
$r=call('manager',$second['id'].'/reject',command($second,'reject-'.$tag));check('reject-refunds-atomic',$r['status']===200&&balance('employee')===30.0,$r);
$r=call('employee','create',booking('after-reject-'.$tag));check('rejected-no-longer-blocks-interval',$r['status']===200,$r['status']);$third=$r['body'];
$r=call('employee',$third['id'].'/requestCancellation',command($third,'not-approved-'.$tag,['reason'=>'Synthetic']));check('Pending-cannot-request-approved-cancellation',$r['status']===409,$r['status']);
// Approved fixture only: no application approval is simulated or counted as implemented.
$approved=$em->getRDBRepository('EmployeeHolidayRequest')->where(['id'=>$third['id']])->findOne();
$attachment=$em->getNewEntity('Attachment');
$attachment->setName('synthetic-approval.docx')->setType('application/vnd.openxmlformats-officedocument.wordprocessingml.document')
    ->setRole(Espo\Entities\Attachment::ROLE_ATTACHMENT)->setParent($approved)->setTargetField('approvalDocuments');
$em->saveEntity($attachment);
$factory->create(Espo\Core\FileStorage\Manager::class)->putContents($attachment,file_get_contents('custom/Espo/Modules/EmployeeManagement/Resources/templates/holiday-request.docx'));
$approved->set(['status'=>'Approved','approvalDocumentsIds'=>[$attachment->getId()]]);$em->saveEntity($approved);
$r=call('employee',$third['id'].'/requestCancellation',command(requestState($third['id']),'empty-reason-'.$tag,['reason'=>'']));check('original-cancellation-reason-required',$r['status']===400&&balance('employee')===27.0,$r['status']);
$requestCancel=command(requestState($third['id']),'ask-cancel-'.$tag,['reason'=>'PRIVATE synthetic cancellation']);$r=call('employee',$third['id'].'/requestCancellation',$requestCancel);check('cancellation-pending-no-premature-refund',$r['status']===200&&$r['body']['status']==='CancellationPending'&&balance('employee')===27.0,$r);
$r=call('employee','create',booking('pending-cancel-overlap-'.$tag));check('CancellationPending-still-reserves',$r['status']===409,$r['status']);
$r=call('manager',$third['id'].'/decideCancellation',command(requestState($third['id']),'deny-cancel-'.$tag,['decision'=>'Approved']));check('decline-cancellation-retains-existing-documented-approval',$r['status']===200&&$r['body']['status']==='Approved'&&balance('employee')===27.0,$r);
$cancel=command(requestState($third['id']),'direct-cancel-'.$tag,['reason'=>'Manager synthetic cancellation']);$r=call('manager',$third['id'].'/cancel',$cancel);check('manager-cancel-approved-restores',$r['status']===200&&balance('employee')===30.0,$r);
$r=call('manager',$third['id'].'/cancel',$cancel);check('approved-cancel-retry-once',$r['status']===200&&($r['body']['duplicate']??false)&&balance('employee')===30.0,$r['status']);
// Explicit final cancellation decision (not only the direct manager cancellation branch).
$r=call('employee','create',booking('cancellation-final-'.$tag,'2026-12-01','2026-12-01'));$finalCancellation=$r['body'];
$approved=$em->getRDBRepository('EmployeeHolidayRequest')->where(['id'=>$finalCancellation['id']])->findOne();$approved->set('status','Approved');$em->saveEntity($approved);
$attachment=$em->getNewEntity('Attachment');$attachment->setName('synthetic-final-approval.docx')->setType('application/vnd.openxmlformats-officedocument.wordprocessingml.document')->setRole(Espo\Entities\Attachment::ROLE_ATTACHMENT)->setParent($approved)->setTargetField('approvalDocuments');$em->saveEntity($attachment);
$factory->create(Espo\Core\FileStorage\Manager::class)->putContents($attachment,file_get_contents('custom/Espo/Modules/EmployeeManagement/Resources/templates/holiday-request.docx'));
$r=call('employee',$finalCancellation['id'].'/requestCancellation',command($finalCancellation,'request-final-cancel-'.$tag,['reason'=>'Synthetic cancellation decision']));check('final-cancel-request-keeps-debit',$r['status']===200&&balance('employee')===29.0,$r['status']);
$decision=command(requestState($finalCancellation['id']),'accept-final-cancel-'.$tag,['decision'=>'Cancelled']);$r=call('manager',$finalCancellation['id'].'/decideCancellation',$decision);check('accept-cancellation-refunds-once',$r['status']===200&&$r['body']['status']==='Cancelled'&&balance('employee')===30.0,$r['status']);
$r=call('manager',$finalCancellation['id'].'/decideCancellation',$decision);check('accept-cancellation-retry-single-refund',$r['status']===200&&($r['body']['duplicate']??false)&&balance('employee')===30.0,$r['status']);
// Invalid historical fixture: cannot restore Approved if its document is absent.
$r=call('employee','create',booking('missing-doc-cancel-'.$tag,'2026-12-03','2026-12-03'));$missingDoc=$r['body'];$entity=$em->getRDBRepository('EmployeeHolidayRequest')->where(['id'=>$missingDoc['id']])->findOne();$entity->set('status','CancellationPending');$em->saveEntity($entity);
$r=call('manager',$missingDoc['id'].'/decideCancellation',command($missingDoc,'missing-doc-restore-'.$tag,['decision'=>'Approved']));check('cannot-restore-Approved-without-file',$r['status']===409&&requestState($missingDoc['id'])['status']==='CancellationPending'&&balance('employee')===29.0,$r['status']);
$r=call('manager',$missingDoc['id'].'/decideCancellation',command($missingDoc,'missing-doc-cancel-'.$tag,['decision'=>'Cancelled']));check('invalid-historical-approval-can-be-cancelled',$r['status']===200&&balance('employee')===30.0,$r['status']);
$r=call('manager','create',booking('managed-admin-'.$tag)+['userId'=>$admin->getId(),'managed'=>true]);check('admin-beneficiary-excluded',$r['status']===400,$r['status']);
$r=call('manager','create',booking('managed-inactive-'.$tag)+['userId'=>$users['inactive']->getId(),'managed'=>true]);check('inactive-beneficiary-excluded',$r['status']===400,$r['status']);
$r=call('employee','create',booking('employee-target-'.$tag)+['userId'=>$users['colleague']->getId()]);check('employee-cannot-book-colleague',$r['status']===403,$r['status']);
$r=call('manager','create',booking('self-'.$tag));check('manager-self-request-supported',$r['status']===200,$r);$self=$r['body'];
$r=call('manager',$self['id'].'/approve',array_replace(command($self,'self-approve-'.$tag),['revision'=>$self['accountingRevision']+1]));check('stale-self-approval-preserves-Pending',$r['status']===409&&requestState($self['id'])['status']==='Pending',$r['status']);
$r=call('manager',$self['id'].'/reject',command($self,'self-reject-'.$tag));check('manager-self-decision-no-new-prohibition',$r['status']===200,$r['status']);
$r=call('manager','create',booking('managed-current-'.$tag,date('Y-m-01'),date('Y-m-02'))+['userId'=>$users['colleague']->getId(),'managed'=>true]);check('managed-booking-current-month-past-preserved',$r['status']===200,$r);
$r=call('manager','create',booking('managed-old-'.$tag,'2026-09-01','2026-09-02')+['userId'=>$users['colleague']->getId(),'managed'=>true]);check('managed-previous-month-blocked',$r['status']===400,$r['status']);
$r=http('manager','GET','EmployeeManagement/holiday/employees');$candidateIds=array_column($r['body']['list']??[],'id');check('managed-selector-native-query-and-eligibility',$r['status']===200&&in_array($users['colleague']->getId(),$candidateIds,true)&&!in_array($admin->getId(),$candidateIds,true)&&!in_array($users['inactive']->getId(),$candidateIds,true),$r['status']);
$r=http('employee','GET','EmployeeManagement/holiday/employees');check('managed-selector-denies-employee',$r['status']===403,$r['status']);
// Original correction and history methods, authorized by new native scope and atomic ledger.
$profile=$em->getRDBRepository('EmployeeHolidayProfile')->where(['assignedUserId'=>$users['employee']->getId()])->findOne();$correction=['profileId'=>$profile->getId(),'delta'=>2.5,'reason'=>'Synthetic correction','idempotencyKey'=>'correction-'.$tag];
foreach(['employee','admin','uncfg'] as $kind){$r=http($kind,'POST','EmployeeManagement/administration/profiles/correct',$correction);check('correction-denied-'.$kind,$r['status']===403,$r['status']);}
$r=http('manager','POST','EmployeeManagement/administration/profiles/correct',$correction);check('original-correction-reused',$r['status']===200&&balance('employee')===32.5,$r);
$r=http('manager','POST','EmployeeManagement/administration/profiles/correct',$correction);check('correction-retry-single-ledger',$r['status']===200&&($r['body']['duplicate']??false)&&balance('employee')===32.5,$r['status']);
$r=http('manager','POST','EmployeeManagement/administration/profiles/correct',array_replace($correction,['delta'=>5]));check('correction-payload-conflict',$r['status']===409,$r['status']);
$r=http('manager','POST','EmployeeManagement/administration/profiles/correct',array_replace($correction,['expectedBalance'=>30,'idempotencyKey'=>'stale-correction-'.$tag]));check('stale-target-balance-correction-blocked',$r['status']===409&&balance('employee')===32.5,$r['status']);
foreach([['delta'=>'1e999'],['delta'=>0],['reason'=>''],['actorId'=>$admin->getId()]] as $invalid){$r=http('manager','POST','EmployeeManagement/administration/profiles/correct',array_replace($correction,$invalid,['idempotencyKey'=>'invalid-correct-'.uniqid()]));check('correction-invalid-'.implode(',',array_keys($invalid)),$r['status']===400,$r['status']);}
$r=http('manager','POST','EmployeeManagement/administration/profiles/corrections',['profileId'=>$profile->getId()]);check('original-history-reused',$r['status']===200&&count($r['body']['list'])===1&&$r['body']['list'][0]['delta']===2.5,$r);
// Processing is a future worker state fixture, not an approval implementation. Reservation must remain intact.
$r=call('weak','create',booking('processing-fixture-'.$tag));$processing=$r['body'];$entity=$em->getRDBRepository('EmployeeHolidayRequest')->where(['id'=>$processing['id']])->findOne();$entity->set('status','ApprovalProcessing');$em->saveEntity($entity);
$r=http('weak','GET','EmployeeManagement/holiday/balance');check('Processing-retains-pending-reservation',$r['status']===200&&$r['body']['pendingDays']==3&&$r['body']['balance']==27,$r['body']);
foreach(['withdraw','reject','update'] as $op){$r=call($op==='reject'?'manager':'weak',$processing['id'].'/'.$op,command($processing,'processing-deny-'.$op.'-'.$tag,$op==='update'?['dateStart'=>'2026-11-09','dateEnd'=>'2026-11-16']:[]));check('Processing-command-blocked-'.$op,$r['status']===409&&balance('weak')===27.0,$r['status']);}
// Deliberate native unique-key failure after the balance save demonstrates full DB rollback, not only validation.
$faultKey='rollback-'.$tag;$accountingKey=hash('sha256',$users['manager']->getId().':'.$faultKey);
$faultProfile=$em->getRDBRepository('EmployeeHolidayProfile')->where(['assignedUserId'=>$users['nobook']->getId()])->findOne();
$faultLedger=fresh('EmployeeHolidayLedger',['name'=>'Synthetic collision fixture','profileId'=>$faultProfile->getId(),'assignedUserId'=>$users['nobook']->getId(),'type'=>'holidayBooked','delta'=>0,'balanceBefore'=>30,'balanceAfter'=>30,'idempotencyKey'=>'holiday-booked:'.$accountingKey,'actorId'=>$users['manager']->getId()]);
$beforeCount=$em->getRDBRepository('EmployeeHolidayLedger')->count();
$r=call('manager','create',booking($faultKey)+['userId'=>$users['nobook']->getId(),'managed'=>true]);check('DB-collision-translated-conflict',$r['status']===409,$r['status']);
check('DB-failure-rolls-back-profile',balance('nobook')===30.0,balance('nobook'));
check('DB-failure-no-partial-request',$em->getRDBRepository('EmployeeHolidayRequest')->where(['accountingKey'=>$accountingKey])->count()===0);
check('DB-failure-no-extra-ledger',$em->getRDBRepository('EmployeeHolidayLedger')->count()===$beforeCount);
// Pending adjustment beyond the negative limit rolls back dates, revision, snapshot and balance together.
$r=call('colleague','create',booking('large-adjust-'.$tag,'2026-12-07','2026-12-08'));$adjustable=$r['body'];$beforeBalance=balance('colleague');
$r=call('colleague',$adjustable['id'].'/update',command($adjustable,'too-large-adjust-'.$tag,['dateStart'=>'2026-12-07','dateEnd'=>'2027-06-30']));
check('adjustment-negative-limit-rollback',$r['status']===409&&balance('colleague')===$beforeBalance&&requestState($adjustable['id'])['accountingRevision']===1,$r['status']);
// Two real parallel requests with same create command.
$racePayload=booking('race-'.$tag);$race=parallel([['race','create',$racePayload],['race','create',$racePayload]]);check('parallel-identical-create',array_column($race,'status')===[200,200],$race);check('parallel-create-one-duplicate',count(array_filter($race,fn($r)=>($r['body']['duplicate']??false)===true))===1);check('parallel-create-one-debit',balance('race')===27.0,balance('race'));
$raceId=$race[0]['body']['id'];$withdraw1=command(requestState($raceId),'race-withdraw-'.$tag);$withdraw2=command(requestState($raceId),'race-other-'.$tag);
$race=parallel([['race',$raceId.'/withdraw',$withdraw1],['race',$raceId.'/withdraw',$withdraw2]]);$codes=array_column($race,'status');sort($codes);check('parallel-different-withdraw-stale-conflict',$codes===[200,409],$race);check('parallel-no-double-refund',balance('race')===30.0,balance('race'));
// Distinct simultaneous bookings overlap: one transaction must lose under the beneficiary lock.
$race=parallel([['race','create',booking('race-a-'.$tag)],['race','create',booking('race-b-'.$tag)]]);$codes=array_column($race,'status');sort($codes);check('parallel-overlap-one-winner',$codes===[200,409],$race);check('parallel-overlap-one-reservation',balance('race')===27.0,balance('race'));
$ledger=$em->getRDBRepository('EmployeeHolidayLedger')->where(['profileId'=>$profile->getId()])->order('createdAt')->find();$delta=0.0;foreach($ledger as $entry)$delta+=(float)$entry->get('delta');check('ledger-sum-equals-balance',$delta===balance('employee'),[$delta,balance('employee')]);
foreach(['admin','manager','employee'] as $kind){foreach([['PUT',['status'=>'Approved']],['DELETE',[]]] as [$method,$payload]){$r=http($kind,$method,'EmployeeHolidayRequest/'.$id,$payload);check('generic-mutation-still-blocked-'.$kind.'-'.$method,$r['status']===403,$r['status']);}}
foreach($original as $type=>$count)check('original-retained-'.$type,$em->getRDBRepository($type)->count()===$count);
foreach($nativeCounts as $type=>$count)check('commands-did-not-create-calendar-data-'.$type,$em->getRDBRepository($type)->count()===$count);
file_put_contents('/feasibility/employee-stage3-state.json',json_encode(['users'=>array_map(fn($u)=>['id'=>$u->getId(),'name'=>$u->get('userName')],$users),'calendarId'=>$calendar->getId(),'profileId'=>$profile->getId()],JSON_PRETTY_PRINT));
file_put_contents('/feasibility/employee-stage3-api-results.json',json_encode(['results'=>$results,'assertions'=>count($results),'failures'=>$failures,'version'=>'0.3.0'],JSON_PRETTY_PRINT));
echo json_encode(['assertions'=>count($results),'failures'=>$failures]).PHP_EOL;exit($failures?1:0);
