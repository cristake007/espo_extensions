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
$admin = $em->getRDBRepository('User')->where(['userName' => 'admin'])->findOne();
$container->getByClass(Espo\Core\ApplicationUser::class)->setUser($admin);
$labState = json_decode(file_get_contents('/feasibility/employee-stage4-state.json'), true, 512, JSON_THROW_ON_ERROR);
$users = $labState['users'];
$password = getenv('EMPLOYEE_TEST_PASSWORD') ?: throw new RuntimeException('Synthetic password required.');
$results = [];
$failures = 0;

function check(string $test, bool $condition, mixed $observed = null): void
{
    global $results, $failures;
    $result = ['test' => $test, 'status' => $condition ? 'PASS' : 'FAIL', 'observed' => $observed];
    $results[] = $result;
    if (!$condition) {
        $failures++;
    }
    echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

/** @return array{status: int, body: mixed, raw: string} */
function api(string $actor, string $operation, ?array $body = null): array
{
    global $users, $password;
    $handle = curl_init('http://127.0.0.1/api/v1/EmployeeManagement/attendance/' . $operation);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $body === null ? 'GET' : 'POST',
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Espo-Authorization: ' . base64_encode($users[$actor]['name'] . ':' . $password),
        ],
        CURLOPT_TIMEOUT => 120,
    ]);
    if ($body !== null) {
        curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
    }
    $raw = curl_exec($handle);
    if ($raw === false) {
        throw new RuntimeException(curl_error($handle));
    }
    $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);
    return ['status' => $status, 'body' => json_decode($raw, true), 'raw' => $raw];
}

function routine(string $actor, string $userId, string $date, string $status, int $revision, array $extra = []): array
{
    return api($actor, 'managerMarkRoutine', $extra + compact('userId', 'date', 'status', 'revision'));
}

$managerId = $users['manager']['id'];
$employeeId = $users['four']['id'];
$cicId = $users['cic']['id'];
$date = '2026-10-01';
$cicDate = '2026-10-02';
$holidayDate = '2026-10-05';

$unauthorized = routine('viewer', $employeeId, $date, 'AtWork', 0);
check('unauthorized-manager-routine-rejected', $unauthorized['status'] === 403, $unauthorized['status']);

$unexpectedReason = routine('manager', $employeeId, $date, 'AtWork', 0, ['reason' => 'must not be accepted']);
check('routine-command-rejects-reason-field', $unexpectedReason['status'] === 400, $unexpectedReason['status']);

$initialRecord = $em->getRDBRepository('EmployeeAttendanceRecord')
    ->where(['assignedUserId' => $employeeId, 'date' => $date])
    ->findOne();
$initialRevision = (int) ($initialRecord?->get('revision') ?? 0);
$first = routine('manager', $employeeId, $date, 'AtWork', $initialRevision);
check('manager-routine-mark-without-reason', $first['status'] === 200 && ($first['body']['revision'] ?? null) === $initialRevision + 1, $first);
$second = routine('manager', $employeeId, $date, 'BusinessTrip', $initialRevision + 1);
check('manager-can-correct-ordinary-status-with-another-button', $second['status'] === 200 && ($second['body']['revision'] ?? null) === $initialRevision + 2, $second);

$record = $em->getRDBRepository('EmployeeAttendanceRecord')
    ->where(['assignedUserId' => $employeeId, 'date' => $date])
    ->findOne();
$notesBeforeNoop = $em->getRDBRepository('Note')->where(['parentId' => $record?->getId(), 'type' => 'Update'])->count();
$month = $em->getRDBRepository('EmployeeAttendanceMonth')->where(['month' => '2026-10'])->findOne();
$monthRevisionBeforeNoop = (int) $month?->get('revision');
$same = routine('manager', $employeeId, $date, 'BusinessTrip', $initialRevision + 2);
$record = $em->getRDBRepository('EmployeeAttendanceRecord')->where(['assignedUserId' => $employeeId, 'date' => $date])->findOne();
$notesAfterNoop = $em->getRDBRepository('Note')->where(['parentId' => $record?->getId(), 'type' => 'Update'])->count();
$month = $em->getRDBRepository('EmployeeAttendanceMonth')->where(['month' => '2026-10'])->findOne();
check('same-status-is-idempotent-server-side', $same['status'] === 200 && ($same['body']['revision'] ?? null) === $initialRevision + 2 &&
    (int) $record?->get('revision') === $initialRevision + 2 && $notesAfterNoop === $notesBeforeNoop &&
    (int) $month?->get('revision') === $monthRevisionBeforeNoop,
    [$same['status'], $record?->get('revision'), $notesBeforeNoop, $notesAfterNoop, $monthRevisionBeforeNoop, $month?->get('revision')]);

$stale = routine('manager', $employeeId, $date, 'MedicalLeave', $initialRevision + 1);
check('stale-routine-revision-rejected', $stale['status'] === 409, $stale['status']);
check('routine-audit-preserves-actor-time-status-and-empty-reason',
    $record?->get('status') === 'BusinessTrip' && $record?->get('source') === 'Manager' &&
    $record?->get('modifiedById') === $managerId && $record?->get('markedAt') !== null &&
    $record?->get('correctionReason') === null && $notesAfterNoop >= 1,
    [$record?->get('status'), $record?->get('source'), $record?->get('modifiedById'), $record?->get('markedAt'), $record?->get('correctionReason'), $notesAfterNoop]);

$locked = routine('manager', $employeeId, '2026-09-14', 'AtWork', 3);
check('routine-mark-cannot-overwrite-frozen-month', $locked['status'] === 409, $locked['status']);
$exceptionalWithoutReason = api('manager', 'managerMark', [
    'userId' => $employeeId,
    'date' => '2026-09-14',
    'status' => 'AtWork',
    'reason' => '',
    'revision' => 3,
]);
check('exceptional-correction-still-requires-reason', $exceptionalWithoutReason['status'] === 400, $exceptionalWithoutReason['status']);

$schedule = api('manager', 'schedule', [
    'userId' => $cicId,
    'remote' => false,
    'cic' => true,
    'cicStartDate' => $cicDate,
    'cicEndDate' => $cicDate,
]);
check('cic-fixture-configured-through-authorized-command', $schedule['status'] === 200, $schedule['status']);
$cicRecord = $em->getRDBRepository('EmployeeAttendanceRecord')
    ->where(['assignedUserId' => $cicId, 'date' => $cicDate])
    ->findOne();
$cicRevision = (int) ($cicRecord?->get('revision') ?? 0);
$cicWork = routine('manager', $cicId, $cicDate, 'AtWork', $cicRevision);
check('cic-routine-blocks-at-work', $cicWork['status'] === 409, $cicWork['status']);
$cicMedical = routine('manager', $cicId, $cicDate, 'MedicalLeave', $cicRevision);
check('cic-routine-permits-medical-leave', $cicMedical['status'] === 200 && ($cicMedical['body']['status'] ?? null) === 'MedicalLeave', $cicMedical);

$holiday = $em->getRDBRepository('EmployeeHolidayRequest')->where(['accountingKey' => 'manager-buttons-0.8.11-holiday'])->findOne();
if (!$holiday) {
    $holiday = $em->getNewEntity('EmployeeHolidayRequest');
    $holiday->set([
        'name' => 'Manager buttons 0.8.11 protected holiday fixture',
        'assignedUserId' => $cicId,
        'dateStartDate' => $holidayDate,
        'dateEndDate' => $holidayDate,
        'days' => 1,
        'status' => 'CancellationPending',
        'accountingKey' => 'manager-buttons-0.8.11-holiday',
        'accountingRevision' => 1,
    ]);
    $em->saveEntity($holiday);
}
$holidayOverwrite = routine('manager', $cicId, $holidayDate, 'MedicalLeave', 0);
check('approved-holiday-date-cannot-be-overwritten', $holidayOverwrite['status'] === 409, $holidayOverwrite['status']);

$result = ['version' => '0.8.11', 'results' => $results, 'assertions' => count($results), 'failures' => $failures];
file_put_contents('/feasibility/employee-manager-0.8.11-api-results.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode(['assertions' => count($results), 'failures' => $failures]) . PHP_EOL;
exit($failures === 0 ? 0 : 1);
