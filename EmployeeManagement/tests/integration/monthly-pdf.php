<?php
declare(strict_types=1);
if(getenv('EMPLOYEE_TEST_LAB')!=='employee-feasibility-20261020')throw new RuntimeException('Isolated synthetic lab only.');
chdir('/var/www/html');require 'bootstrap.php';$app=new Espo\Core\Application();$app->setupSystemUser();$c=$app->getContainer();$em=$c->get('entityManager');$f=$c->get('injectableFactory');$config=$c->get('config');
if($config->get('version')!=='10.0.1'||$config->get('siteUrl')!=='http://127.0.0.1:18089')throw new RuntimeException('Wrong environment.');
$phase=$argv[1]??'setup';$path='/feasibility/monthly-pdf-state.json';$s=is_file($path)?json_decode(file_get_contents($path),true):[];$results=[];
function check(string $name,bool $ok,mixed $value=null):void{global $results;$results[]=['test'=>$name,'status'=>$ok?'PASS':'FAIL','observed'=>$value];echo json_encode(end($results)).PHP_EOL;}
function fresh(string $type,array $data):Espo\ORM\Entity{global $em;$e=$em->getNewEntity($type);$e->set($data);$em->saveEntity($e);return $e;}
function setting(string $key,mixed $value):void{global $f;$w=$f->create(Espo\Core\Utils\Config\ConfigWriter::class);$w->set($key,$value);$w->save();}
function request(string $kind,string $method,string $url,?array $data=null):array{global $s;$name=$kind==='admin'?'admin':$s['users'][$kind]['name'];$pw=getenv($kind==='admin'?'EMPLOYEE_TEST_ADMIN_PASSWORD':'EMPLOYEE_TEST_PASSWORD');$h=curl_init('http://127.0.0.1/api/v1/'.$url);curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Espo-Authorization: '.base64_encode($name.':'.$pw)]]);if($data!==null)curl_setopt($h,CURLOPT_POSTFIELDS,json_encode($data));$body=curl_exec($h);if($body===false)throw new RuntimeException(curl_error($h));return ['status'=>curl_getinfo($h,CURLINFO_HTTP_CODE),'body'=>json_decode($body,true)];}
if($phase==='setup'){
 if($s&&!($s['restored']??false))throw new RuntimeException('Restore the existing cohort first.');
 $s=['users'=>[],'oldActive'=>[],'config'=>[],'restored'=>false];
 foreach(['employeeAttendanceAllowIncompleteExports','employeeAttendanceArchiveStartMonth','exportDisabled'] as $key)$s['config'][$key]=$config->get($key);
 foreach($em->getRDBRepository('User')->where(['type'=>'regular','isActive'=>true])->find() as $u){$s['oldActive'][]=$u->getId();$u->set('isActive',false);$em->saveEntity($u);}
 file_put_contents($path,json_encode($s,JSON_PRETTY_PRINT));
 $tag=uniqid('monthly_pdf_');$calendar=fresh('WorkingTimeCalendar',['name'=>$tag,'weekdays'=>[1,2,3,4,5],'timeRanges'=>[['11:00','17:00']],'timeZone'=>'Europe/Bucharest']);$hash=$f->create(Espo\Core\Utils\PasswordHash::class)->hash(getenv('EMPLOYEE_TEST_PASSWORD'));
 $scopes=['EmployeeManagement','EmployeeAdministration','EmployeeAttendance','EmployeeAttendanceOverview','EmployeeAttendanceManagement','EmployeeAttendanceSettings','EmployeeHolidaySettings','EmployeeHolidayProfileManagement','EmployeeHolidayBalanceCorrection','EmployeeHolidayApproval','EmployeeHolidayBooking','EmployeeHolidayRequestManagement'];
 foreach(['managerUnset','managerDenied','viewerExport','viewerDenied','employee','own','fls','noOverview'] as $kind){
  $manager=in_array($kind,['managerUnset','managerDenied','own','fls','noOverview'],true);$data=[];foreach($scopes as $scope)$data[$scope]=$manager||$scope==='EmployeeManagement'||$scope==='EmployeeAttendance';
  $data['EmployeeAttendanceOverview']=!in_array($kind,['employee','noOverview'],true);
  foreach(['EmployeeAttendanceRecord','EmployeeAttendanceWorkSchedule','EmployeeHolidayRequest','EmployeeHolidayProfile','EmployeeHolidayLedger'] as $type)$data[$type]=(object)['read'=>in_array($kind,['employee','own'],true)?'own':'all','create'=>'yes','edit'=>'all','delete'=>'all'];
  $roleData=['name'=>$tag.' '.$kind,'data'=>(object)$data,'fieldData'=>$kind==='fls'?(object)['EmployeeAttendanceRecord'=>(object)['status'=>(object)['read'=>'no','edit'=>'no']]]:(object)[]];
  if($kind!=='managerUnset')$roleData['exportPermission']=$kind==='viewerExport'?'yes':'no';
  $role=fresh('Role',$roleData);$user=fresh('User',['userName'=>$tag.'_'.$kind,'firstName'=>'Monthly PDF','lastName'=>$kind,'type'=>'regular','isActive'=>true,'password'=>$hash,'rolesIds'=>[$role->getId()],'workingTimeCalendarId'=>$calendar->getId()]);$s['users'][$kind]=['id'=>$user->getId(),'name'=>$user->get('userName'),'roleId'=>$role->getId()];
 }
 setting('employeeAttendanceAllowIncompleteExports',true);setting('employeeAttendanceArchiveStartMonth','2026-09');setting('exportDisabled',false);check('isolated-small-cohort',true,count($s['users']));
}
if($phase==='api'){
 foreach(['managerUnset','managerDenied'] as $kind){
  $r=request($kind,'GET','App/user');check($kind.'-regular-without-global-Export',$r['status']===200&&$r['body']['user']['type']==='regular'&&$r['body']['acl']['exportPermission']!=='yes',$r['body']['acl']['exportPermission']??null);
  $r=request($kind,'GET','EmployeeManagement/attendance/overview?month=2026-10');check($kind.'-monthly-PDF-authorized',$r['status']===200&&$r['body']['isManager']&&$r['body']['downloadReady']&&$r['body']['pdfDownloadAllowed']&&!$r['body']['exportAllowed']);
  $r=request($kind,'POST','EmployeeManagement/attendance/pdf',['month'=>'2026-10']);check($kind.'-actual-PDF-command',$r['status']===200&&str_starts_with(base64_decode($r['body']['content']??''),'%PDF-'),$r['status']);
  $r=request($kind,'POST','EmployeeManagement/attendance/xlsx',['month'=>'2026-10']);check($kind.'-generic-export-permission-preserved',$r['status']===403,$r['status']);
 }
 foreach(['viewerDenied','employee','own','fls','noOverview','admin'] as $kind){$r=request($kind,'POST','EmployeeManagement/attendance/pdf',['month'=>'2026-10']);check('PDF-security-'.$kind,$r['status']===403,$r['status']);}
 $r=request('viewerExport','POST','EmployeeManagement/attendance/pdf',['month'=>'2026-10']);check('existing-authorized-viewer-PDF-preserved',$r['status']===200,$r['status']);
 setting('employeeAttendanceAllowIncompleteExports',false);$r=request('managerDenied','POST','EmployeeManagement/attendance/pdf',['month'=>'2026-10']);check('incomplete-policy-still-enforced',$r['status']===409,$r['status']);setting('employeeAttendanceAllowIncompleteExports',true);
 setting('exportDisabled',true);$r=request('managerDenied','POST','EmployeeManagement/attendance/pdf',['month'=>'2026-10']);check('explicit-platform-disable-still-enforced',$r['status']===403,$r['status']);setting('exportDisabled',false);
 foreach(['managerUnset','managerDenied'] as $kind){$r=request($kind,'GET','App/user');check($kind.'-global-Export-never-granted',$r['body']['acl']['exportPermission']!=='yes');}
}
if($phase==='restore'){
 foreach($s['users'] as $u){$e=$em->getEntityById('User',$u['id']);$e->set('isActive',false);$em->saveEntity($e);}
 foreach($s['oldActive'] as $id){$e=$em->getEntityById('User',$id);if($e){$e->set('isActive',true);$em->saveEntity($e);}}
 foreach($s['config'] as $key=>$value)setting($key,$value);$s['restored']=true;check('lab-cohort-and-settings-restored',true);
}
file_put_contents($path,json_encode($s,JSON_PRETTY_PRINT));$failures=count(array_filter($results,fn($r)=>$r['status']!=='PASS'));file_put_contents('/feasibility/monthly-pdf-'.$phase.'-results.json',json_encode(['results'=>$results,'assertions'=>count($results),'failures'=>$failures],JSON_PRETTY_PRINT));exit($failures?1:0);
