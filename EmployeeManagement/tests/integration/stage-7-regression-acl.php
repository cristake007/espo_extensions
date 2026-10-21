<?php
// Stage7: the private Pending-only request Calendar source is now registered; preserve all actual ACL assertions.

declare(strict_types=1);

/** Run only inside the retained, synthetic feasibility lab. Never on company data. */
if (getenv('EMPLOYEE_TEST_LAB') !== 'employee-feasibility-20261020') {
    throw new RuntimeException('Explicit isolated-lab opt-in required.');
}

chdir('/var/www/html');
require 'bootstrap.php';
$app = new Espo\Core\Application();
$container = $app->getContainer();
$em = $container->get('entityManager');
$factory = $container->get('injectableFactory');
$config = $container->get('config');

if ($config->get('version') !== '10.0.1' || $config->get('siteUrl') !== 'http://127.0.0.1:18089') {
    throw new RuntimeException('This is not the documented synthetic lab.');
}

$password = getenv('EMPLOYEE_TEST_PASSWORD') ?: throw new RuntimeException('Test password required.');
$adminPassword = getenv('EMPLOYEE_TEST_ADMIN_PASSWORD') ?: throw new RuntimeException('Synthetic admin password required.');
$admin = $em->getRDBRepository('User')->where(['userName' => 'admin'])->findOne();
$container->getByClass(Espo\Core\ApplicationUser::class)->setUser($admin);
$entities = ['EmployeeAttendanceRecord', 'EmployeeHolidayRequest', 'EmployeeHolidayProfile', 'EmployeeHolidayLedger'];
$failures = 0;
$assertions = 0;
$results = [];

function expect(string $test, bool $ok, mixed $observed = null): void
{
    global $failures, $assertions, $results;
    $assertions++;
    if (!$ok) { $failures++; }
    $row = ['test' => $test, 'status' => $ok ? 'PASS' : 'FAIL', 'observed' => $observed];
    $results[] = $row;
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

function http(string $name, string $method, string $path, ?array $data = null): array
{
    global $password, $adminPassword;
    $pw = $name === 'admin' ? $adminPassword : $password;
    $curl = curl_init('http://127.0.0.1/api/v1/' . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Espo-Authorization: ' . base64_encode($name . ':' . $pw)],
        CURLOPT_USERPWD => $name . ':' . $pw, CURLOPT_TIMEOUT => 25]);
    if ($data !== null) { curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data)); }
    $body = curl_exec($curl);
    if ($body === false) { throw new RuntimeException(curl_error($curl)); }
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return ['status' => $status, 'body' => json_decode($body, true), 'raw' => $body];
}

function fixture(string $type, array $find, array $values): Espo\ORM\Entity
{
    global $em;
    $record = $em->getRDBRepository($type)->where($find)->findOne() ?? $em->getNewEntity($type);
    $record->set($values);
    // Deliberately synthetic ORM fixture, not a supported business write endpoint.
    $em->saveEntity($record);
    return $record;
}

$originalCounts = [];
foreach (['AttendanceRecord', 'AttendanceWorkSchedule', 'HolidayRequest', 'HolidayProfile', 'HolidayLedger'] as $type) {
    $originalCounts[$type] = $em->getRDBRepository($type)->count();
}

$users = [];
$hash = $factory->create(Espo\Core\Utils\PasswordHash::class)->hash($password);
foreach (['manager' => 'all', 'owner' => 'own', 'colleague' => 'own', 'denied' => 'no'] as $key => $level) {
    $data = ['EmployeeManagement' => $key !== 'denied', 'EmployeeAdministration' => $key === 'manager'];
    foreach ($entities as $type) {
        // Intentionally overgrant writes: installed access checkers must still deny them.
        $data[$type] = (object) ['create' => 'yes', 'read' => $level, 'edit' => 'all', 'delete' => 'all'];
    }
    $role = fixture('Role', ['name' => 'Employee Stage 1 ' . $key], ['name' => 'Employee Stage 1 ' . $key, 'data' => (object) $data]);
    $name = 'em_stage1_' . $key;
    $users[$key] = fixture('User', ['userName' => $name], ['userName' => $name, 'firstName' => 'Synthetic',
        'lastName' => 'EM ' . $key, 'type' => 'regular', 'isActive' => true, 'password' => $hash, 'rolesIds' => [$role->getId()]]);
}
// A regular user without any roles must remain denied by strictDefault.
$users['unconfigured'] = fixture('User', ['userName' => 'em_stage1_unconfigured'], ['userName' => 'em_stage1_unconfigured',
    'firstName' => 'Synthetic', 'lastName' => 'EM no roles', 'type' => 'regular', 'isActive' => true, 'password' => $hash, 'rolesIds' => []]);

foreach (['owner', 'manager', 'colleague', 'denied', 'unconfigured'] as $key) {
    $auth = http('em_stage1_' . $key, 'GET', 'App/user');
    expect('authenticated-' . $key, $auth['status'] === 200, $auth['status']);
}

$records = [];
foreach (['owner', 'colleague'] as $key) {
    $uid = $users[$key]->getId();
    $profile = fixture('EmployeeHolidayProfile', ['assignedUserId' => $uid], ['name' => 'EM fixture profile ' . $key,
        'assignedUserId' => $uid, 'balance' => 20, 'annualEntitlement' => 21, 'nextResetDate' => '2027-01-01', 'isInitialized' => true]);
    $request = fixture('EmployeeHolidayRequest', ['accountingKey' => 'em-stage1-' . $key], ['name' => 'EM fixture holiday ' . $key,
        'assignedUserId' => $uid, 'profileId' => $profile->getId(), 'dateStartDate' => '2026-11-03', 'dateEndDate' => '2026-11-03',
        'status' => 'Pending', 'days' => 1, 'accountingKey' => 'em-stage1-' . $key, 'description' => 'EM PRIVATE NOTE ' . $key]);
    $attendance = fixture('EmployeeAttendanceRecord', ['assignedUserId' => $uid, 'date' => '2026-11-02'], ['name' => 'EM fixture attendance ' . $key,
        'assignedUserId' => $uid, 'date' => '2026-11-02', 'status' => 'AtWork', 'source' => 'Self', 'markedAt' => '2026-11-02 08:00:00']);
    $ledger = fixture('EmployeeHolidayLedger', ['idempotencyKey' => 'em-stage1-' . $key], ['name' => 'EM fixture ledger ' . $key,
        'assignedUserId' => $uid, 'profileId' => $profile->getId(), 'requestId' => $request->getId(), 'actorId' => $uid,
        'type' => 'holidayBooked', 'delta' => -1, 'balanceBefore' => 21, 'balanceAfter' => 20, 'entitlementBefore' => 21,
        'entitlementAfter' => 21, 'effectiveDate' => '2026-11-03', 'idempotencyKey' => 'em-stage1-' . $key]);
    $records[$key] = ['EmployeeAttendanceRecord' => $attendance->getId(), 'EmployeeHolidayRequest' => $request->getId(),
        'EmployeeHolidayProfile' => $profile->getId(), 'EmployeeHolidayLedger' => $ledger->getId()];
}

$metadata = $container->get('metadata');
foreach ($entities as $type) {
    expect('metadata-owner-' . $type, $metadata->get(['scopes', $type, 'module']) === 'EmployeeManagement');
    expect('ownership-' . $type, $metadata->get(['entityDefs', $type, 'links', 'assignedUser', 'entity']) === 'User'
        && $metadata->get(['entityDefs', $type, 'fields', 'user']) === null);
    expect('stage7-native-calendar-registration-' . $type, $metadata->get(['scopes', $type, 'calendar']) === ($type === 'EmployeeHolidayRequest')); 
    $own = http('em_stage1_owner', 'GET', $type . '/' . $records['owner'][$type]);
    expect('own-read-' . $type, $own['status'] === 200, $own['status']);
    foreach (['colleague', 'denied', 'unconfigured'] as $key) {
        $r = http('em_stage1_' . $key, 'GET', $type . '/' . $records['owner'][$type]);
        expect($key . '-private-id-' . $type, $r['status'] === 403, $r['status']);
    }
    $r = http('em_stage1_manager', 'GET', $type . '/' . $records['owner'][$type]);
    expect('manager-read-' . $type, $r['status'] === 200, $r['status']);
    $list = http('em_stage1_owner', 'GET', $type . '?maxSize=100');
    expect('own-list-' . $type, $list['status'] === 200 && count($list['body']['list'] ?? []) === 1
        && ($list['body']['list'][0]['assignedUserId'] ?? '') === $users['owner']->getId(),
        ['status' => $list['status'], 'count' => count($list['body']['list'] ?? [])]);
    foreach (['owner', 'manager', 'admin'] as $key) {
        $name = $key === 'admin' ? 'admin' : 'em_stage1_' . $key;
        foreach ([['POST', $type, ['name' => 'Forbidden API insert']],
            ['PUT', $type . '/' . $records['owner'][$type], ['name' => 'Forbidden API change']],
            ['DELETE', $type . '/' . $records['owner'][$type], null]] as [$method, $path, $payload]) {
            $r = http($name, $method, $path, $payload);
            expect('no-mutation-' . $key . '-' . $method . '-' . $type, $r['status'] === 403, $r['status']);
        }
    }
    foreach (['em_stage1_manager', 'admin'] as $name) {
        foreach (['update', 'delete'] as $action) {
            $r = http($name, 'POST', 'MassAction', ['entityType' => $type, 'action' => $action,
                'params' => ['ids' => [$records['owner'][$type]]], 'data' => ['attributes' => ['name' => 'Forbidden mass change']]]);
            expect('no-mass-' . $action . '-' . $name . '-' . $type, $r['status'] === 403, $r['status']);
        }
        $r = http($name, 'POST', 'Export', ['entityType' => $type, 'ids' => [$records['owner'][$type]], 'format' => 'csv']);
        expect('no-export-' . $name . '-' . $type, $r['status'] === 403, $r['status']);
    }
}

foreach (['owner', 'manager', 'admin', 'denied', 'unconfigured'] as $key) {
    $name = $key === 'admin' ? 'admin' : 'em_stage1_' . $key;
    foreach (['workspace', 'administration'] as $area) {
        $r = http($name, 'GET', 'EmployeeManagement/' . $area . '/status');
        $expected = ($area === 'workspace' && in_array($key, ['owner', 'manager'], true)) || ($area === 'administration' && $key === 'manager') ? 200 : 403;
        expect('workspace-' . $area . '-' . $key, $r['status'] === $expected, $r['status']);
    }
}

$profileId = $records['owner']['EmployeeHolidayProfile'];
$requestId = $records['owner']['EmployeeHolidayRequest'];
foreach (['manager', 'admin'] as $key) {
    foreach (['POST', 'DELETE'] as $method) {
        $r = http($key === 'admin' ? 'admin' : 'em_stage1_manager', $method, 'EmployeeHolidayProfile/' . $profileId . '/holidayRequests', ['id' => $requestId]);
        expect('no-relationship-mutation-' . $key . '-' . $method, $r['status'] === 403, $r['status']);
    }
}
$r = http('em_stage1_colleague', 'GET', 'EmployeeHolidayProfile/' . $profileId . '/holidayRequests');
expect('no-private-related-list', $r['status'] === 403, $r['status']);
$r = http('em_stage1_colleague', 'GET', 'EmployeeHolidayRequest/' . $requestId);
expect('P2-private-note-not-returned', $r['status'] === 403 && !str_contains($r['raw'], 'EM PRIVATE NOTE'), $r['status']);

// An attachment on the private request must inherit native parent ACL.
$request = $em->getEntityById('EmployeeHolidayRequest', $requestId);
$attachment = $em->getRDBRepository('Attachment')->where(['parentId' => $requestId, 'name' => 'em-stage1-private.docx'])->findOne();
if (!$attachment) {
    $attachment = $em->getNewEntity('Attachment');
    $attachment->setName('em-stage1-private.docx')->setType('application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->setRole(Espo\Entities\Attachment::ROLE_ATTACHMENT)->setSize(4)->setParent($request)->setTargetField('approvalDocuments');
    $em->saveEntity($attachment);
    $factory->create(Espo\Core\FileStorage\Manager::class)->putContents($attachment, 'TEST');
}
foreach (['owner' => 200, 'manager' => 200, 'colleague' => 403, 'denied' => 403] as $key => $expected) {
    $r = http('em_stage1_' . $key, 'GET', 'Attachment/file/' . $attachment->getId());
    expect('private-download-' . $key, $r['status'] === $expected, $r['status']);
}

foreach ($originalCounts as $type => $count) {
    expect('original-data-unchanged-' . $type, $em->getRDBRepository($type)->count() === $count, $count);
}
file_put_contents('/feasibility/employee-stage1-state.json', json_encode(['records' => $records, 'users' => array_map(fn($u) => $u->getId(), $users)], JSON_PRETTY_PRINT));
file_put_contents('/feasibility/employee-stage1-api-results.json', json_encode(['results' => $results, 'assertions' => $assertions, 'failures' => $failures], JSON_PRETTY_PRINT));
echo json_encode(['assertions' => $assertions, 'failures' => $failures]) . PHP_EOL;
exit($failures ? 1 : 0);
