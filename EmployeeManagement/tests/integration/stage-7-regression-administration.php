<?php
// Stage7: bool true is now a supported explicit opt-in; retain invalid-type coverage with string true.
declare(strict_types=1);
if (getenv('EMPLOYEE_TEST_LAB') !== 'employee-feasibility-20261020') { throw new RuntimeException('Isolated lab only.'); }
chdir('/var/www/html'); require 'bootstrap.php';
$c=(new Espo\Core\Application())->getContainer(); $em=$c->get('entityManager'); $factory=$c->get('injectableFactory');
if ($c->get('config')->get('version')!=='10.0.1' || $c->get('config')->get('siteUrl')!=='http://127.0.0.1:18089') { throw new RuntimeException('Wrong environment.'); }
$admin=$em->getRDBRepository('User')->where(['userName'=>'admin'])->findOne();
$c->getByClass(Espo\Core\ApplicationUser::class)->setUser($admin);
$password=getenv('EMPLOYEE_TEST_PASSWORD') ?: throw new RuntimeException('Password required.');
$adminPassword=getenv('EMPLOYEE_TEST_ADMIN_PASSWORD') ?: throw new RuntimeException('Admin password required.');
$results=[]; $failures=0;
function check(string $test,bool $ok,mixed $observed=null):void {global $results,$failures;$r=['test'=>$test,'status'=>$ok?'PASS':'FAIL','observed'=>$observed];$results[]=$r;if(!$ok)$failures++;echo json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;}
function fixture(string $type,array $where,array $values):Espo\ORM\Entity {global $em;$r=$em->getRDBRepository($type)->where($where)->findOne()??$em->getNewEntity($type);$r->set($values);$em->saveEntity($r);return $r;}
function http(string $name,string $method,string $path,?array $data=null):array {
    global $password,$adminPassword;$pw=$name==='admin'?$adminPassword:$password;$ch=curl_init('http://127.0.0.1/api/v1/'.$path);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_USERPWD=>$name.':'.$pw,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Espo-Authorization: '.base64_encode($name.':'.$pw)],CURLOPT_TIMEOUT=>30]);
    if($data!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($data));$raw=curl_exec($ch);if($raw===false)throw new RuntimeException(curl_error($ch));$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return ['status'=>$code,'body'=>json_decode($raw,true),'raw'=>$raw];
}
$original=[];
foreach(['AttendanceRecord','AttendanceWorkSchedule','HolidayRequest','HolidayProfile','HolidayLedger','WorkingTimeCalendar','WorkingTimeRange'] as $type)$original[$type]=$em->getRDBRepository($type)->count();
$hash=$factory->create(Espo\Core\Utils\PasswordHash::class)->hash($password);
$scopes=['EmployeeAttendanceSettings','EmployeeHolidaySettings','EmployeeHolidayProfileManagement','EmployeeHolidayApproval'];
$kinds=['manager','attendance','holiday','profiles','entry','noentry','weak','employee','fls','approver','target','target2','zero','inactive'];$users=[];
foreach($kinds as $kind){
    $data=['EmployeeManagement'=>true,'EmployeeAdministration'=>!in_array($kind,['noentry','employee','approver','target','target2','inactive'],true)];
    foreach($scopes as $scope)$data[$scope]=$kind==='manager'||($scope==='EmployeeAttendanceSettings'&&in_array($kind,['attendance','noentry']))||($scope==='EmployeeHolidaySettings'&&$kind==='holiday')||($scope==='EmployeeHolidayProfileManagement'&&in_array($kind,['profiles','weak','fls']))||($scope==='EmployeeHolidayApproval'&&$kind==='approver');
    foreach(['EmployeeHolidayProfile','EmployeeHolidayLedger','EmployeeHolidayRequest','EmployeeAttendanceRecord'] as $type)$data[$type]=(object)['create'=>'yes','read'=>in_array($kind,['manager','profiles','fls'],true)?'all':'own','edit'=>'all','delete'=>'all'];
    $role=fixture('Role',['name'=>'EM stage 2 '.$kind],['name'=>'EM stage 2 '.$kind,'data'=>(object)$data,
        'fieldData'=>$kind==='fls'?(object)['EmployeeHolidayProfile'=>(object)['balance'=>(object)['read'=>'no','edit'=>'no']]]:(object)[]]);
    $userName='em_stage2_'.$kind.(in_array($kind,['target2','zero'],true)?'_'.uniqid():'');
    $users[$kind]=fixture('User',['userName'=>$userName],['userName'=>$userName,'firstName'=>'Synthetic','lastName'=>'Stage2 '.$kind,'type'=>'regular','isActive'=>$kind!=='inactive','password'=>$hash,'rolesIds'=>[$role->getId()]]);
}
$prefix='EmployeeManagement/administration/';
foreach(['manager'=>['attendance','holiday','profiles'],'attendance'=>['attendance'],'holiday'=>['holiday'],'profiles'=>['profiles'],'entry'=>[],'noentry'=>[],'weak'=>[],'fls'=>[],'employee'=>[],'admin'=>[]] as $kind=>$allowed){
    $name=$kind==='admin'?'admin':'em_stage2_'.$kind;
    if($kind!=='admin'){ $r=http($name,'GET','App/user'); check('auth-'.$kind,$r['status']===200,$r['status']); }
    foreach(['attendance','holiday','profiles'] as $section){$r=http($name,'GET',$prefix.$section);check('section-'.$kind.'-'.$section,$r['status']===(in_array($section,$allowed)?200:403),$r['status']);}
}
$attendance=['editablePastMonths'=>2,'allowIncompleteExports'=>true];
$r=http('em_stage2_attendance','PUT',$prefix.'attendance',$attendance);check('attendance-save',$r['status']===200&&($r['body']['editablePastMonths']??null)===2,$r['status']);
foreach(['admin','em_stage2_employee','em_stage2_entry','em_stage2_noentry','em_stage2_holiday'] as $name){$r=http($name,'PUT',$prefix.'attendance',$attendance);check('attendance-deny-'.$name,$r['status']===403,$r['status']);}
foreach([['siteUrl'=>'https://evil.invalid'],['workingTimeCalendarId'=>'invalid'],['editablePastMonths'=>'3'],['editablePastMonths'=>121],['allowIncompleteExports'=>1],['archiveStartMonth'=>'2000-01']] as $invalid){$r=http('em_stage2_manager','PUT',$prefix.'attendance',array_replace($attendance,$invalid));check('attendance-invalid-'.implode(',',array_keys($invalid)),$r['status']===400,$r['status']);}
$holiday=['employeeHolidayAnnualEntitlementDays'=>21,'employeeHolidayResetDate'=>'2027-01-01','employeeHolidayAutomaticAnnualGrantEnabled'=>false,'employeeHolidayCarryOverLimitDays'=>90,'employeeHolidayNegativeBalanceLimitDays'=>-21,'employeeHolidayApproversIds'=>[$users['manager']->getId()], 'employeeHolidayApprovalBlock1Title'=>'Stage2 Director','employeeHolidayApprovalBlock1Name'=>'Synthetic Manager','employeeHolidayApprovalBlock2Title'=>'','employeeHolidayApprovalBlock2Name'=>''];
$r=http('em_stage2_holiday','PUT',$prefix.'holiday',$holiday);check('holiday-save',$r['status']===200,$r['status']);
foreach(['admin','em_stage2_employee','em_stage2_attendance','em_stage2_entry'] as $name){$r=http($name,'PUT',$prefix.'holiday',$holiday);check('holiday-deny-'.$name,$r['status']===403,$r['status']);}
foreach([['siteUrl'=>'https://evil.invalid'],['employeeHolidayApproversNames'=>['x'=>'spoof']],['employeeHolidayApproversIds'=>[$admin->getId()]],['employeeHolidayApproversIds'=>[$users['employee']->getId()]],['employeeHolidayApproversIds'=>[$users['inactive']->getId()]],['employeeHolidayAutomaticAnnualGrantEnabled'=>'true'],['employeeHolidayResetDate'=>'2027-02-30'],['employeeHolidayApprovalBlock1Name'=>['bad']],['employeeHolidayAnnualEntitlementDays'=>'21']] as $invalid){$r=http('em_stage2_manager','PUT',$prefix.'holiday',array_replace($holiday,$invalid));check('holiday-invalid-'.implode(',',array_keys($invalid)),$r['status']===400,$r['status']);}
$r=http('em_stage2_holiday','GET',$prefix.'approvers');$ids=array_column($r['body']['list']??[],'id');check('approver-options-only-native-authorized', $r['status']===200&&in_array($users['manager']->getId(),$ids)&&in_array($users['approver']->getId(),$ids)&&!in_array($admin->getId(),$ids)&&!in_array($users['employee']->getId(),$ids),$r['status']);
$r=http('em_stage2_profiles','GET',$prefix.'profiles');$ids=array_column($r['body']['list']??[],'userId');check('profile-population-regular-active',$r['status']===200&&in_array($users['manager']->getId(),$ids)&&!in_array($admin->getId(),$ids)&&!in_array($users['inactive']->getId(),$ids));
$item=['userId'=>$users['target']->getId(),'annualEntitlement'=>21,'openingBalance'=>18,'nextResetDate'=>'2027-01-01','calendarColor'=>'#4F8A8B','idempotencyKey'=>'stage2-initialize-target'];
$operation=$prefix.'profiles/bulkInitialize';
foreach(['admin','em_stage2_employee','em_stage2_attendance','em_stage2_entry','em_stage2_weak','em_stage2_fls'] as $name){$r=http($name,'POST',$operation,['items'=>[$item]]);check('profile-mutation-deny-'.$name,$r['status']===403,$r['status']);}
$r=http('em_stage2_profiles','POST',$operation,['items'=>[$item]]);check('initialize-profile',$r['status']===200&&($r['body']['list'][0]['balance']??null)==18,$r['status']);$profileId=$r['body']['list'][0]['profileId']??null;
$r=http('em_stage2_profiles','POST',$operation,['items'=>[$item]]);check('initialize-retry-idempotent',$r['status']===200&&($r['body']['list'][0]['duplicate']??false),$r['status']);
$ledgerCount=$em->getRDBRepository('EmployeeHolidayLedger')->where(['idempotencyKey'=>$item['idempotencyKey']])->count();check('one-initialization-ledger',$ledgerCount===1,$ledgerCount);
$changed=array_replace($item,['annualEntitlement'=>22]);$r=http('em_stage2_profiles','POST',$operation,['items'=>[$changed]]);check('idempotency-payload-conflict',$r['status']===409,$r['status']);
$other=array_replace($item,['userId'=>$users['target2']->getId()]);$r=http('em_stage2_profiles','POST',$operation,['items'=>[$other]]);check('idempotency-cross-user-conflict',$r['status']===409,$r['status']);
$update=$item;unset($update['openingBalance']);$update['annualEntitlement']=23;$update['idempotencyKey']='stage2-update-target';
$r=http('em_stage2_profiles','POST',$operation,['items'=>[$update]]);check('profile-parameter-update-not-balance',$r['status']===200&&($r['body']['list'][0]['balance']??null)==18&&($r['body']['list'][0]['annualEntitlement']??null)==23,$r['status']);
$r=http('em_stage2_profiles','POST',$operation,['items'=>[array_replace($item,['idempotencyKey'=>'stage2-incorrect-opening'])]]);check('opening-cannot-correct-balance',$r['status']===400,$r['status']);
foreach([$admin->getId(),$users['inactive']->getId()] as $id){$r=http('em_stage2_profiles','POST',$operation,['items'=>[array_replace($item,['userId'=>$id,'idempotencyKey'=>'stage2-reject-'.$id])]]);check('ineligible-beneficiary-'.$id,$r['status']===400,$r['status']);}
$first=array_replace($item,['userId'=>$users['target2']->getId(),'idempotencyKey'=>'atomic-'.$users['target2']->getId()]);$bad=array_replace($item,['userId'=>'zzzz-ineligible','idempotencyKey'=>'stage2-atomic-invalid']);
$r=http('em_stage2_profiles','POST',$operation,['items'=>[$first,$bad]]);check('invalid-batch-rollback-response',$r['status']===400,$r['status']);check('invalid-batch-no-partial-profile',$em->getRDBRepository('EmployeeHolidayProfile')->where(['assignedUserId'=>$users['target2']->getId()])->count()===0);
check('invalid-batch-no-partial-ledger',$em->getRDBRepository('EmployeeHolidayLedger')->where(['idempotencyKey'=>$first['idempotencyKey']])->count()===0);
foreach([['items'=>[$update],'isAdmin'=>true],['items'=>[(object)['userId'=>$users['target']->getId(),'balance'=>999]]],['items'=>['bad']]] as $invalid){$r=http('em_stage2_profiles','POST',$operation,$invalid);check('invalid-profile-payload',$r['status']===400,$r['status']);}
foreach(['correct','reset','decision'] as $op){$r=http('em_stage2_manager','POST',$prefix.'profiles/'.$op,[]);check('unimplemented-operation-blocked-'.$op,$r['status']===($op==='correct'?403:400),$r['status']);}
$zero=array_replace($item,['userId'=>$users['zero']->getId(),'openingBalance'=>0,'idempotencyKey'=>'zero-'.$users['zero']->getId()]);
$r=http('em_stage2_profiles','POST',$operation,['items'=>[$zero]]);$zeroId=$r['body']['list'][0]['profileId']??'';check('zero-profile-initialize',$r['status']===200&&($r['body']['list'][0]['balance']??-1)==0,$r['status']);
$setup=['profileId'=>$zeroId,'openingBalance'=>5,'idempotencyKey'=>'setup-'.$users['zero']->getId()];
$r=http('em_stage2_profiles','POST',$prefix.'profiles/completeInitialSetup',array_replace($setup,['openingBalance'=>'1e999','idempotencyKey'=>'nonfinite-'.$users['zero']->getId()]));check('setup-nonfinite-input-is-bad-request',$r['status']===400,$r['status']);
foreach(['admin','em_stage2_employee','em_stage2_entry','em_stage2_weak'] as $name){$r=http($name,'POST',$prefix.'profiles/completeInitialSetup',$setup);check('setup-deny-'.$name,$r['status']===403,$r['status']);}
$r=http('em_stage2_profiles','POST',$prefix.'profiles/completeInitialSetup',$setup);check('preserved-complete-initial-setup',$r['status']===200&&($r['body']['balance']??0)==5,$r['status']);
$r=http('em_stage2_profiles','POST',$prefix.'profiles/completeInitialSetup',$setup);check('setup-retry-idempotent',$r['status']===200&&($r['body']['duplicate']??false),$r['status']);
check('setup-single-ledger',$em->getRDBRepository('EmployeeHolidayLedger')->where(['idempotencyKey'=>$setup['idempotencyKey']])->count()===1);
$r=http('em_stage2_profiles','POST',$prefix.'profiles/completeInitialSetup',array_replace($setup,['idempotencyKey'=>'different-'.$users['zero']->getId()]));check('setup-not-a-balance-correction',$r['status']===409,$r['status']);
if($profileId){foreach(['admin','em_stage2_manager','em_stage2_profiles'] as $name){$r=http($name,'PUT','EmployeeHolidayProfile/'.$profileId,['balance'=>999]);check('generic-profile-write-blocked-'.$name,$r['status']===403,$r['status']);}
    $r=http('em_stage2_employee','GET','EmployeeHolidayProfile/'.$profileId);check('private-profile-id-blocked',$r['status']===403,$r['status']);}
foreach([['PUT','Settings',['siteUrl'=>'https://evil.invalid']],['POST','Role',['name'=>'Escalation']],['POST','WorkingTimeCalendar',['name'=>'Forbidden']]] as [$method,$url,$body]){$r=http('em_stage2_manager',$method,$url,$body);check('no-platform-escalation-'.$url,$r['status']===403,$r['status']);}
$freshConfig = require '/var/www/html/data/config.php';
check('global-site-url-unchanged',($freshConfig['siteUrl'] ?? null)==='http://127.0.0.1:18089');
foreach($original as $type=>$count)check('original-and-WTC-unchanged-'.$type,$em->getRDBRepository($type)->count()===$count);
// Two actual parallel native HTTP requests, not two sequential replay calls.
$race=fixture('User',['userName'=>'em_stage2_race_'.uniqid()],['userName'=>'em_stage2_race_'.uniqid(),'firstName'=>'Synthetic','lastName'=>'Stage2 Race','type'=>'regular','isActive'=>true,'password'=>$hash]);
$raceItem=array_replace($item,['userId'=>$race->getId(),'idempotencyKey'=>'race-'.$race->getId()]);
$multi=curl_multi_init();$handles=[];
for($i=0;$i<2;$i++){$ch=curl_init('http://127.0.0.1/api/v1/'.$operation);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['items'=>[$raceItem]]),CURLOPT_USERPWD=>'em_stage2_profiles:'.$password,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Espo-Authorization: '.base64_encode('em_stage2_profiles:'.$password)],CURLOPT_TIMEOUT=>30]);curl_multi_add_handle($multi,$ch);$handles[]=$ch;}
do{$status=curl_multi_exec($multi,$running);if($running)curl_multi_select($multi,1);}while($running&&$status===CURLM_OK);
$codes=[];$duplicates=[];foreach($handles as $ch){$codes[]=curl_getinfo($ch,CURLINFO_HTTP_CODE);$body=json_decode(curl_multi_getcontent($ch),true);$duplicates[]=$body['list'][0]['duplicate']??null;curl_multi_remove_handle($multi,$ch);curl_close($ch);}curl_multi_close($multi);
check('parallel-initialization-responses',$codes===[200,200],$codes);
check('parallel-initialization-one-duplicate',count(array_filter($duplicates,fn($x)=>$x===true))===1,$duplicates);
check('parallel-initialization-single-ledger',$em->getRDBRepository('EmployeeHolidayLedger')->where(['idempotencyKey'=>$raceItem['idempotencyKey']])->count()===1);
check('parallel-initialization-single-profile',$em->getRDBRepository('EmployeeHolidayProfile')->where(['assignedUserId'=>$race->getId()])->count()===1);
file_put_contents('/feasibility/employee-stage2-state.json',json_encode(['users'=>array_map(fn($u)=>$u->getId(),$users),'profileId'=>$profileId],JSON_PRETTY_PRINT));
file_put_contents('/feasibility/employee-stage3-regression-administration-results.json',json_encode(['results'=>$results,'assertions'=>count($results),'failures'=>$failures],JSON_PRETTY_PRINT));
echo json_encode(['assertions'=>count($results),'failures'=>$failures]).PHP_EOL;exit($failures?1:0);
