<?php

declare(strict_types=1);

if (getenv('EMPLOYEE_TEST_LAB') !== 'employee-ledger-0814-lab') {
    throw new RuntimeException('Isolated Employee Management 0.8.14 laboratory only.');
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
$storage = $factory->create(Espo\Core\FileStorage\Manager::class);
$phase = $argv[1] ?? '';

if (!in_array($phase, ['capture', 'verify', 'capture-correction', 'verify-correction'], true)) {
    throw new RuntimeException('Use capture, verify, capture-correction or verify-correction.');
}

$version = $em->getRDBRepository('Extension')
    ->where(['name' => 'Employee Management', 'isInstalled' => true])
    ->findOne()?->get('version');
$expected = $phase === 'capture' ? '0.8.13' : '0.8.14';

if ($version !== $expected) {
    throw new RuntimeException("Expected $expected, got " . ($version ?? 'none'));
}

$fields = [
    'EmployeeHolidayLedger' => [
        'id', 'name', 'assignedUserId', 'profileId', 'requestId', 'type', 'delta',
        'balanceBefore', 'balanceAfter', 'entitlementBefore', 'entitlementAfter',
        'resetDateBefore', 'resetDateAfter', 'actorId', 'reason', 'effectiveDate',
        'createdAt', 'idempotencyKey', 'operationHash',
    ],
    'EmployeeHolidayRequest' => [
        'id', 'assignedUserId', 'profileId', 'dateStartDate', 'dateEndDate', 'days',
        'status', 'accountingKey', 'accountingRevision', 'approvalDocumentsIds',
        'supersededApprovalDocumentsIds',
    ],
    'EmployeeHolidayProfile' => [
        'id', 'assignedUserId', 'annualEntitlement', 'balance', 'nextResetDate',
        'isInitialized', 'resetPending',
    ],
    'EmployeeHolidayDocument' => [
        'id', 'requestId', 'attachmentId', 'month', 'version', 'status', 'sha256',
    ],
    'Role' => ['id', 'name', 'data', 'fieldData'],
];
$state = ['counts' => [], 'hashes' => []];

foreach ($fields as $type => $names) {
    $rows = [];

    foreach ($em->getRDBRepository($type)->order('id')->find() as $entity) {
        $row = [];

        foreach ($names as $name) {
            $row[$name] = $entity->get($name);
        }

        $rows[] = $row;
    }

    $state['counts'][$type] = count($rows);
    $state['hashes'][$type] = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
}

$attachments = [];

foreach ($em->getRDBRepository('Attachment')->where(['parentType' => 'EmployeeHolidayRequest'])->order('id')->find() as $attachment) {
    $attachments[] = [
        'id' => $attachment->getId(),
        'parentId' => $attachment->get('parentId'),
        'field' => $attachment->get('field'),
        'name' => $attachment->get('name'),
        'size' => $attachment->get('size'),
        'sha256' => $storage->exists($attachment)
            ? hash('sha256', $storage->getContents($attachment))
            : null,
    ];
}

$state['counts']['HolidayAttachments'] = count($attachments);
$state['hashes']['HolidayAttachments'] = hash('sha256', json_encode($attachments, JSON_THROW_ON_ERROR));
$correction = str_ends_with($phase, '-correction');
$path = $correction
    ? '/tmp/employee-holiday-ledger-0.8.14-correction-baseline.json'
    : '/feasibility/employee-holiday-ledger-0.8.14-upgrade-baseline.json';

if (str_starts_with($phase, 'capture')) {
    file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode(['status' => 'PASS', 'phase' => $phase, 'version' => $version, 'state' => $state]) . PHP_EOL;
    exit;
}

$before = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$differences = [];

foreach (['counts', 'hashes'] as $section) {
    foreach ($state[$section] as $name => $value) {
        if (($before[$section][$name] ?? null) !== $value) {
            $differences[] = "$section.$name";
        }
    }
}

$result = [
    'status' => $differences === [] ? 'PASS' : 'FAIL',
    'fromVersion' => $correction ? '0.8.14' : '0.8.13',
    'toVersion' => $version,
    'differences' => $differences,
    'before' => $before,
    'after' => $state,
];
file_put_contents(
    $correction
        ? '/tmp/employee-holiday-ledger-0.8.14-correction-results.json'
        : '/feasibility/employee-holiday-ledger-0.8.14-upgrade-results.json',
    json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
);
echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($differences === [] ? 0 : 1);
