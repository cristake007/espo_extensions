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
function saveResults():void{global $results,$failures;file_put_contents('/feasibility/employee-stage4-api-results.json',json_encode(['version'=>'0.4.0','results'=>$results,'assertions'=>count($results),'failures'=>$failures],JSON_PRETTY_PRINT));}
// All old users are synthetic laboratory users. Temporarily narrow the fixture cohort to 15, preserving restoration IDs.
if(($argv[1]??'')==='restore'){$state=json_decode(file_get_contents('/feasibility/employee-stage4-state.json'),true);foreach($state['deactivatedUserIds'] as $id){$e=$em->getEntityById('User',$id);if($e){$e->set('isActive',true);$em->saveEntity($e);}}echo "Restored prior synthetic active users.\n";exit;}
$oldActive=is_file('/feasibility/employee-stage4-state.json')?(json_decode(file_get_contents('/feasibility/employee-stage4-state.json'),true)['deactivatedUserIds']??[]):[];
foreach($em->getRDBRepository('User')->where(['type'=>'regular','isActive'=>true])->find() as $e){$oldActive[]=$e->getId();$e->set('isActive',false);$em->saveEntity($e);}$oldActive=array_values(array_unique($oldActive));
// Repeatable synthetic fixture only: retain previous derived month data in /feasibility before reseeding a new test cohort.
foreach(['2026-09','2026-10'] as $month){$previous=$em->getRDBRepository('EmployeeAttendanceMonth')->where(['month'=>$month])->findOne();if($previous){file_put_contents('/feasibility/employee-stage4-previous-'.$month.'-'.$tag.'.json',json_encode($previous->getValueMap(),JSON_PRETTY_PRINT));$previous->set(['norms'=>(object)[],'policies'=>null,'frozen'=>false,'frozenAt'=>null,'frozenById'=>null,'revision'=>0]);$em->saveEntity($previous);}}
$original=[];foreach(['AttendanceRecord','AttendanceWorkSchedule','HolidayRequest','HolidayProfile','HolidayLedger'] as $type)$original[$type]=$em->getRDBRepository($type)->count();
$calendars=[];foreach([4,6,8] as $hours)$calendars[$hours]=fresh('WorkingTimeCalendar',['name'=>'Synthetic Stage4 '.$tag.' '.$hours.'h','weekdays'=>[1,2,3,4,5],'timeRanges'=>[['08:00',sprintf('%02d:00',8+$hours)]],'timeZone'=>'Europe/Bucharest']);
$hash=$factory->create(Espo\Core\Utils\PasswordHash::class)->hash($password);
$kinds=['manager','four','six','eight','cic','remote','viewer','weak','fls','nomark','noexport','nowtc','race','other','ui'];
foreach($kinds as $kind){
    $all=in_array($kind,['manager','viewer','noexport','fls']);$data=['EmployeeManagement'=>true,'EmployeeAdministration'=>$kind==='manager','EmployeeAttendanceSettings'=>$kind==='manager','EmployeeAttendance'=>$kind!=='nomark','EmployeeAttendanceOverview'=>$all||$kind==='weak','EmployeeAttendanceManagement'=>in_array($kind,['manager','noexport','fls'])];
    foreach(['EmployeeAttendanceRecord','EmployeeAttendanceWorkSchedule'] as $type)$data[$type]=(object)['read'=>$all?'all':'own','create'=>'yes','edit'=>'all','delete'=>'all'];
    $role=fresh('Role',['name'=>'Synthetic Stage4 '.$tag.' '.$kind,'data'=>(object)$data,'exportPermission'=>$kind==='noexport'?'no':'yes',
        'fieldData'=>$kind==='fls'?(object)['EmployeeAttendanceRecord'=>(object)['status'=>(object)['read'=>'no','edit'=>'no']]]:(object)[]]);
    $hours=$kind==='six'?6:($kind==='eight'?8:4);
    $users[$kind]=fresh('User',['userName'=>'em_'.$tag.'_'.$kind,'firstName'=>'Synthetic','lastName'=>'Stage4 '.$kind,'type'=>'regular','isActive'=>true,'password'=>$hash,'rolesIds'=>[$role->getId()],'workingTimeCalendarId'=>$kind==='nowtc'?null:$calendars[$hours]->getId()]);
}
$writer=$factory->create(Espo\Core\Utils\Config\ConfigWriter::class);$writer->setMultiple(['employeeAttendanceArchiveStartMonth'=>'2026-09','employeeAttendanceEditablePastMonths'=>2,'employeeAttendanceAllowIncompleteExports'=>false]);$writer->save();
fresh('ZileLibere',['name'=>'Synthetic Stage4 public holiday '.$tag,'countryCode'=>'RO','dateStart'=>'2026-09-15']);
$today=$c->get('dateTime')->getToday()->toString();
file_put_contents('/feasibility/employee-stage4-state.json',json_encode(['users'=>array_map(fn($e)=>['id'=>$e->getId(),'name'=>$e->get('userName')],$users),'calendars'=>array_map(fn($e)=>$e->getId(),$calendars),'deactivatedUserIds'=>$oldActive,'today'=>$today],JSON_PRETTY_PRINT));
foreach(['admin','nomark','fls'] as $kind){$r=api($kind,'myAttendance?month=2026-09');check('personal-ACL-'.$kind,$r['status']===403,$r['status']);}
foreach(['admin','four','weak','fls'] as $kind){$r=api($kind,'overview?month=2026-09');check('overview-ACL-'.$kind,$r['status']===403,$r['status']);}
$r=api('viewer','overview?month=2026-09');check('native-readonly-overview',$r['status']===200,$r['status']);
if($r['status']!==200){saveResults();exit(1);}
$data=$r['body'];check('population-15-no-admin',count($data['users'])===15&&!in_array($admin->getId(),array_column($data['users'],'id')));
check('missing-WTC-reported-not-defaulted',$data['scheduleMissingCount']===1,$data['scheduleMissingCount']);
foreach(['four'=>84,'six'=>126,'eight'=>168] as $kind=>$hours){$row=array_values(array_filter($data['users'],fn($row)=>$row['id']===$users[$kind]->getId()))[0];check('WTC-norm-'.$kind,(float)$row['normHours']==(float)$hours,[$row['normHours'],$hours]);}
$r=mark('nowtc','2026-09-14');check('missing-WTC-mark-refused',$r['status']===400,$r['status']);
$r=api('nowtc','myAttendance?month=2026-09');check('personal-missing-WTC-explicit',$r['status']===200&&$r['body']['normConfigured']===false,$r['status']);
$users['nowtc']->set('workingTimeCalendarId',$calendars[4]->getId());$em->saveEntity($users['nowtc']);$data=overview();check('native-WTC-assignment-only-admin-fixture',$data['scheduleMissingCount']===0);
$r=api('nowtc','myAttendance?month=2026-09');check('personal-native-WTC-ready',$r['status']===200&&$r['body']['normConfigured']===true,$r['status']);
$r=mark('four','2026-09-14');check('employee-mark',$r['status']===200,$r);
$r=mark('four','2026-09-15');check('legal-nonworking-day-blocked',$r['status']===400,$r['status']);
$r=mark('four','2026-09-13');check('weekend-blocked',$r['status']===400,$r['status']);
$future=(new DateTimeImmutable($today))->modify('+1 day')->format('Y-m-d');$r=mark('four',$future);check('future-mark-blocked',$r['status']===400,$r['status']);
$r=correction('four',$future);check('manager-future-mark-blocked',$r['status']===400,$r['status']);
$r=mark('admin','2026-09-14');check('admin-business-write-blocked',$r['status']===403,$r['status']);
$r=api('manager','managerMark',['userId'=>$admin->getId(),'date'=>'2026-09-14','status'=>'AtWork','reason'=>'Synthetic']);check('admin-beneficiary-blocked',$r['status']===404,$r['status']);
foreach([['workingTimeCalendarId'=>'x'],['startTime'=>'09:00'],['assignedUserId'=>$users['other']->getId()],['status'=>'Holiday'],['date'=>'2026-09-31']] as $bad){$r=api('four','mark',array_replace(['date'=>'2026-09-14','status'=>'AtWork'],$bad));check('mark-invalid-'.implode(',',array_keys($bad)),$r['status']===400,$r['status']);}
$meta=['userId'=>$users['cic']->getId(),'remote'=>false,'cic'=>true,'cicStartDate'=>'2026-09-16','cicEndDate'=>'2026-09-18'];$r=api('manager','schedule',$meta);check('original-CIC-period-settings',$r['status']===200,$r);
foreach(['viewer','four','admin'] as $kind){$r=api($kind,'schedule',$meta);check('metadata-write-ACL-'.$kind,$r['status']===403,$r['status']);}
$r=api('manager','schedule',$meta+['startTime'=>'08:00']);check('no-parallel-program-editor-API',$r['status']===400,$r['status']);
$r=api('manager','schedule',array_replace($meta,['cicEndDate'=>'2026-09-10']));check('CIC-period-validation',$r['status']===400,$r['status']);
$data=overview();check('CIC-derived-status',cell($data,$users['cic']->getId(),'2026-09-16')['status']==='ChildCareLeave');
foreach(['AtWork','BusinessTrip'] as $status){$r=mark('cic','2026-09-16',$status);check('CIC-blocks-'.$status,$r['status']===409,$r['status']);}
$r=mark('cic','2026-09-16','MedicalLeave');check('CIC-permits-medical-mark',$r['status']===200,$r);
$data=overview();check('CIC-medical-visible-in-matrix',cell($data,$users['cic']->getId(),'2026-09-16')['status']==='MedicalLeave');
// Historical Holiday fixtures only; Stage 4 never approves or modifies a Holiday request.
fresh('EmployeeHolidayRequest',['name'=>'Synthetic Stage4 approved absence','assignedUserId'=>$users['cic']->getId(),'dateStartDate'=>'2026-09-17','dateEndDate'=>'2026-09-17','days'=>1,'status'=>'CancellationPending','accountingKey'=>$tag.'-holiday','accountingRevision'=>1]);
$r=mark('cic','2026-09-17','MedicalLeave');check('effective-CO-higher-priority-than-CIC-medical',$r['status']===409,$r['status']);
$data=overview();check('CancellationPending-remains-effective-CO',cell($data,$users['cic']->getId(),'2026-09-17')['status']==='Holiday');
$r=api('manager','schedule',['userId'=>$users['remote']->getId(),'remote'=>true,'cic'=>false,'cicStartDate'=>null,'cicEndDate'=>null]);check('original-remote-metadata',$r['status']===200,$r['status']);
$r=mark('remote','2026-09-14');check('remote-mark',$r['status']===200,$r['status']);
$r=api('viewer','managerMark',['userId'=>$users['four']->getId(),'date'=>'2026-09-14','status'=>'BusinessTrip','reason'=>'Synthetic']);check('overview-not-management',$r['status']===403,$r['status']);
$r=api('manager','managerMark',['userId'=>$users['four']->getId(),'date'=>'2026-09-14','status'=>'BusinessTrip','reason'=>'']);check('manager-reason-required',$r['status']===400,$r['status']);
$r=correction('four','2026-09-14','BusinessTrip',1);check('manager-correction',$r['status']===200,$r);
$r=correction('four','2026-09-14','AtWork',1);check('stale-record-revision-blocked',$r['status']===409,$r['status']);
$state=$em->getRDBRepository('EmployeeAttendanceMonth')->where(['month'=>'2026-09'])->findOne();$normBefore=json_encode($state->get('norms'));
$calendars[4]->set('timeRanges',[['07:00','15:00']]);$em->saveEntity($calendars[4]);
$data=overview();check('historical-WTC-hours-preserved',cell($data,$users['four']->getId(),'2026-09-14')['normHours']==4);
check('historical-WTC-start-time-preserved',cell($data,$users['four']->getId(),'2026-09-14')['schedule']['startTime']==='08:00',cell($data,$users['four']->getId(),'2026-09-14')['schedule']);
$r=api('manager','remind',['month'=>'2026-09']);check('native-reminders-missing-only',$r['status']===200&&$r['body']['sent']>0,$r['status']);
$r=api('manager','xlsx',['month'=>'2026-09']);check('incomplete-export-config-enforced',$r['status']===409,$r['status']);
$r=api('noexport','xlsx',['month'=>'2026-09']);check('native-export-permission-enforced',$r['status']===403,$r['status']);
// Fill synthetic prior-month rows via ORM fixture; final day is submitted through the real employee API to freeze.
$lastDate='2026-09-30';$lastUser=$users['eight']->getId();
$em->getTransactionManager()->run(function()use($data,$lastDate,$lastUser,$em){foreach($data['rows'] as $row){foreach($row['cells'] as $cell){if(!$cell['isWorkingDay']||$cell['status']||($cell['date']===$lastDate&&$cell['userId']===$lastUser))continue;fresh('EmployeeAttendanceRecord',['name'=>'Synthetic Stage4 filled record','assignedUserId'=>$cell['userId'],'date'=>$cell['date'],'status'=>'AtWork','source'=>'Self','markedAt'=>Espo\Core\Utils\DateTime::getSystemNowString(),'revision'=>1]);}}});
$r=mark('eight',$lastDate);check('100-percent-final-employee-mark',$r['status']===200,$r);
$data=overview();check('100-percent-month-frozen',$data['frozen']===true&&$data['registerComplete']===true&&$data['missingCount']===0,$data['frozen']);
$r=mark('four','2026-09-14');check('employee-frozen-month-denied',$r['status']===409,$r['status']);
$r=api('manager','remind',['month'=>'2026-09']);check('frozen-month-no-reminders',$r['status']===409,$r['status']);
$xlsx=api('manager','xlsx',['month'=>'2026-09']);check('original-XLSX-export',$xlsx['status']===200&&str_starts_with(base64_decode($xlsx['body']['content']??''),'PK'),$xlsx['status']);
$pdf=api('manager','pdf',['month'=>'2026-09']);check('original-PDF-export',$pdf['status']===200&&str_starts_with(base64_decode($pdf['body']['content']??''),'%PDF'),$pdf['status']);
$r=correction('four','2026-09-14','MedicalLeave',2);check('manager-corrects-frozen-month',$r['status']===200,$r);
$after=overview();check('correction-does-not-unfreeze',$after['frozen']===true&&cell($after,$users['four']->getId(),'2026-09-14')['status']==='MedicalLeave');
$freshXlsx=api('manager','xlsx',['month'=>'2026-09']);check('export-reflects-new-correction',$freshXlsx['status']===200&&$freshXlsx['body']['content']!==$xlsx['body']['content']);
$file='/feasibility/employee-stage4.xlsx';file_put_contents($file,base64_decode($freshXlsx['body']['content']));$sheet=PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getActiveSheet();$exportText=json_encode($sheet->toArray());check('XLSX-preserves-Remote-CIC-CO-medical',str_contains($exportText,'Remote')&&str_contains($exportText,'CIC')&&str_contains($exportText,'CO')&&str_contains($exportText,'CM'));
$stored=$em->getRDBRepository('EmployeeAttendanceRecord')->where(['assignedUserId'=>$users['four']->getId(),'date'=>'2026-09-14'])->findOne();check('native-modifiedBy-and-reason',$stored->get('modifiedById')===$users['manager']->getId()&&$stored->get('correctionReason')==='Synthetic managerial correction',[$stored->get('modifiedById'),$stored->get('correctionReason')]);
$notes=$em->getRDBRepository('Note')->where(['parentId'=>$stored->getId(),'type'=>'Update'])->count();check('native-status-audit-recorded',$notes>=2,$notes);
$state=$em->getRDBRepository('EmployeeAttendanceMonth')->where(['month'=>'2026-09'])->findOne();check('norms-immutable-after-freeze-correction',json_encode($state->get('norms'))===$normBefore);
foreach(['EmployeeAttendanceRecord','EmployeeAttendanceWorkSchedule','EmployeeAttendanceMonth'] as $type){$r=http('manager','POST',$type,['name'=>'Forbidden']);check('generic-CRUD-closed-'.$type,in_array($r['status'],[403,404],true),$r['status']);}
foreach($original as $type=>$count)check('original-data-retained-'.$type,$em->getRDBRepository($type)->count()===$count);
saveResults();echo json_encode(['assertions'=>count($results),'failures'=>$failures]).PHP_EOL;exit($failures?1:0);
