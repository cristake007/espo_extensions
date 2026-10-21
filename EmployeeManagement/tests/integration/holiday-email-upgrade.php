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
$phase = $argv[1] ?? '';
if (!in_array($phase, ['capture', 'verify', 'replacement-capture', 'replacement-verify'], true)) {
    throw new RuntimeException('Use capture, verify, replacement-capture or replacement-verify.');
}
$extensionVersion = $em->getRDBRepository('Extension')
    ->where(['name' => 'Employee Management', 'isInstalled' => true])
    ->findOne()?->get('version');
$expectedVersion = $phase === 'capture' ? '0.8.11' : '0.8.12';
if ($extensionVersion !== $expectedVersion) {
    throw new RuntimeException(sprintf('Expected Employee Management %s, got %s.', $expectedVersion, $extensionVersion ?? 'none'));
}

$fieldsByType = [
    'EmployeeAttendanceRecord' => ['id', 'assignedUserId', 'date', 'status', 'source', 'markedAt', 'revision', 'correctionReason', 'createdById', 'modifiedById'],
    'EmployeeAttendanceWorkSchedule' => ['id', 'assignedUserId', 'remote', 'cic', 'cicStartDate', 'cicEndDate', 'effectiveFrom', 'createdById', 'modifiedById'],
    'EmployeeAttendanceMonth' => ['id', 'month', 'norms', 'policies', 'frozen', 'frozenAt', 'frozenById', 'revision'],
    'EmployeeHolidayRequest' => ['id', 'assignedUserId', 'status', 'dateStartDate', 'dateEndDate', 'description', 'days', 'accountingKey', 'accountingRevision', 'calculationSnapshot', 'operationReceipts', 'approvalSnapshot', 'approvalManifest', 'approvalDocumentsIds', 'approvalState', 'approvalError'],
    'EmployeeHolidayProfile' => ['id', 'assignedUserId', 'annualEntitlement', 'balance', 'nextResetDate', 'calendarColor', 'isInitialized', 'resetPending'],
    'EmployeeHolidayLedger' => ['id', 'profileId', 'assignedUserId', 'type', 'delta', 'balanceBefore', 'balanceAfter', 'actorId', 'idempotencyKey', 'operationHash'],
    'EmployeeAbsence' => ['id', 'name', 'dateStart', 'dateEnd', 'sourceId', 'assignedUserId', 'deleted'],
];
$state = ['counts' => [], 'hashes' => []];
foreach ($fieldsByType as $type => $fields) {
    $rows = [];
    $repository = $em->getRDBRepository($type);
    $entities = $type === 'EmployeeAbsence'
        ? $repository->clone($em->getQueryBuilder()->select()->from($type)->withDeleted()->order('id')->build())->find()
        : $repository->order('id')->find();
    foreach ($entities as $entity) {
        $row = [];
        foreach ($fields as $field) {
            $row[$field] = $entity->get($field);
        }
        $rows[] = $row;
    }
    $state['counts'][$type] = count($rows);
    $state['hashes'][$type] = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
}

$storage = $container->get('injectableFactory')->create(Espo\Core\FileStorage\Manager::class);
$documents = [];
foreach ($em->getRDBRepository('Attachment')->where(['parentType' => 'EmployeeHolidayRequest'])->order('id')->find() as $attachment) {
    $documents[] = [
        'id' => $attachment->getId(),
        'parentId' => $attachment->get('parentId'),
        'field' => $attachment->get('field'),
        'name' => $attachment->get('name'),
        'type' => $attachment->get('type'),
        'size' => $attachment->get('size'),
        'sha256' => $storage->exists($attachment) ? hash('sha256', $storage->getContents($attachment)) : null,
    ];
}
$state['counts']['approvalDocuments'] = count($documents);
$state['hashes']['approvalDocuments'] = hash('sha256', json_encode($documents, JSON_THROW_ON_ERROR));
$state['config'] = [
    'employeeAttendanceArchiveStartMonth' => $config->get('employeeAttendanceArchiveStartMonth'),
    'employeeAttendanceEditablePastMonths' => $config->get('employeeAttendanceEditablePastMonths'),
    'employeeAttendanceAllowIncompleteExports' => $config->get('employeeAttendanceAllowIncompleteExports'),
    'employeeHolidayAutomaticAnnualGrantEnabled' => $config->get('employeeHolidayAutomaticAnnualGrantEnabled'),
    'calendarEntityList' => $config->get('calendarEntityList'),
    'tabList' => $config->get('tabList'),
];
$state = json_decode(json_encode($state, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
$replacement = str_starts_with($phase, 'replacement-');
$baselinePath = $replacement
    ? '/feasibility/employee-holiday-email-0.8.12-replacement-baseline.json'
    : '/feasibility/employee-holiday-email-0.8.12-upgrade-baseline.json';

if (in_array($phase, ['capture', 'replacement-capture'], true)) {
    file_put_contents($baselinePath, json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode(['status' => 'PASS', 'phase' => $phase, 'version' => $extensionVersion, 'state' => $state], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

$baseline = json_decode(file_get_contents($baselinePath), true, 512, JSON_THROW_ON_ERROR);
$differences = [];
foreach ($state as $section => $values) {
    foreach ($values as $name => $value) {
        if (($baseline[$section][$name] ?? null) !== $value) {
            $differences[] = $section . '.' . $name;
        }
    }
}
$result = [
    'status' => $differences === [] ? 'PASS' : 'FAIL',
    'phase' => $phase,
    'fromVersion' => $replacement ? '0.8.12' : '0.8.11',
    'toVersion' => $extensionVersion,
    'differences' => $differences,
    'before' => $baseline,
    'after' => $state,
];
file_put_contents(
    $replacement
        ? '/feasibility/employee-holiday-email-0.8.12-replacement-results.json'
        : '/feasibility/employee-holiday-email-0.8.12-upgrade-results.json',
    json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
);
echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($differences === [] ? 0 : 1);
