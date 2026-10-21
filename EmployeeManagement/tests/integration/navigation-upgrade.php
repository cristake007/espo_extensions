<?php
declare(strict_types=1);
if(getenv('EMPLOYEE_TEST_LAB')!=='employee-feasibility-20261020'){throw new RuntimeException('Isolated lab only.');}
chdir('/var/www/html');require 'bootstrap.php';$a=new Espo\Core\Application();$a->setupSystemUser();$c=$a->getContainer();
if($c->get('config')->get('siteUrl')!=='http://127.0.0.1:18089'){throw new RuntimeException('Wrong environment.');}
$em=$c->get('entityManager');$config=$c->get('config');$metadata=$c->get('metadata');
$own=['EmployeeManagement','EmployeeAttendance','EmployeeAttendanceOverview','EmployeeAdministration'];
$state=['otherTabs'=>array_values(array_filter($config->get('tabList'),fn($i)=>!is_string($i)||!in_array($i,$own,true))),
 'AdminRoute'=>$metadata->get(['app','clientRoutes','Admin']),
 'calendarView'=>$metadata->get(['clientDefs','Calendar','calendarView'])];

$state['calendarEntityList']=$config->get('calendarEntityList');
$state['employeeSettings']=[];
foreach((array)$metadata->get(['app','employeeManagement','settings']) as $fields){foreach(array_keys((array)$fields) as $name){$key=['editablePastMonths'=>'employeeAttendanceEditablePastMonths','allowIncompleteExports'=>'employeeAttendanceAllowIncompleteExports','archiveStartMonth'=>'employeeAttendanceArchiveStartMonth'][$name]??$name;$state['employeeSettings'][$key]=$config->get($key);}}
foreach(['Role','User','WorkingTimeCalendar','WorkingTimeRange','EmployeeHolidayProfile','EmployeeHolidayLedger','EmployeeHolidayRequest','EmployeeAttendanceWorkSchedule'] as $type){
 $rows=[];foreach($em->getRDBRepository($type)->order('id')->find() as $e){$rows[]=(array)$e->getValueMap();}
 $state[$type]=hash('sha256',json_encode($rows));
}
$path='/tmp/navigation-upgrade-baseline.json';
if(($argv[1]??'')==='before'){file_put_contents($path,json_encode($state));echo "PASS: upgrade baseline captured.\n";exit;}
$state=json_decode(json_encode($state),true);$before=json_decode(file_get_contents($path),true);$results=[];
foreach($state as $name=>$value){$ok=$before[$name]===$value;$results[]=['test'=>'upgrade-preserves-'.$name,'status'=>$ok?'PASS':'FAIL'];if(!$ok){throw new RuntimeException($name.' changed');}}
$tabs=$config->get('tabList');foreach($own as $scope){$ok=count(array_filter($tabs,fn($i)=>$i===$scope))===0;$results[]=['test'=>'no-automatic-sidebar-tab-'.$scope,'status'=>$ok?'PASS':'FAIL'];if(!$ok){throw new RuntimeException($scope.' still present');}}
echo json_encode(['results'=>$results,'assertions'=>count($results),'failures'=>0],JSON_PRETTY_PRINT).PHP_EOL;
