<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/files/custom/Espo/Modules/EmployeeManagement/Tools/Navigation/TabList.php';
use Espo\Modules\EmployeeManagement\Tools\Navigation\TabList;
require dirname(__DIR__,2).'/scripts/AfterUninstall.php';
$cleanup=new ReflectionMethod(AfterUninstall::class,'removeTabs');
function check(string $name,bool $ok):void {if(!$ok){throw new RuntimeException($name);}echo "PASS: $name\n";}
$original=['Account',(object)['id'=>'custom','text'=>'Operations','itemList'=>['Calendar','EmployeeAttendance']], '_delimiter_', 'Document'];
$installed=TabList::install($original);
check('upgrade preserves groups, existing navigation order and More position', $installed===[$original[0],$original[1],'EmployeeManagement','EmployeeAttendanceOverview','EmployeeAdministration','_delimiter_','Document']);
check('repeated upgrades do not duplicate nested or top-level tabs',TabList::install($installed)===$installed);
$removed=$cleanup->invoke(null,$installed);
check('uninstall removes only own tabs, including nested scopes',$removed==['Account',(object)['id'=>'custom','text'=>'Operations','itemList'=>['Calendar']], '_delimiter_', 'Document']);
check('empty groups containing only own scopes are removed',$cleanup->invoke(null,[(object)['itemList'=>TabList::SCOPES],'Calendar'])===['Calendar']);
check('empty and fully customized lists are supported',TabList::install([])===TabList::SCOPES&&$cleanup->invoke(null,['Account'])===['Account']);
