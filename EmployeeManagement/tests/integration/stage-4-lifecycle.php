<?php

declare(strict_types=1);

if (getenv('EMPLOYEE_TEST_LAB') !== 'employee-feasibility-20261020') {
    throw new RuntimeException('Isolated laboratory only.');
}
chdir('/var/www/html');
require 'bootstrap.php';
$container = (new Espo\Core\Application())->getContainer();
$config = $container->get('config');
if ($config->get('siteUrl') !== 'http://127.0.0.1:18089' || $config->get('version') !== '10.0.1') {
    throw new RuntimeException('Wrong runtime.');
}
$em = $container->get('entityManager');
$metadata = $container->get('metadata');
$state = [
    'tabList' => $config->get('tabList'),
    'calendarEntityList' => $config->get('calendarEntityList'),
    'globalAdminRoute' => $metadata->get(['app', 'clientRoutes', 'Admin']),
    'globalCalendarView' => $metadata->get(['clientDefs', 'Calendar', 'calendarView']),
];
foreach (['AttendanceRecord', 'AttendanceWorkSchedule', 'HolidayRequest', 'HolidayProfile', 'HolidayLedger',
    'EmployeeAttendanceRecord', 'EmployeeAttendanceWorkSchedule', 'EmployeeAttendanceMonth', 'EmployeeHolidayRequest', 'EmployeeHolidayProfile', 'EmployeeHolidayLedger', 'WorkingTimeCalendar', 'WorkingTimeRange'] as $type) {
    $state['counts'][$type] = $em->getRDBRepository($type)->count();
}
foreach (['Attendance Management', 'Holiday Management', 'Zile Sărbătoare'] as $name) {
    $record = $em->getRDBRepository('Extension')->where(['name' => $name, 'isInstalled' => true])->findOne();
    $state['originalExtensions'][$name] = $record?->get('version');
}
$state['employeeManagementVersion'] = $em->getRDBRepository('Extension')->where(['name' => 'Employee Management', 'isInstalled' => true])->findOne()?->get('version');
if ($state['employeeManagementVersion'] !== '0.4.0') { throw new RuntimeException('Wrong Employee Management version.'); }
foreach (array_keys((array) $metadata->get(['app', 'employeeManagement', 'settings'])) as $section) {
    foreach (array_keys((array) $metadata->get(['app', 'employeeManagement', 'settings', $section])) as $name) {
        $configName = ['editablePastMonths' => 'employeeAttendanceEditablePastMonths',
            'allowIncompleteExports' => 'employeeAttendanceAllowIncompleteExports',
            'archiveStartMonth' => 'employeeAttendanceArchiveStartMonth'][$name] ?? $name;
        $state['employeeSettings'][$configName] = $config->get($configName);
    }
}
foreach (['EmployeeAttendanceRecord'=>['id','assignedUserId','date','status','source','revision','correctionReason','createdById','modifiedById'],
    'EmployeeAttendanceWorkSchedule'=>['id','assignedUserId','remote','cic','cicStartDate','cicEndDate','effectiveFrom','modifiedById'],
    'EmployeeAttendanceMonth'=>['id','month','norms','policies','frozen','frozenAt','frozenById','revision'],
    'EmployeeHolidayRequest' => ['id', 'assignedUserId', 'status', 'dateStartDate', 'dateEndDate', 'description', 'days', 'accountingKey', 'accountingRevision', 'creationHash', 'calculationSnapshot', 'operationReceipts'],
    'EmployeeHolidayProfile' => ['id', 'assignedUserId', 'annualEntitlement', 'balance', 'nextResetDate', 'calendarColor', 'isInitialized', 'resetPending'],
    'EmployeeHolidayLedger' => ['id', 'profileId', 'assignedUserId', 'type', 'delta', 'balanceBefore', 'balanceAfter', 'actorId', 'idempotencyKey', 'operationHash'],
    'Role' => ['id', 'name', 'data', 'fieldData']] as $type => $fields) {
    $rows = [];
    foreach ($em->getRDBRepository($type)->order('id')->find() as $entity) {
        $row = [];
        foreach ($fields as $field) { $row[$field] = $entity->get($field); }
        $rows[] = $row;
    }
    $state['dataHashes'][$type] = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
}
$path = '/feasibility/employee-stage4-lifecycle-baseline.json';
if (($argv[1] ?? '') === 'capture') {
    file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT));
    echo "Captured synthetic lifecycle baseline.\n";
    exit;
}
$baseline = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$same = json_decode(json_encode($state), true) === $baseline;
$result = ['status' => $same ? 'PASS' : 'FAIL', 'test' => 'stage4-replacement-rebuild-retains-profiles-ledger-settings-roles-and-originals', 'state' => $state];
file_put_contents('/feasibility/employee-stage4-lifecycle-results.json', json_encode($result, JSON_PRETTY_PRINT));
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($same ? 0 : 1);
