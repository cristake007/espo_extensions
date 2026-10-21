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
$password = getenv('EMPLOYEE_TEST_PASSWORD') ?: throw new RuntimeException('Test password required.');
$results = [];
$failures = 0;

function check(string $name, bool $ok, mixed $observed = null): void
{
    global $results, $failures;
    $results[] = ['test' => $name, 'status' => $ok ? 'PASS' : 'FAIL', 'observed' => $observed];
    if (!$ok) { $failures++; }
    echo json_encode(end($results), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

function fixture(string $type, array $find, array $values): Espo\ORM\Entity
{
    global $em;
    $entity = $em->getRDBRepository($type)->where($find)->findOne() ?? $em->getNewEntity($type);
    $entity->set($values);
    $em->saveEntity($entity);
    return $entity;
}

function request(string $userName, string $method, string $path, ?array $data = null): array
{
    global $password;
    $curl = curl_init('http://127.0.0.1/api/v1/' . $path);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Espo-Authorization: ' . base64_encode($userName . ':' . $password),
        ],
    ]);
    if ($data !== null) { curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data)); }
    $raw = curl_exec($curl);
    if ($raw === false) { throw new RuntimeException(curl_error($curl)); }
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return ['status' => $status, 'body' => json_decode($raw, true), 'raw' => $raw];
}

function query(array $params): string
{
    return http_build_query($params);
}

$version = $em->getRDBRepository('Extension')
    ->where(['name' => 'Employee Management', 'isInstalled' => true])
    ->findOne()?->get('version');
check('installed version is 0.8.14', $version === '0.8.14', $version);

$hash = $factory->create(Espo\Core\Utils\PasswordHash::class)->hash($password);
$scope = static fn(string $read): object => (object) [
    'read' => $read, 'create' => 'no', 'edit' => 'no', 'delete' => 'no',
];
$roleOwn = fixture('Role', ['name' => 'Ledger 0.8.14 Own'], [
    'name' => 'Ledger 0.8.14 Own',
    'data' => (object) ['EmployeeManagement' => true, 'EmployeeHolidayLedger' => $scope('own')],
    'fieldData' => (object) [],
]);
$roleAll = fixture('Role', ['name' => 'Ledger 0.8.14 All'], [
    'name' => 'Ledger 0.8.14 All',
    'data' => (object) [
        'EmployeeManagement' => true,
        'EmployeeHolidayLedger' => $scope('all'),
        'EmployeeHolidayRequest' => $scope('all'),
        'User' => $scope('all'),
    ],
    'fieldData' => (object) [],
]);
$roleDenied = fixture('Role', ['name' => 'Ledger 0.8.14 No'], [
    'name' => 'Ledger 0.8.14 No',
    'data' => (object) ['EmployeeManagement' => true, 'EmployeeHolidayLedger' => $scope('no')],
    'fieldData' => (object) [],
]);
$roleFls = fixture('Role', ['name' => 'Ledger 0.8.14 FLS'], [
    'name' => 'Ledger 0.8.14 FLS',
    'data' => (object) ['EmployeeManagement' => true, 'EmployeeHolidayLedger' => $scope('all')],
    'fieldData' => (object) ['EmployeeHolidayLedger' => (object) ['reason' => (object) ['read' => 'no', 'edit' => 'no']]],
]);
$team = fixture('Team', ['name' => 'Ledger 0.8.14 Team'], [
    'name' => 'Ledger 0.8.14 Team', 'rolesIds' => [$roleAll->getId()],
]);
$users = [
    'owner' => fixture('User', ['userName' => 'ledger_0814_owner'], [
        'userName' => 'ledger_0814_owner', 'firstName' => 'Ledger', 'lastName' => 'Owner',
        'type' => 'regular', 'isActive' => true, 'password' => $hash, 'rolesIds' => [$roleOwn->getId()],
    ]),
    'colleague' => fixture('User', ['userName' => 'ledger_0814_colleague'], [
        'userName' => 'ledger_0814_colleague', 'firstName' => 'Ledger', 'lastName' => 'Colleague',
        'type' => 'regular', 'isActive' => true, 'password' => $hash, 'rolesIds' => [$roleOwn->getId()],
    ]),
    'manager' => fixture('User', ['userName' => 'ledger_0814_manager'], [
        'userName' => 'ledger_0814_manager', 'firstName' => 'Ledger', 'lastName' => 'Manager',
        'type' => 'regular', 'isActive' => true, 'password' => $hash, 'rolesIds' => [$roleAll->getId()],
    ]),
    'teamManager' => fixture('User', ['userName' => 'ledger_0814_team_manager'], [
        'userName' => 'ledger_0814_team_manager', 'firstName' => 'Ledger', 'lastName' => 'Team Manager',
        'type' => 'regular', 'isActive' => true, 'password' => $hash, 'rolesIds' => [], 'teamsIds' => [$team->getId()],
    ]),
    'denied' => fixture('User', ['userName' => 'ledger_0814_denied'], [
        'userName' => 'ledger_0814_denied', 'firstName' => 'Ledger', 'lastName' => 'Denied',
        'type' => 'regular', 'isActive' => true, 'password' => $hash, 'rolesIds' => [$roleDenied->getId()],
    ]),
    'fls' => fixture('User', ['userName' => 'ledger_0814_fls'], [
        'userName' => 'ledger_0814_fls', 'firstName' => 'Ledger', 'lastName' => 'FLS',
        'type' => 'regular', 'isActive' => true, 'password' => $hash, 'rolesIds' => [$roleFls->getId()],
    ]),
];

foreach ($users as $user) {
    $factory->create(Espo\Core\Acl\Cache\Clearer::class)->clearForUser($user);
}

$profiles = [];
foreach (['owner', 'colleague', 'manager'] as $kind) {
    $user = $users[$kind];
    $profiles[$kind] = fixture('EmployeeHolidayProfile', ['name' => 'ledger-0814-' . $kind], [
        'name' => 'ledger-0814-' . $kind,
        'assignedUserId' => $user->getId(), 'assignedUserName' => $user->get('name'),
        'annualEntitlement' => 21.0, 'balance' => 10.0, 'nextResetDate' => '2027-01-01',
        'isInitialized' => true, 'resetPending' => false,
    ]);
}
$requestEntity = fixture('EmployeeHolidayRequest', ['accountingKey' => 'ledger-0814-request'], [
    'name' => 'ledger-0814-request',
    'assignedUserId' => $users['owner']->getId(), 'assignedUserName' => $users['owner']->get('name'),
    'profileId' => $profiles['owner']->getId(), 'profileName' => $profiles['owner']->get('name'),
    'dateStartDate' => '2026-11-02', 'dateEndDate' => '2026-11-04', 'days' => 3,
    'status' => 'Approved', 'accountingKey' => 'ledger-0814-request', 'accountingRevision' => 1,
]);

$ledgerIds = [];
$entries = [
    ['owner', 'initialization', 5.0, 5.0, 10.0, '2026-10-21 08:00:00', 'Sold inițial', null],
    ['owner', 'holidayBooked', -3.0, 10.0, 7.0, '2026-10-21 09:00:00', 'Cerere aprobată', $requestEntity],
    ['owner', 'correction', 0.0, 7.0, 7.0, '2026-10-21 10:00:00', null, null],
    ['colleague', 'annualGrant', 21.0, 0.0, 21.0, '2026-10-21 11:00:00', 'Alocare anuală', null],
    ['manager', 'correction', 1.0, 9.0, 10.0, '2026-10-21 12:00:00', 'Corecție manager', null],
];

foreach ($entries as $index => [$kind, $type, $delta, $before, $after, $createdAt, $reason, $relatedRequest]) {
    $key = 'ledger-0814-' . $kind . '-' . $index;
    $user = $users[$kind];
    $entry = fixture('EmployeeHolidayLedger', ['idempotencyKey' => $key], [
        'name' => $key,
        'profileId' => $profiles[$kind]->getId(), 'profileName' => $profiles[$kind]->get('name'),
        'requestId' => $relatedRequest?->getId(), 'requestName' => $relatedRequest?->get('name'),
        'assignedUserId' => $user->getId(), 'assignedUserName' => $user->get('name'),
        'type' => $type, 'delta' => $delta, 'balanceBefore' => $before, 'balanceAfter' => $after,
        'entitlementBefore' => 21.0, 'entitlementAfter' => 21.0,
        'resetDateBefore' => '2027-01-01', 'resetDateAfter' => '2027-01-01',
        'actorId' => $users['manager']->getId(), 'actorName' => $users['manager']->get('name'),
        'reason' => $reason, 'effectiveDate' => '2026-10-21', 'createdAt' => $createdAt,
        'idempotencyKey' => $key, 'operationHash' => hash('sha256', $key),
    ]);
    $entry->set('createdAt', $createdAt);
    $em->saveEntity($entry);
    $ledgerIds[$kind][] = $entry->getId();
}

$ownerName = (string) $users['owner']->get('userName');
$romanianPreference = request($ownerName, 'PUT', 'Preferences/' . $users['owner']->getId(), ['language' => 'ro_RO']);
check('Romanian preference is configured through the native Preferences record',
    $romanianPreference['status'] === 200 && ($romanianPreference['body']['language'] ?? null) === 'ro_RO',
    $romanianPreference['status']);
$colleagueId = $ledgerIds['colleague'][0];
$own = request($ownerName, 'GET', 'EmployeeHolidayLedger?' . query(['maxSize' => 100]));
$ownRows = $own['body']['list'] ?? [];
check('Read Own list contains only assigned records',
    $own['status'] === 200 && count($ownRows) === 3 &&
    count(array_filter($ownRows, fn(array $row): bool => ($row['assignedUserId'] ?? null) !== $users['owner']->getId())) === 0,
    ['status' => $own['status'], 'count' => count($ownRows)]);

$ownRecord = request($ownerName, 'GET', 'EmployeeHolidayLedger/' . $ledgerIds['owner'][1]);
$otherRecord = request($ownerName, 'GET', 'EmployeeHolidayLedger/' . $colleagueId);
check('Read Own direct detail allows own and denies another employee',
    $ownRecord['status'] === 200 && $otherRecord['status'] === 403,
    [$ownRecord['status'], $otherRecord['status']]);
check('technical ledger identity fields are protected by native entity ACL',
    !array_key_exists('idempotencyKey', $ownRecord['body'] ?? []) &&
    !array_key_exists('operationHash', $ownRecord['body'] ?? []));

$crafted = request($ownerName, 'GET', 'EmployeeHolidayLedger?' . query([
    'where' => [['type' => 'equals', 'attribute' => 'assignedUserId', 'value' => $users['colleague']->getId()]],
    'maxSize' => 100,
]));
check('crafted linked-record filter cannot bypass Read Own',
    $crafted['status'] === 200 && count($crafted['body']['list'] ?? []) === 0,
    ['status' => $crafted['status'], 'count' => count($crafted['body']['list'] ?? [])]);

$pageOne = request($ownerName, 'GET', 'EmployeeHolidayLedger?' . query(['maxSize' => 2, 'offset' => 0]));
$pageTwo = request($ownerName, 'GET', 'EmployeeHolidayLedger?' . query(['maxSize' => 2, 'offset' => 2]));
$pageOneIds = array_column($pageOne['body']['list'] ?? [], 'id');
$pageTwoIds = array_column($pageTwo['body']['list'] ?? [], 'id');
check('native pagination remains functional',
    $pageOne['status'] === 200 && $pageTwo['status'] === 200 && count($pageOneIds) === 2 && count($pageTwoIds) === 1 &&
    array_intersect($pageOneIds, $pageTwoIds) === [],
    [$pageOneIds, $pageTwoIds]);
$created = array_column($ownRows, 'createdAt');
$sorted = $created;
rsort($sorted);
check('default native order is createdAt descending', $created === $sorted, $created);

$managerName = (string) $users['manager']->get('userName');
$managerAll = request($managerName, 'GET', 'EmployeeHolidayLedger?' . query(['maxSize' => 200]));
$managerIds = array_column($managerAll['body']['list'] ?? [], 'id');
check('Read All manager sees multiple employees',
    $managerAll['status'] === 200 && in_array($ledgerIds['owner'][0], $managerIds, true) &&
    in_array($ledgerIds['colleague'][0], $managerIds, true));

$managerFiltered = request($managerName, 'GET', 'EmployeeHolidayLedger?' . query([
    'where' => [['type' => 'equals', 'attribute' => 'assignedUserId', 'value' => $users['owner']->getId()]],
    'maxSize' => 100,
]));
$filteredRows = $managerFiltered['body']['list'] ?? [];
check('native assignedUser filter selects one employee',
    $managerFiltered['status'] === 200 && count($filteredRows) === 3 &&
    count(array_filter($filteredRows, fn(array $row): bool => ($row['assignedUserId'] ?? null) !== $users['owner']->getId())) === 0,
    count($filteredRows));
check('clearing native filter restores permitted records',
    count($managerIds) > count($filteredRows) && in_array($ledgerIds['colleague'][0], $managerIds, true));

$managerOwn = request($managerName, 'GET', 'EmployeeHolidayLedger?' . query([
    'where' => [['type' => 'equals', 'attribute' => 'assignedUserId', 'value' => $users['manager']->getId()]],
    'maxSize' => 100,
]));
check('manager can select own history with the same native filter',
    $managerOwn['status'] === 200 && array_column($managerOwn['body']['list'] ?? [], 'id') === $ledgerIds['manager']);

$teamManager = request((string) $users['teamManager']->get('userName'), 'GET', 'EmployeeHolidayLedger/' . $ledgerIds['owner'][0]);
check('team-inherited Read All permission is effective', $teamManager['status'] === 200, $teamManager['status']);

$denied = request((string) $users['denied']->get('userName'), 'GET', 'EmployeeHolidayLedger');
$deniedDetail = request((string) $users['denied']->get('userName'), 'GET', 'EmployeeHolidayLedger/' . $ledgerIds['owner'][0]);
check('Read No denies list and direct detail', $denied['status'] === 403 && $deniedDetail['status'] === 403, [$denied['status'], $deniedDetail['status']]);

$fls = request((string) $users['fls']->get('userName'), 'GET', 'EmployeeHolidayLedger/' . $ledgerIds['owner'][1]);
check('native field-level security hides reason', $fls['status'] === 200 && !array_key_exists('reason', $fls['body'] ?? []), array_keys($fls['body'] ?? []));

foreach ([
    ['POST', 'EmployeeHolidayLedger', ['name' => 'forbidden']],
    ['PUT', 'EmployeeHolidayLedger/' . $ledgerIds['owner'][0], ['reason' => 'forbidden']],
    ['DELETE', 'EmployeeHolidayLedger/' . $ledgerIds['owner'][0], null],
] as [$method, $path, $data]) {
    $response = request($managerName, $method, $path, $data);
    check("$method remains forbidden", $response['status'] === 403, $response['status']);
}

check('detail returns stored balances and optional related data',
    (float) ($ownRecord['body']['balanceBefore'] ?? NAN) === 10.0 &&
    (float) ($ownRecord['body']['delta'] ?? NAN) === -3.0 &&
    (float) ($ownRecord['body']['balanceAfter'] ?? NAN) === 7.0 &&
    ($ownRecord['body']['actorId'] ?? null) === $users['manager']->getId() &&
    ($ownRecord['body']['requestId'] ?? null) === $requestEntity->getId());
$ownerRequest = request($ownerName, 'GET', 'EmployeeHolidayRequest/' . $requestEntity->getId());
$managerRequest = request($managerName, 'GET', 'EmployeeHolidayRequest/' . $requestEntity->getId());
check('related Holiday request remains protected by its native ACL',
    $ownerRequest['status'] === 403 && $managerRequest['status'] === 200,
    [$ownerRequest['status'], $managerRequest['status']]);
$emptyOptional = request($ownerName, 'GET', 'EmployeeHolidayLedger/' . $ledgerIds['owner'][2]);
check('missing optional fields remain empty',
    $emptyOptional['status'] === 200 && empty($emptyOptional['body']['reason']) && empty($emptyOptional['body']['requestId']));

$output = ['status' => $failures ? 'FAIL' : 'PASS', 'assertions' => count($results), 'failures' => $failures, 'results' => $results];
file_put_contents('/feasibility/employee-holiday-ledger-0.8.14-runtime-results.json', json_encode($output, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
file_put_contents('/feasibility/employee-holiday-ledger-0.8.14-browser-state.json', json_encode([
    'password' => $password,
    'owner' => $ownerName,
    'manager' => $managerName,
    'ownerLedgerId' => $ledgerIds['owner'][1],
    'ownerId' => $users['owner']->getId(),
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode(['status' => $output['status'], 'assertions' => count($results), 'failures' => $failures]) . PHP_EOL;
exit($failures ? 1 : 0);
