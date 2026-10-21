<?php

declare(strict_types=1);

if (getenv('EMPLOYEE_TEST_LAB') !== 'employee-feasibility-20261020') {
    throw new RuntimeException('Isolated Employee Management laboratory only.');
}

chdir('/var/www/html');
require 'bootstrap.php';
$application = new Espo\Core\Application();
$application->setupSystemUser();
$container = $application->getContainer();
$config = $container->get('config');

if ($config->get('version') !== '10.0.1' || $config->get('siteUrl') !== 'http://127.0.0.1:18089') {
    throw new RuntimeException('Wrong runtime.');
}

$em = $container->get('entityManager');
$factory = $container->get('injectableFactory');
$phase = $argv[1] ?? '';
$statePath = '/feasibility/employee-holiday-workspaces-0.8.15-state.json';
$managerRoleIds = ['6ad6f062b6151744b', '6ad6f062bac762634'];
$employeeRoleId = '6ad6f062b7b1b9a07';

if ($phase === 'setup') {
    if (is_file($statePath)) {
        throw new RuntimeException('Workspace test state already exists; restore it first.');
    }

    $state = ['tabList' => $config->get('tabList', []), 'roles' => [], 'temporary' => []];

    foreach ([...$managerRoleIds, $employeeRoleId] as $roleId) {
        $role = $em->getEntityById('Role', $roleId);
        if (!$role) {
            throw new RuntimeException("Missing fixture Role $roleId.");
        }
        $state['roles'][$roleId] = $role->get('data');
        $data = (array) $role->get('data');
        $data['EmployeeHolidayManagement'] = in_array($roleId, $managerRoleIds, true);
        $role->set('data', (object) $data);
        $em->saveEntity($role);
    }

    $tabs = $state['tabList'];
    if (!in_array('EmployeeHolidayManagement', $tabs, true)) {
        $tabs[] = 'EmployeeHolidayManagement';
    }
    $writer = $factory->create(Espo\Core\Utils\Config\ConfigWriter::class);
    $writer->set('tabList', $tabs);
    $writer->save();

    $password = getenv('EMPLOYEE_TEST_PASSWORD') ?: throw new RuntimeException('Password required.');
    $hash = $factory->create(Espo\Core\Utils\PasswordHash::class)->hash($password);
    $reference = $em->getEntityById('User', '6ad6f062b6aadccf3');
    $tag = 'holiday_workspace_' . bin2hex(random_bytes(4));
    $definitions = [
        'approverOnly' => [
            'EmployeeManagement' => true,
            'EmployeeHolidayManagement' => true,
            'EmployeeHolidayApproval' => true,
            'EmployeeHolidayBooking' => false,
            'EmployeeHolidayRequestManagement' => false,
            'EmployeeHolidayRequest' => (object) ['create' => 'no', 'read' => 'all', 'edit' => 'no', 'delete' => 'no'],
        ],
        'bookingOnly' => [
            'EmployeeManagement' => true,
            'EmployeeHolidayManagement' => true,
            'EmployeeHolidayApproval' => false,
            'EmployeeHolidayBooking' => true,
            'EmployeeHolidayRequestManagement' => true,
            'EmployeeHolidayRequest' => (object) ['create' => 'yes', 'read' => 'all', 'edit' => 'all', 'delete' => 'all'],
            'EmployeeHolidayProfile' => (object) ['create' => 'no', 'read' => 'all', 'edit' => 'no', 'delete' => 'no'],
        ],
    ];

    foreach ($definitions as $kind => $data) {
        $role = $em->getNewEntity('Role');
        $role->set(['name' => "$tag $kind", 'data' => (object) $data]);
        $em->saveEntity($role);
        $user = $em->getNewEntity('User');
        $user->set([
            'userName' => "{$tag}_{$kind}",
            'firstName' => 'Holiday Workspace',
            'lastName' => $kind,
            'type' => 'regular',
            'isActive' => true,
            'password' => $hash,
            'rolesIds' => [$role->getId()],
            'workingTimeCalendarId' => $reference?->get('workingTimeCalendarId'),
        ]);
        $em->saveEntity($user);
        $state['temporary'][$kind] = [
            'roleId' => $role->getId(),
            'userId' => $user->getId(),
            'userName' => $user->get('userName'),
        ];
    }

    $state['manager'] = 'functional_6ad6f062801b2_manager';
    $state['employee'] = 'functional_6ad6f062801b2_employee';
    file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode(['status' => 'PASS', 'phase' => 'setup', 'temporary' => $state['temporary']]) . PHP_EOL;
    exit;
}

if ($phase === 'restore') {
    $state = json_decode(file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
    foreach ($state['temporary'] as $item) {
        $user = $em->getEntityById('User', $item['userId']);
        if ($user) {
            $em->removeEntity($user);
        }
        $role = $em->getEntityById('Role', $item['roleId']);
        if ($role) {
            $em->removeEntity($role);
        }
    }
    foreach ($state['roles'] as $roleId => $data) {
        $role = $em->getEntityById('Role', $roleId);
        $role->set('data', (object) $data);
        $em->saveEntity($role);
    }
    $writer = $factory->create(Espo\Core\Utils\Config\ConfigWriter::class);
    $writer->set('tabList', $state['tabList']);
    $writer->save();
    unlink($statePath);
    echo json_encode(['status' => 'PASS', 'phase' => 'restore']) . PHP_EOL;
    exit;
}

throw new RuntimeException('Use setup or restore.');
