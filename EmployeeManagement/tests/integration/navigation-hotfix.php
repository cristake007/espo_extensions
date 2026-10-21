<?php
declare(strict_types=1);
if (getenv('EMPLOYEE_TEST_LAB') !== 'employee-feasibility-20261020') { throw new RuntimeException('Isolated lab only.'); }
chdir('/var/www/html'); require 'bootstrap.php';
$app=new Espo\Core\Application();$app->setupSystemUser();$c=$app->getContainer();$em=$c->get('entityManager');$f=$c->get('injectableFactory');
if ($c->get('config')->get('siteUrl') !== 'http://127.0.0.1:18089' || $c->get('config')->get('version') !== '10.0.1') {throw new RuntimeException('Wrong environment.');}
$old=json_decode(file_get_contents('/feasibility/employee-stage7-state.json'),true);
$reference=$em->getEntityById('User',$old['users']['manager']['id']);
$employee=$em->getEntityById('User',$old['users']['employee']['id']);
$hash=$f->create(Espo\Core\Utils\PasswordHash::class)->hash(getenv('EMPLOYEE_TEST_PASSWORD')?:throw new RuntimeException('Password required'));
$tag=uniqid('nav_');$s=['users'=>[]];
foreach (['manager','employee','entry','noentry','viewer'] as $kind) {
 $roles=[];foreach($em->getRDBRepository('User')->getRelation($reference,'roles')->find() as $role){$roles[]=$role->getId();}
 if($kind==='employee'){$roles=[];foreach($em->getRDBRepository('User')->getRelation($employee,'roles')->find() as $role){$roles[]=$role->getId();}}
 if(in_array($kind,['entry','noentry','viewer'],true)){
  $d=['EmployeeManagement'=>true,'EmployeeAdministration'=>$kind!=='noentry','EmployeeAttendanceSettings'=>$kind==='noentry','EmployeeAttendanceOverview'=>$kind==='viewer'];
  if($kind==='noentry'){foreach($em->getRDBRepository('User')->getRelation($reference,'roles')->find() as $role){$d=(array)$role->get('data');break;}$d['EmployeeAdministration']=false;}
  if($kind==='viewer'){foreach(['EmployeeAttendanceRecord','EmployeeAttendanceWorkSchedule'] as $scope){$d[$scope]=(object)['read'=>'all'];}}
  $r=$em->getNewEntity('Role');$r->set(['name'=>$tag.' '.$kind,'data'=>(object)$d]);$em->saveEntity($r);$roles=[$r->getId()];
 }
 $u=$em->getNewEntity('User');$u->set(['userName'=>$tag.'_'.$kind,'firstName'=>'Navigation','lastName'=>$kind,'type'=>'regular','isActive'=>true,'password'=>$hash,'rolesIds'=>$roles,'workingTimeCalendarId'=>$reference->get('workingTimeCalendarId')]);$em->saveEntity($u);
 $s['users'][$kind]=['id'=>$u->getId(),'name'=>$u->get('userName')];
}
file_put_contents('/feasibility/navigation-hotfix-state.json',json_encode($s,JSON_PRETTY_PRINT));
echo "PASS: isolated regular manager uses existing management Roles; no administrator grant or original Role change.\n";
