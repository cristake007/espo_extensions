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
    'EmployeeAttendanceRecord', 'EmployeeHolidayRequest', 'EmployeeHolidayProfile', 'EmployeeHolidayLedger', 'WorkingTimeCalendar', 'WorkingTimeRange'] as $type) {
    $state['counts'][$type] = $em->getRDBRepository($type)->count();
}
foreach (['Attendance Management', 'Holiday Management', 'Zile Sărbătoare'] as $name) {
    $record = $em->getRDBRepository('Extension')->where(['name' => $name, 'isInstalled' => true])->findOne();
    $state['originalExtensions'][$name] = $record?->get('version');
}
$path = '/feasibility/employee-stage1-lifecycle-baseline.json';
if (($argv[1] ?? '') === 'capture') {
    file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT));
    echo "Captured synthetic lifecycle baseline.\n";
    exit;
}
$baseline = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$same = json_decode(json_encode($state), true) === $baseline;
$result = ['status' => $same ? 'PASS' : 'FAIL', 'test' => 'replacement-rebuild-retains-records-config-and-original-modules', 'state' => $state];
file_put_contents('/feasibility/employee-stage1-lifecycle-results.json', json_encode($result, JSON_PRETTY_PRINT));
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($same ? 0 : 1);
