<?php

declare(strict_types=1);

if (getenv('EMPLOYEE_TEST_LAB') !== 'employee-feasibility-20261020') {
    throw new RuntimeException('Isolated laboratory only.');
}
chdir('/var/www/html');
require 'bootstrap.php';
$application = new Espo\Core\Application();
$container = $application->getContainer();
$config = $container->get('config');
if ($config->get('version') !== '10.0.1' || $config->get('siteUrl') !== 'http://127.0.0.1:18089') {
    throw new RuntimeException('Wrong runtime.');
}
$phase = $argv[1] ?? '';
if (!in_array($phase, ['setup', 'success', 'failure-create', 'failure', 'restore'], true)) {
    throw new RuntimeException('Use setup, success, failure-create, failure or restore.');
}
$em = $container->get('entityManager');
$applicationUser = $container->getByClass(Espo\Core\ApplicationUser::class);
$fixture = json_decode(file_get_contents('/feasibility/functional-fixes-state.json'), true, 512, JSON_THROW_ON_ERROR);
$employee = $em->getEntityById('User', $fixture['users']['employee']['id']);
if (in_array($phase, ['setup', 'failure-create'], true)) {
    $applicationUser->setUser($employee);
} else {
    $applicationUser->setupSystemUser();
}
$factory = $container->get('injectableFactory');
$statePath = '/feasibility/employee-holiday-email-0.8.12-state.json';
$state = is_file($statePath) ? json_decode(file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR) : [];
$results = [];
$failures = 0;

function check(string $name, bool $condition, mixed $observed = null): void
{
    global $results, $failures;
    $item = ['test' => $name, 'status' => $condition ? 'PASS' : 'FAIL', 'observed' => $observed];
    $results[] = $item;
    if (!$condition) {
        $failures++;
    }
    echo json_encode($item, JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

/** @return array<string, mixed> */
function arrayValue(mixed $value): array
{
    return is_object($value) ? get_object_vars($value) : (is_array($value) ? $value : []);
}

function notificationJobs(string $requestId): array
{
    global $em;
    return iterator_to_array($em->getRDBRepository('Job')->where([
        'targetId' => $requestId,
        'targetType' => 'EmployeeHolidayRequest',
        'className' => Espo\Modules\EmployeeManagement\Jobs\SendHolidayApprovalNotification::class,
    ])->order('number')->find());
}

function notificationEmails(string $requestId): array
{
    global $em;
    return iterator_to_array($em->getRDBRepository('Email')->where([
        'parentId' => $requestId,
        'parentType' => 'EmployeeHolidayRequest',
        'isSystem' => true,
    ])->order('createdAt')->find());
}

function findJob(string $requestId, string $recipientId, int $attempt): ?Espo\ORM\Entity
{
    foreach (notificationJobs($requestId) as $job) {
        $data = arrayValue($job->get('data'));
        if (($data['recipientId'] ?? null) === $recipientId && (int) ($data['attempt'] ?? 0) === $attempt) {
            return $job;
        }
    }
    return null;
}

if ($phase === 'setup') {
    $oldRequestJobs = $em->getRDBRepository('Job')->where([
        'className' => Espo\Modules\EmployeeManagement\Jobs\SendHolidayApprovalNotification::class,
    ])->count();
    check('upgrade-does-not-backfill-existing-pending-requests', $oldRequestJobs === 0, $oldRequestJobs);

    $state = [
        'config' => [],
        'emails' => [],
        'requests' => [],
        'emailIds' => [],
        'jobIds' => [],
        'notificationIds' => [],
    ];
    foreach (['smtpServer', 'smtpPort', 'smtpAuth', 'smtpSecurity', 'outboundEmailFromAddress', 'outboundEmailFromName'] as $key) {
        $state['config'][$key] = $config->get($key);
    }
    $addresses = [
        'manager' => 'manager.approver@lab.test',
        'teamManager' => 'team.approver@lab.test',
        'noexport' => 'not-an-email',
        'employee' => 'unauthorized@lab.test',
        'inactive' => 'inactive@lab.test',
    ];
    foreach ($addresses as $kind => $address) {
        $user = $em->getEntityById('User', $fixture['users'][$kind]['id']);
        $state['emails'][$kind] = $user?->getEmailAddress();
        $user?->set('emailAddress', $address);
        if ($user) {
            $em->saveEntity($user);
        }
    }
    $writer = $factory->create(Espo\Core\Utils\Config\ConfigWriter::class);
    $writer->setMultiple([
        'smtpServer' => 'employee-holiday-smtp-sink',
        'smtpPort' => 1025,
        'smtpAuth' => false,
        'smtpSecurity' => '',
        'outboundEmailFromAddress' => 'noreply@lab.test',
        'outboundEmailFromName' => 'Employee Management Lab',
    ]);
    $writer->save();
    $smtpAccount = $em->getNewEntity('InboundEmail');
    $smtpAccount->set([
        'name' => 'Employee Management 0.8.12 SMTP sink',
        'emailAddress' => 'noreply@lab.test',
        'status' => 'Active',
        'useSmtp' => true,
        'smtpHost' => 'employee-holiday-smtp-sink',
        'smtpPort' => 1025,
        'smtpAuth' => false,
        'smtpSecurity' => '',
        'fromName' => 'Employee Management Lab',
    ]);
    $em->saveEntity($smtpAccount);
    $state['smtpAccountId'] = $smtpAccount->getId();

    $profile = $em->getRDBRepository('EmployeeHolidayProfile')->where(['assignedUserId' => $employee->getId()])->findOne();
    $state['profileId'] = $profile->getId();
    $state['profileBalanceBefore'] = $profile->get('balance');
    $state['ledgerCountBefore'] = $em->getRDBRepository('EmployeeHolidayLedger')->where(['profileId' => $profile->getId()])->count();
    $service = $factory->create(Espo\Modules\EmployeeManagement\Tools\HolidayBalance\HolidayRequestService::class);
    $tag = 'holiday-email-0812-' . bin2hex(random_bytes(5));
    $state['tag'] = $tag;
    $payload = [
        'idempotencyKey' => $tag . '-success',
        'dateStart' => '2026-10-26',
        'dateEnd' => '2026-10-26',
        'description' => 'SMTP sink notification test',
    ];
    $created = $service->create($payload);
    $duplicate = $service->create($payload);
    $state['requests']['success'] = $created['id'];
    check('pending-request-created-successfully', $created['status'] === 'Pending' && $created['duplicate'] === false, $created);
    check('duplicate-create-is-idempotent', $duplicate['duplicate'] === true && $duplicate['id'] === $created['id'], $duplicate);

    $emails = notificationEmails($created['id']);
    $jobs = notificationJobs($created['id']);
    $recipients = array_map(
        static fn($job) => (string) (arrayValue($job->get('data'))['recipientId'] ?? ''),
        $jobs,
    );
    sort($recipients);
    $expected = [$fixture['users']['manager']['id'], $fixture['users']['teamManager']['id']];
    sort($expected);
    check('all-and-only-eligible-usable-recipients-queued', $recipients === $expected, $recipients);
    check('one-native-job-per-logical-notification', count($jobs) === 2 && count($emails) === 2, [count($jobs), count($emails)]);
    check('queued-state-recorded-as-draft', array_reduce($emails, static fn($ok, $email) => $ok && $email->get('status') === 'Draft', true));
    check('unauthorized-and-inactive-users-receive-nothing', !in_array($fixture['users']['employee']['id'], $recipients, true) && !in_array($fixture['users']['inactive']['id'], $recipients, true));
    check('invalid-address-is-skipped-safely', !in_array($fixture['users']['noexport']['id'], $recipients, true));
    check('duplicate-create-does-not-duplicate-email-or-job', count(notificationEmails($created['id'])) === 2 && count(notificationJobs($created['id'])) === 2);

    $teamUser = $em->getEntityById('User', $fixture['users']['teamManager']['id']);
    $directRoles = $em->getRelation($teamUser, 'roles')->count();
    $teams = $em->getRelation($teamUser, 'teams')->count();
    check('team-role-approver-is-actually-team-derived', $directRoles === 0 && $teams > 0, [$directRoles, $teams]);

    $notifier = $factory->create(Espo\Modules\EmployeeManagement\Tools\HolidayBalance\HolidaySubmissionNotifier::class);
    $jobsBeforeRollback = $em->getRDBRepository('Job')->count();
    $emailsBeforeRollback = $em->getRDBRepository('Email')->count();
    $rollbackId = 'he' . bin2hex(random_bytes(7));
    try {
        $em->getTransactionManager()->run(function () use ($em, $notifier, $employee, $rollbackId): void {
            $request = $em->getNewEntity('EmployeeHolidayRequest');
            $request->set([
                'id' => $rollbackId,
                'name' => 'Rolled back notification fixture',
                'assignedUserId' => $employee->getId(),
                'dateStartDate' => '2026-10-28',
                'dateEndDate' => '2026-10-28',
                'days' => 1,
                'status' => 'Pending',
                'accountingKey' => hash('sha256', $rollbackId),
                'accountingRevision' => 1,
            ]);
            $em->saveEntity($request);
            $notifier->queue($request);
            throw new RuntimeException('controlled rollback');
        });
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'controlled rollback') {
            throw $e;
        }
    }
    check('rolled-back-create-has-no-email-or-job',
        !$em->getEntityById('EmployeeHolidayRequest', $rollbackId) &&
        $em->getRDBRepository('Job')->count() === $jobsBeforeRollback &&
        $em->getRDBRepository('Email')->count() === $emailsBeforeRollback);

    $nonPending = $em->getNewEntity('EmployeeHolidayRequest');
    $nonPending->set(['id' => 'hn' . bin2hex(random_bytes(7)), 'status' => 'Approved']);
    $beforeNonPending = $em->getRDBRepository('Job')->count();
    $notifier->queue($nonPending);
    check('non-pending-request-is-not-notified', $em->getRDBRepository('Job')->count() === $beforeNonPending);
}

if ($phase === 'success') {
    $requestId = $state['requests']['success'];
    $runner = $factory->create(Espo\Core\Job\JobRunner::class);
    $jobs = notificationJobs($requestId);
    foreach ($jobs as $job) {
        $runner->runThrowingException($job);
    }
    $emails = notificationEmails($requestId);
    check('native-smtp-delivery-recorded-sent', count($emails) === 2 && array_reduce($emails, static fn($ok, $email) => $ok && $email->get('status') === 'Sent' && $email->get('dateSent'), true));
    $messages = array_values(array_filter(array_map('trim', file('/feasibility/employee-holiday-email-0.8.12-smtp.jsonl') ?: [])));
    check('controlled-smtp-sink-received-one-per-approver', count($messages) === 2, count($messages));
    $decoded = array_map(static fn($line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR), $messages);
    $allData = implode("\n", array_column($decoded, 'data'));
    $allRecipients = array_merge(...array_column($decoded, 'recipients'));
    sort($allRecipients);
    check('team-and-direct-approvers-received-through-native-transport', $allRecipients === ['manager.approver@lab.test', 'team.approver@lab.test'], $allRecipients);
    check('smtp-message-has-html-plain-and-no-sensitive-fields',
        str_contains($allData, 'multipart/alternative') &&
        str_contains($allData, '#EmployeeHolidayRequest') &&
        !str_contains($allData, 'accountingKey') &&
        !str_contains($allData, 'accessToken'),
        substr($allData, 0, 500));
    check('native-email-content-is-professional-and-complete', array_reduce($emails, static function ($ok, $email) {
        return $ok
            && str_starts_with((string) $email->get('name'), 'Cerere nouă de concediu – ')
            && str_contains((string) $email->get('body'), 'Zile lucrătoare solicitate')
            && str_contains((string) $email->get('bodyPlain'), 'Status: În așteptare')
            && str_contains((string) $email->get('bodyPlain'), 'http://127.0.0.1:18089/#EmployeeHolidayRequest');
    }, true));

    $managerJob = findJob($requestId, $fixture['users']['manager']['id'], 1);
    $data = arrayValue($managerJob->get('data'));
    $factory->create(Espo\Core\Job\JobSchedulerFactory::class)->create()
        ->setClassName(Espo\Modules\EmployeeManagement\Jobs\SendHolidayApprovalNotification::class)
        ->setGroup((string) $managerJob->get('group'))
        ->setData(Espo\Core\Job\Job\Data::create($data)->withTargetId($requestId)->withTargetType('EmployeeHolidayRequest'))
        ->schedule();
    $duplicateJob = $em->getRDBRepository('Job')->where([
        'className' => Espo\Modules\EmployeeManagement\Jobs\SendHolidayApprovalNotification::class,
        'group' => $managerJob->get('group'),
        'status' => 'Pending',
    ])->order('number', 'DESC')->findOne();
    $runner->runThrowingException($duplicateJob);
    $messagesAfterDuplicate = array_values(array_filter(array_map('trim', file('/feasibility/employee-holiday-email-0.8.12-smtp.jsonl') ?: [])));
    check('duplicate-concurrent-group-cannot-send-again', count($messagesAfterDuplicate) === 2, count($messagesAfterDuplicate));

    $request = $em->getEntityById('EmployeeHolidayRequest', $requestId);
    $decision = $factory->create(Espo\Modules\EmployeeManagement\Tools\HolidayBalance\HolidayDecisionNotifier::class);
    $before = $em->getRDBRepository('Notification')->where(['relatedId' => $requestId, 'relatedType' => 'EmployeeHolidayRequest'])->count();
    $decision->notify($request, 'Rejected');
    $decision->notify($request, 'Rejected');
    $after = $em->getRDBRepository('Notification')->where(['relatedId' => $requestId, 'relatedType' => 'EmployeeHolidayRequest'])->count();
    check('existing-decision-notification-remains-idempotent', $after === $before + 1, [$before, $after]);
}

if ($phase === 'failure-create') {
    $service = $factory->create(Espo\Modules\EmployeeManagement\Tools\HolidayBalance\HolidayRequestService::class);
    $created = $service->create([
        'idempotencyKey' => $state['tag'] . '-failure',
        'dateStart' => '2026-10-27',
        'dateEnd' => '2026-10-27',
        'description' => 'Controlled SMTP failure test',
    ]);
    $state['requests']['failure'] = $created['id'];
    check('request-remains-valid-before-any-smtp-attempt', $created['status'] === 'Pending' && $created['duplicate'] === false, $created);
}

if ($phase === 'failure') {
    $created = ['id' => $state['requests']['failure']];
    $request = $em->getEntityById('EmployeeHolidayRequest', $created['id']);
    $balanceAfterCreate = $em->getEntityById('EmployeeHolidayProfile', $state['profileId'])->get('balance');
    $ledgerAfterCreate = $em->getRDBRepository('EmployeeHolidayLedger')->where(['profileId' => $state['profileId']])->count();
    $revisionAfterCreate = $request->get('accountingRevision');
    $runner = $factory->create(Espo\Core\Job\JobRunner::class);
    $recipientId = $fixture['users']['manager']['id'];
    $attemptStatuses = [];
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $job = findJob($created['id'], $recipientId, $attempt);
        check('retry-attempt-' . $attempt . '-is-scheduled', (bool) $job, $job?->getId());
        try {
            $runner->runThrowingException($job);
        } catch (Throwable) {
            // Expected controlled SMTP connection failure.
        }
        $attemptStatuses[] = $em->getEntityById('Job', $job->getId())?->get('status');
    }
    $attemptFour = findJob($created['id'], $recipientId, 4);
    $failedJobData = arrayValue(findJob($created['id'], $recipientId, 1)?->get('data'));
    $failedEmail = isset($failedJobData['emailId'])
        ? $em->getEntityById('Email', $failedJobData['emailId'])
        : null;
    check('smtp-failure-is-recorded', $failedEmail?->get('status') === 'Failed' && $attemptStatuses === ['Failed', 'Failed', 'Failed'], [$failedEmail?->get('status'), $attemptStatuses]);
    check('retry-is-bounded-at-three-attempts', $attemptFour === null, $attemptFour?->getId());
    $jobs = notificationJobs($created['id']);
    $retryTimes = [];
    foreach ($jobs as $job) {
        $data = arrayValue($job->get('data'));
        if (($data['recipientId'] ?? null) === $recipientId) {
            $retryTimes[(int) $data['attempt']] = $job->get('executeTime');
        }
    }
    check('retry-delays-are-persisted', isset($retryTimes[1], $retryTimes[2], $retryTimes[3]) && $retryTimes[1] < $retryTimes[2] && $retryTimes[2] < $retryTimes[3], $retryTimes);
    $requestAfter = $em->getEntityById('EmployeeHolidayRequest', $created['id']);
    check('smtp-failure-does-not-change-request-balance-or-ledger',
        $requestAfter->get('status') === 'Pending' &&
        $requestAfter->get('accountingRevision') === $revisionAfterCreate &&
        $em->getEntityById('EmployeeHolidayProfile', $state['profileId'])->get('balance') === $balanceAfterCreate &&
        $em->getRDBRepository('EmployeeHolidayLedger')->where(['profileId' => $state['profileId']])->count() === $ledgerAfterCreate,
        [$requestAfter->get('status'), $balanceAfterCreate, $ledgerAfterCreate]);
}

if ($phase === 'restore') {
    foreach ($state['requests'] as $requestId) {
        foreach (notificationJobs($requestId) as $job) {
            $state['jobIds'][] = $job->getId();
            $em->removeEntity($job);
        }
        foreach (notificationEmails($requestId) as $email) {
            $state['emailIds'][] = $email->getId();
            $em->removeEntity($email);
        }
        foreach ($em->getRDBRepository('Notification')->where(['relatedId' => $requestId, 'relatedType' => 'EmployeeHolidayRequest'])->find() as $notification) {
            $state['notificationIds'][] = $notification->getId();
            $em->removeEntity($notification);
        }
        foreach ($em->getRDBRepository('EmployeeHolidayLedger')->where(['requestId' => $requestId])->find() as $ledger) {
            $em->removeEntity($ledger);
        }
        $request = $em->getEntityById('EmployeeHolidayRequest', $requestId);
        if ($request) {
            $em->removeEntity($request);
        }
    }
    $profile = $em->getEntityById('EmployeeHolidayProfile', $state['profileId']);
    $profile->set('balance', $state['profileBalanceBefore']);
    $em->saveEntity($profile);
    foreach ($state['emails'] as $kind => $address) {
        $user = $em->getEntityById('User', $fixture['users'][$kind]['id']);
        $user?->set('emailAddress', $address);
        if ($user) {
            $em->saveEntity($user);
        }
    }
    $writer = $factory->create(Espo\Core\Utils\Config\ConfigWriter::class);
    $writer->setMultiple($state['config']);
    $writer->save();
    $smtpAccount = isset($state['smtpAccountId'])
        ? $em->getEntityById('InboundEmail', $state['smtpAccountId'])
        : null;
    if ($smtpAccount) {
        $em->removeEntity($smtpAccount);
    }
    check('synthetic-fixtures-and-config-restored',
        (float) $em->getEntityById('EmployeeHolidayProfile', $state['profileId'])->get('balance') === (float) $state['profileBalanceBefore'] &&
        $em->getRDBRepository('EmployeeHolidayLedger')->where(['profileId' => $state['profileId']])->count() === $state['ledgerCountBefore']);
}

file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
file_put_contents('/feasibility/employee-holiday-email-0.8.12-' . $phase . '-results.json', json_encode([
    'phase' => $phase,
    'results' => $results,
    'assertions' => count($results),
    'failures' => $failures,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
exit($failures === 0 ? 0 : 1);
