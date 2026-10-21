<?php
declare(strict_types=1);
if(getenv('EMPLOYEE_TEST_LAB')!=='employee-feasibility-20261020'){throw new RuntimeException('Isolated lab only.');}
chdir('/var/www/html');require 'bootstrap.php';$c=(new Espo\Core\Application())->getContainer();$em=$c->get('entityManager');$factory=$c->get('injectableFactory');
if($c->get('config')->get('version')!=='10.0.1'||$c->get('config')->get('siteUrl')!=='http://127.0.0.1:18089'){throw new RuntimeException('Wrong environment.');}
$admin=$em->getRDBRepository('User')->where(['userName'=>'admin'])->findOne();$c->getByClass(Espo\Core\ApplicationUser::class)->setUser($admin);
$password=getenv('EMPLOYEE_TEST_PASSWORD')?:throw new RuntimeException('Password required.');$adminPassword=getenv('EMPLOYEE_TEST_ADMIN_PASSWORD')?:throw new RuntimeException('Admin password required.');
$users=[];$results=[];$failures=0;$tag=uniqid('s4');
function check(string $test,bool $ok,mixed $observed=null):void{global $results,$failures;$r=['test'=>$test,'status'=>$ok?'PASS':'FAIL','observed'=>$observed];$results[]=$r;if(!$ok)$failures++;echo json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;}
function fresh(string $type,array $values):Espo\ORM\Entity{global $em;$e=$em->getNewEntity($type);$e->set($values);$em->saveEntity($e);return $e;}
function http(string $kind,string $method,string $path,?array $body=null):array{global $users,$password,$adminPassword;$name=$kind==='admin'?'admin':$users[$kind]->get('userName');$pw=$kind==='admin'?$adminPassword:$password;$h=curl_init('http://127.0.0.1/api/v1/'.$path);curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Espo-Authorization: '.base64_encode($name.':'.$pw)],CURLOPT_TIMEOUT=>90]);if($body!==null)curl_setopt($h,CURLOPT_POSTFIELDS,json_encode($body));$raw=curl_exec($h);if($raw===false)throw new RuntimeException(curl_error($h));$s=curl_getinfo($h,CURLINFO_HTTP_CODE);curl_close($h);return ['status'=>$s,'body'=>json_decode($raw,true),'raw'=>$raw];}
function api(string $kind,string $op,?array $body=null):array{return http($kind,$body===null?'GET':'POST','EmployeeManagement/attendance/'.$op,$body);}
function cell(array $overview,string $userId,string $date):array{foreach($overview['rows']??[] as $row){if($row['date']!==$date)continue;foreach($row['cells'] as $cell){if($cell['userId']===$userId)return $cell;}}return [];}
function overview(string $month='2026-09'):array{$r=api('manager','overview?month='.$month);if($r['status']!==200){check('overview-required-'.$month,false,$r);throw new RuntimeException('Cannot continue Attendance assertions.');}return $r['body'];}
function mark(string $kind,string $date,string $status='AtWork'):array{return api($kind,'mark',['date'=>$date,'status'=>$status]);}
function correction(string $target,string $date,string $status='BusinessTrip',?int $revision=null):array{global $users;$body=['userId'=>$users[$target]->getId(),'date'=>$date,'status'=>$status,'reason'=>'Synthetic managerial correction'];if($revision!==null)$body['revision']=$revision;return api('manager','managerMark',$body);}
function saveResults():void{global $results,$failures;file_put_contents('/feasibility/employee-stage4-extra-results.json',json_encode(['version'=>'0.4.0','results'=>$results,'assertions'=>count($results),'failures'=>$failures],JSON_PRETTY_PRINT));}
$state=json_decode(file_get_contents('/feasibility/employee-stage4-state.json'),true);
foreach($state['users'] as $kind=>$value){$users[$kind]=$em->getEntityById('User',$value['id']);}
$today=$c->get('dateTime')->getToday()->toString();
$r=api('manager','overview?month=2026-11');check('future-month-open-denied',$r['status']===400,$r['status']);
$r=api('four','myAttendance?month=2026-09');check('personal-frozen-state-and-only-own-data',$r['status']===200&&$r['body']['frozen']===true&&count($r['body']['days'])===21&&!str_contains($r['raw'],$users['six']->getId()),$r['status']);
$r=correction('cic','2026-09-18','AtWork');check('manager-cannot-write-work-over-frozen-CIC',$r['status']===409,$r['status']);
$r=correction('cic','2026-09-18','MedicalLeave',0);check('manager-medical-over-frozen-CIC',$r['status']===200,$r['status']);
$r=api('manager','schedule',['userId'=>$users['cic']->getId(),'remote'=>false,'cic'=>true,'cicStartDate'=>$today,'cicEndDate'=>substr($today,0,7).'-31']);check('current-CIC-period-for-UI',$r['status']===200,$r['status']);
$r=api('cic','myAttendance');check('current-CIC-medical-only-controls',$r['status']===200&&!$r['body']['todayCanMark']&&$r['body']['todayCanMarkMedical'],$r['status']);
$r=mark('cic',$today,'MedicalLeave');check('current-CIC-medical-valid',$r['status']===200,$r['status']);
$r=mark('cic',$today,'AtWork');check('medical-does-not-unlock-CIC-work',$r['status']===409,$r['status']);
$r=api('manager','overview');check('current-month-future-days-prevent-premature-freeze',$r['status']===200&&$r['body']['futureCount']>0&&!$r['body']['frozen']&&!$r['body']['registerComplete'],$r['status']);
$before=$em->getRDBRepository('EmployeeAttendanceMonth')->where(['month'=>'2026-09'])->findOne();$normHash=hash('sha256',json_encode($before->get('norms')));
$users['six']->set('workingTimeCalendarId',$state['calendars']['8']);$em->saveEntity($users['six']);
$data=overview();check('WTC-reassignment-does-not-rewrite-history',cell($data,$users['six']->getId(),'2026-09-14')['normHours']==6);
$r=http('manager','PUT','User/'.$users['other']->getId(),['workingTimeCalendarId'=>$state['calendars']['8']]);check('manager-cannot-assign-native-WTC',$r['status']===403,$r['status']);
// Native field edit ACL remains authoritative even when the boolean command permission is granted.
foreach($em->getRDBRepository('User')->getRelation($users['other'],'roles')->find() as $role){$role->set('fieldData',(object)['EmployeeAttendanceRecord'=>(object)['status'=>(object)['read'=>'yes','edit'=>'no']]]);$em->saveEntity($role);}
$r=mark('other',$today);check('native-field-edit-ACL-enforced',$r['status']===403,$r['status']);
// Two actual parallel writes to one new daily record, both using revision zero.
$m=curl_multi_init();$handles=[];
for($i=0;$i<2;$i++){$h=curl_init('http://127.0.0.1/api/v1/EmployeeManagement/attendance/mark');curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['date'=>$today,'status'=>$i===0?'AtWork':'BusinessTrip','revision'=>0]),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Espo-Authorization: '.base64_encode($users['race']->get('userName').':'.$password)],CURLOPT_TIMEOUT=>90]);curl_multi_add_handle($m,$h);$handles[]=$h;}
do{$status=curl_multi_exec($m,$running);if($running)curl_multi_select($m,1);}while($running&&$status===CURLM_OK);
$codes=[];foreach($handles as $h){$codes[]=curl_getinfo($h,CURLINFO_HTTP_CODE);curl_multi_remove_handle($m,$h);curl_close($h);}curl_multi_close($m);sort($codes);check('concurrent-daily-write-stale-conflict',$codes===[200,409],$codes);
check('concurrent-daily-write-one-record',$em->getRDBRepository('EmployeeAttendanceRecord')->where(['assignedUserId'=>$users['race']->getId(),'date'=>$today])->count()===1);
$count=$em->getRDBRepository('EmployeeAttendanceRecord')->count();
foreach(['admin','four','viewer'] as $kind){$r=http($kind,'POST','EmployeeManagement/attendance/initialize');check('archive-initializer-ACL-'.$kind,$r['status']===403,$r['status']);}
$r=http('manager','POST','EmployeeManagement/attendance/initialize');check('original-archive-initializer-active',$r['status']===200&&$r['body']['archiveStartMonth']===substr($today,0,7),$r['status']);
check('archive-initializer-never-deletes-data',$em->getRDBRepository('EmployeeAttendanceRecord')->count()===$count);
$writer=$factory->create(Espo\Core\Utils\Config\ConfigWriter::class);$writer->set('employeeAttendanceArchiveStartMonth','2026-09');$writer->save();
$after=$em->getRDBRepository('EmployeeAttendanceMonth')->where(['month'=>'2026-09'])->findOne();check('all-supplemental-commands-preserve-norm',$after->get('frozen')&&hash('sha256',json_encode($after->get('norms')))===$normHash);
saveResults();echo json_encode(['assertions'=>count($results),'failures'=>$failures]).PHP_EOL;exit($failures?1:0);
