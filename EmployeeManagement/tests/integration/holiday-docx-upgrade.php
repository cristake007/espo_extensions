<?php

declare(strict_types=1);

if (getenv('EMPLOYEE_TEST_LAB') !== 'employee-docx-0813-lab') {
    throw new RuntimeException('Isolated Employee Management 0.8.13 laboratory only.');
}
chdir('/var/www/html');
require 'bootstrap.php';
$application = new Espo\Core\Application();
$application->setupSystemUser();
$container = $application->getContainer();
$config = $container->get('config');
if ($config->get('siteUrl') !== 'http://127.0.0.1:18089' || $config->get('version') !== '10.0.1') {
    throw new RuntimeException('Wrong runtime.');
}
$em = $container->get('entityManager');
$factory = $container->get('injectableFactory');
$storage = $factory->create(Espo\Core\FileStorage\Manager::class);
$phase = $argv[1] ?? '';
if (!in_array($phase, ['seed-capture', 'verify'], true)) { throw new RuntimeException('Use seed-capture or verify.'); }
$version = $em->getRDBRepository('Extension')->where(['name' => 'Employee Management', 'isInstalled' => true])->findOne()?->get('version');
$expected = $phase === 'seed-capture' ? '0.8.12' : '0.8.13';
if ($version !== $expected) { throw new RuntimeException("Expected $expected, got " . ($version ?? 'none')); }

function createEntity(string $type, array $values): Espo\ORM\Entity
{
    global $em;
    $entity = $em->getNewEntity($type);
    $entity->set($values);
    $em->saveEntity($entity);
    return $entity;
}

$marker = 'employee-docx-0813-preservation';
if ($phase === 'seed-capture' && !$em->getRDBRepository('User')->where(['userName' => $marker])->findOne()) {
    $writer = $factory->create(Espo\Core\Utils\Config\ConfigWriter::class);
    $writer->setMultiple([
        'employeeHolidayAutomaticAnnualGrantEnabled' => true,
        'employeeHolidayResetDate' => '2027-01-01',
    ]);
    $writer->save();
    $user = createEntity('User', [
        'userName' => $marker, 'firstName' => 'Upgrade', 'lastName' => 'Preservation',
        'type' => 'regular', 'isActive' => true,
    ]);
    $profile = createEntity('EmployeeHolidayProfile', [
        'name' => $marker, 'assignedUserId' => $user->getId(), 'assignedUserName' => $user->get('name'),
        'annualEntitlement' => 21.0, 'balance' => 0.0, 'nextResetDate' => '2027-01-01',
        'calendarColor' => '#4F8A8B', 'isInitialized' => true, 'resetPending' => false,
    ]);
    $request = createEntity('EmployeeHolidayRequest', [
        'name' => $marker, 'assignedUserId' => $user->getId(), 'assignedUserName' => $user->get('name'),
        'profileId' => $profile->getId(), 'profileName' => $profile->get('name'),
        'dateStartDate' => '2026-12-24', 'dateEndDate' => '2027-01-09', 'days' => 8,
        'status' => 'Approved', 'description' => 'Upgrade preservation fixture',
        'accountingKey' => hash('sha256', $marker), 'accountingRevision' => 2,
        'calculationSnapshot' => (object) ['totalDays' => 8, 'monthlyDays' => (object) ['2026-12' => 5, '2027-01' => 3]],
        'approvalSnapshot' => (object) ['version' => 1,
            'templateHash' => hash_file('sha256', 'custom/Espo/Modules/EmployeeManagement/Resources/templates/holiday-request.docx'),
            'documents' => [
            (object) ['month' => '2026-12', 'filename' => 'Cerere concediu - decembrie 2026.docx', 'values' => (object) ['totalDays' => '21', 'balanceBefore' => '8', 'balanceAfter' => '3']],
            (object) ['month' => '2027-01', 'filename' => 'Cerere concediu - ianuarie 2027.docx', 'values' => (object) ['totalDays' => '21', 'balanceBefore' => '3', 'balanceAfter' => '0']],
        ]],
        'approvalManifest' => (object) [], 'approvalState' => 'Completed',
    ]);
    createEntity('EmployeeHolidayLedger', [
        'name' => $marker, 'profileId' => $profile->getId(), 'profileName' => $profile->get('name'),
        'requestId' => $request->getId(), 'requestName' => $request->get('name'),
        'assignedUserId' => $user->getId(), 'assignedUserName' => $user->get('name'),
        'type' => 'holidayBooked', 'delta' => -8.0, 'balanceBefore' => 8.0, 'balanceAfter' => 0.0,
        'entitlementBefore' => 21.0, 'entitlementAfter' => 21.0,
        'resetDateBefore' => '2027-01-01', 'resetDateAfter' => '2027-01-01',
        'actorId' => 'system', 'actorName' => 'System', 'effectiveDate' => '2026-10-21',
        'idempotencyKey' => $marker . ':ledger', 'operationHash' => hash('sha256', $marker . ':ledger'),
    ]);
    createEntity('EmployeeAttendanceRecord', [
        'name' => $marker, 'assignedUserId' => $user->getId(), 'assignedUserName' => $user->get('name'),
        'date' => '2026-10-20', 'status' => 'AtWork', 'source' => 'Manager',
        'markedAt' => '2026-10-20 08:00:00', 'revision' => 1, 'correctionReason' => null,
    ]);
    $attachment = $em->getNewEntity('Attachment');
    $attachment->setName('Cerere concediu - ianuarie 2027.docx')
        ->setType('application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->setRole(Espo\Entities\Attachment::ROLE_ATTACHMENT)->setSize(22)
        ->setParent($request)->setTargetField('approvalDocuments');
    $em->saveEntity($attachment);
    $storage->putContents($attachment, 'legacy-docx-bytes-0813');
    $request->set('approvalDocumentsIds', [$attachment->getId()]);
    $em->saveEntity($request);
}

$fields = [
    'EmployeeAttendanceRecord' => ['id','assignedUserId','date','status','source','markedAt','revision','correctionReason'],
    'EmployeeHolidayRequest' => ['id','assignedUserId','profileId','dateStartDate','dateEndDate','days','status','description','accountingKey','accountingRevision','calculationSnapshot','approvalSnapshot','approvalManifest','approvalDocumentsIds'],
    'EmployeeHolidayProfile' => ['id','assignedUserId','annualEntitlement','balance','nextResetDate','calendarColor','isInitialized','resetPending'],
    'EmployeeHolidayLedger' => ['id','profileId','requestId','assignedUserId','type','delta','balanceBefore','balanceAfter','entitlementBefore','entitlementAfter','resetDateBefore','resetDateAfter','idempotencyKey','operationHash'],
    'EmployeeAbsence' => ['id','name','dateStart','dateEnd','sourceId','assignedUserId'],
];
$state = ['counts' => [], 'hashes' => []];
foreach ($fields as $type => $names) {
    $rows = [];
    foreach ($em->getRDBRepository($type)->order('id')->find() as $entity) {
        $row = [];
        foreach ($names as $name) { $row[$name] = $entity->get($name); }
        $rows[] = $row;
    }
    $state['counts'][$type] = count($rows);
    $state['hashes'][$type] = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
}
$attachments = [];
foreach ($em->getRDBRepository('Attachment')->where(['parentType' => 'EmployeeHolidayRequest'])->order('id')->find() as $attachment) {
    $attachments[] = [
        'id' => $attachment->getId(), 'parentId' => $attachment->get('parentId'),
        'field' => $attachment->get('field'), 'name' => $attachment->get('name'),
        'size' => $attachment->get('size'),
        'sha256' => $storage->exists($attachment) ? hash('sha256', $storage->getContents($attachment)) : null,
    ];
}
$state['counts']['Attachments'] = count($attachments);
$state['hashes']['Attachments'] = hash('sha256', json_encode($attachments, JSON_THROW_ON_ERROR));
$path = '/tmp/employee-docx-0813-upgrade-baseline.json';
if ($phase === 'seed-capture') {
    file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode(['status' => 'PASS', 'phase' => $phase, 'version' => $version, 'state' => $state], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}
$before = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$differences = [];
foreach (['counts', 'hashes'] as $section) foreach ($state[$section] as $name => $value) {
    if (($before[$section][$name] ?? null) !== $value) { $differences[] = "$section.$name"; }
}
$internalTables = [
    'EmployeeHolidayDocument' => $em->getRDBRepository('EmployeeHolidayDocument')->count(),
    'EmployeeHolidayDocumentVerificationLog' => $em->getRDBRepository('EmployeeHolidayDocumentVerificationLog')->count(),
];
$result = [
    'status' => $differences === [] && $internalTables === ['EmployeeHolidayDocument' => 0, 'EmployeeHolidayDocumentVerificationLog' => 0] ? 'PASS' : 'FAIL',
    'fromVersion' => '0.8.12', 'toVersion' => $version, 'differences' => $differences,
    'before' => $before, 'after' => $state, 'newInternalRecordCounts' => $internalTables,
];
file_put_contents('/tmp/employee-docx-0813-upgrade-results.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($result['status'] === 'PASS' ? 0 : 1);
