<?php
declare(strict_types=1);
namespace Espo\Modules\EmployeeManagement\Tools\HolidayBalance;
use Espo\Core\Acl;
use Espo\Core\Exceptions\{BadRequest, Conflict, Forbidden, NotFound};
use Espo\Core\Utils\{Config, Language};
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Core\Utils\Id\RecordIdGenerator;
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Entities\User;
use Espo\ORM\{Entity, EntityManager};
use Espo\Modules\EmployeeManagement\Tools\EmployeeHolidayRequest\BookingDatePolicy;
use Espo\Modules\EmployeeManagement\Tools\Attendance\{AttendanceMonthService, HolidayAttendanceGuard};
use InvalidArgumentException;

/** Request/accounting subset of Holiday 1.9.4. Commands own transactions; generic CRUD stays closed. */
final class HolidayRequestService
{
    // Avoid locking foreign author/decider joins on MariaDB.
    public const LOCK_FIELDS = ['id', 'name', 'dateStart', 'dateEnd', 'dateStartDate', 'dateEndDate', 'days', 'status', 'decidedById', 'decidedAt', 'cancellationReason', 'cancellationRequestedById', 'cancellationRequestedAt', 'cancellationDecidedById', 'cancellationDecidedAt', 'description', 'assignedUserId', 'profileId', 'accountingKey', 'accountingRevision', 'createdAt', 'modifiedAt', 'createdById', 'modifiedById', 'calculationSnapshot', 'operationReceipts', 'creationHash', 'approvalSnapshot', 'approvalManifest', 'approvalOperationKey', 'approvalToken', 'approvalJobId', 'approvalRevision', 'approvalAttempts', 'approvalState', 'approvalError', 'assignedUserName'];
    private const LIST_FIELDS = ['id', 'assignedUserId', 'assignedUserName', 'dateStartDate', 'dateEndDate', 'days', 'status', 'description', 'cancellationReason', 'accountingRevision', 'approvalOperationKey', 'approvalJobId', 'approvalAttempts', 'approvalState', 'approvalError'];
    private const PROFILE = 'EmployeeHolidayProfile';
    private const REQUEST = 'EmployeeHolidayRequest';
    private const STATUS_PENDING = 'Pending';
    private const STATUS_APPROVED = 'Approved';
    private const STATUS_REJECTED = 'Rejected';
    public function __construct(private EntityManager $entityManager, private Config $config,
        private User $user, private DateTimeUtil $dateTime, private Language $language,
        private RecordIdGenerator $recordIdGenerator, private BookingDatePolicy $bookingDatePolicy,
        private AttendanceMonthService $months, private HolidayAttendanceGuard $attendanceGuard,
        private HolidayProfileService $profiles, private Acl $acl, private FileStorageManager $fileStorage,
        private \Espo\Modules\EmployeeManagement\Tools\HolidayDocument\HolidayApprovalService $approvals,
        private \Espo\Modules\EmployeeManagement\Tools\HolidayAdministration\HolidayApproverProvider $approvers,
        private HolidayDecisionNotifier $decisionNotifier) {}
    public function assertActor(): void
    {
        if (!$this->user->isRegular() || !$this->user->isActive() || !$this->acl->checkScope('EmployeeManagement')) {
            throw new Forbidden('An authorized active regular employee is required.');
        }
    }

    private function assertFields(string $type, array $fields): void
    {
        if (!$this->acl->checkScope($type, 'read') || array_intersect($fields, $this->acl->getScopeForbiddenAttributeList($type, 'read'))) {
            throw new Forbidden('Native record and field read permissions are required.');
        }
    }

    private function assertManager(): void
    {
        $this->assertActor();
        if (!$this->acl->checkScope('EmployeeHolidayRequestManagement') || $this->acl->getLevel(self::REQUEST, 'read') !== 'all') {
            throw new Forbidden('Native Holiday request management and read-all required.');
        }
    }

    private function assertApprover(): void
    {
        $this->assertActor();
        if (!$this->approvers->isApprover($this->user)) {
            throw new Forbidden('An active regular user with native Holiday approval permission is required.');
        }
    }

    private function assertRequestAccess(Entity $request): void
    {
        $this->assertFields(self::REQUEST, ['assignedUserId', 'assignedUserName', 'dateStartDate', 'dateEndDate', 'days', 'status', 'description', 'cancellationReason', 'accountingRevision']);
        if (!$this->acl->checkEntity($request, 'read')) { throw new Forbidden('Private request access denied.'); }
    }

    private function lockUser(string $userId): User
    {
        $user = $this->entityManager->getRDBRepositoryByClass(User::class)
            ->where(['id' => $userId, 'type' => User::TYPE_REGULAR, 'isActive' => true])->select(['id','name','type','isActive','firstName','lastName','workingTimeCalendarId'])->forUpdate()->findOne();
        if (!$user) { throw new BadRequest($this->message('eligibleUserRequired')); }
        return $user;
    }

    private function lockProfileByUser(string $userId): Entity
    {
        $profile = $this->entityManager->getRDBRepository(self::PROFILE)
            ->where(['assignedUserId' => $userId])->select(['id','name','assignedUserId','annualEntitlement','balance','nextResetDate','calendarColor','isInitialized','resetPending','pendingResetDate','pendingResetKey'])->forUpdate()->findOne();
        if (!$profile || !$profile->get('isInitialized')) { throw new BadRequest($this->message('profileNotInitialized')); }
        return $profile;
    }

    private function findProfileByUser(string $userId): Entity
    {
        return $this->lockProfileByUser($userId);
    }

    private function calculate(User $user, string $start, string $end, Entity $request): int
    {
        try {
            $snapshot = $this->months->snapshot($user, $start, $end);
        } catch (InvalidArgumentException $e) { throw new BadRequest($this->translateValidationMessage($e->getMessage())); }
        $request->set('calculationSnapshot', (object) $snapshot);
        return $snapshot['totalDays'];
    }

    /**
     * Return the same WTC/public-holiday calculation used by create/update, without
     * reserving balance or persisting a request.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function calculatePreview(array $data): array
    {
        $this->assertActor();
        $this->assertFields(self::REQUEST, ['assignedUserId', 'dateStartDate', 'dateEndDate', 'days']);

        if (!$this->acl->checkScope('EmployeeHolidayBooking')) {
            throw new Forbidden('Holiday booking permission required.');
        }

        return $this->entityManager->getTransactionManager()->run(
            function () use ($data): array {
                $requestId = $data['requestId'] ?? null;
                $existing = null;

                if (is_string($requestId) && $requestId !== '') {
                    $existing = $this->entityManager->getEntityById(self::REQUEST, $requestId);
                    if (!$existing) {
                        throw new NotFound($this->message('requestNotFound'));
                    }
                    $this->assertRequestAccess($existing);
                }

                $userId = $existing
                    ? (string) $existing->get('assignedUserId')
                    : (string) ($data['userId'] ?? $this->user->getId());
                $managed = $userId !== $this->user->getId() || ($data['managed'] ?? false);

                if ($managed) {
                    $this->assertManager();
                }

                $target = $this->lockUser($userId);
                $this->lockProfileByUser($userId);
                $dateStart = (string) $data['dateStart'];
                $dateEnd = (string) $data['dateEnd'];

                if ($existing) {
                    $this->assertBookingDatesAllowed(
                        $dateStart,
                        $dateEnd,
                        (string) $existing->get('dateStartDate'),
                        (string) $existing->get('dateEndDate'),
                    );
                } elseif ($managed) {
                    $this->assertManagedBookingDatesAllowed($dateStart);
                } else {
                    $this->assertBookingDatesAllowed($dateStart, $dateEnd);
                }

                $preview = $this->entityManager->getNewEntity(self::REQUEST);
                $days = $this->calculate($target, $dateStart, $dateEnd, $preview);
                $snapshot = (array) $preview->get('calculationSnapshot');

                return [
                    'days' => $days,
                    'dateStart' => $dateStart,
                    'dateEnd' => $dateEnd,
                    'excludedPublicDates' => array_values((array) ($snapshot['excludedPublicDates'] ?? [])),
                ];
            },
        );
    }

    public function create(array $data): array
    {
        $this->assertActor();
        $this->assertFields(self::REQUEST, ['assignedUserId', 'dateStartDate', 'dateEndDate', 'days', 'status', 'description']);
        if (!$this->acl->checkScope('EmployeeHolidayBooking')) { throw new Forbidden('Holiday booking permission required.'); }
        $key = $data['idempotencyKey'];
        $this->validateIdempotencyKey($key);
        $userId = $data['userId'] ?? $this->user->getId();
        $managed = $userId !== $this->user->getId() || ($data['managed'] ?? false);
        if ($managed) { $this->assertManager(); }
        $hash = $this->fingerprint('create', [$userId, $data['dateStart'], $data['dateEnd'], $data['description'] ?? '', $managed]);
        $accountingKey = hash('sha256', $this->user->getId() . ':' . $key);
        return $this->entityManager->getTransactionManager()->run(function () use ($data, $userId, $managed, $hash, $accountingKey): array {
            $target = $this->lockUser($userId);
            $this->lockProfileByUser($userId);
            $existing = $this->entityManager->getRDBRepository(self::REQUEST)->where(['accountingKey' => $accountingKey])->findOne();
            if ($existing) {
                $this->assertRequestAccess($existing);
                if ($existing->get('creationHash') !== $hash) { throw new Conflict('Idempotency key already used for different request parameters.'); }
                return $this->result($existing) + ['duplicate' => true];
            }
            $request = $this->entityManager->getNewEntity(self::REQUEST);
            $request->set(['dateStartDate' => $data['dateStart'], 'dateEndDate' => $data['dateEnd'], 'description' => $data['description'] ?? '']);
            $this->prepareHolidayForUser($request, $target, $managed);
            $request->set(['accountingKey' => $accountingKey, 'creationHash' => $hash]);
            $this->assertRequestAccess($request);
            // One accounting owner. No legacy ORM Balance hook is installed in this module.
            $this->reserveHoliday($request);
            $this->entityManager->saveEntity($request);
            return $this->result($request) + ['duplicate' => false];
        });
    }

    public function command(string $id, string $operation, array $data): array
    {
        $this->assertActor();
        // Keep receipts identical for the API and future internal Job callers, not dependent on array key order.
        ksort($data);
        $this->validateIdempotencyKey($data['idempotencyKey']);
        $preliminary = $this->entityManager->getEntityById(self::REQUEST, $id);
        if (!$preliminary) { throw new NotFound($this->message('requestNotFound')); }
        $this->assertRequestAccess($preliminary);
        $userId = (string) $preliminary->get('assignedUserId');
        return $this->entityManager->getTransactionManager()->run(function () use ($id, $operation, $data, $userId): array {
            $target = $this->lockUser($userId);
            $profile = $this->lockProfileByUser($userId);
            $request = $this->entityManager->getRDBRepository(self::REQUEST)->where(['id' => $id])->select(self::LOCK_FIELDS)->forUpdate()->findOne();
            if (!$request || $request->get('assignedUserId') !== $userId) { throw new Conflict('Request beneficiary changed.'); }
            $this->assertRequestAccess($request);
            // Check command authority before returning a prior receipt as well.
            if (in_array($operation, ['reject', 'cancel', 'decideCancellation', 'approve', 'retryApproval'], true)) { $this->assertApprover(); }
            else {
                if (!$this->acl->checkScope('EmployeeHolidayBooking')) { throw new Forbidden('Holiday booking permission required.'); }
                if ($operation === 'requestCancellation' && $userId !== $this->user->getId()) { throw new Forbidden($this->message('cancellationOwnerOnly')); }
                if ($userId !== $this->user->getId()) { $this->assertManager(); }
            }
            $receiptKey = hash('sha256', $data['idempotencyKey']);
            $hash = $this->fingerprint($operation, [$id, $data]);
            $receipts = (array) ($request->get('operationReceipts') ?? (object) []);
            if (isset($receipts[$receiptKey])) {
                $receipt = (array) $receipts[$receiptKey];
                if (($receipt['hash'] ?? null) !== $hash) { throw new Conflict('Idempotency key already used for another command.'); }
                // A receipt preserves the effect, not permissions that may have been revoked since it was saved.
                $result=(array)$receipt['result'];
                $forbidden=$this->acl->getScopeForbiddenAttributeList(self::REQUEST,'read');
                foreach (['approvalState','approvalError','approvalAttempts','approvalRetryAvailable'] as $field) {
                    if (in_array($field==='approvalRetryAvailable'?'approvalState':$field,$forbidden,true)) { unset($result[$field]); }
                }
                $documents=[];
                if ($this->acl->checkField(self::REQUEST,'approvalDocuments','read')) {
                    foreach ((array)($result['documents']??[]) as $document) {
                        $document=(array)$document;$a=$this->entityManager->getEntityById('Attachment',$document['id']);
                        if ($a && $a->get('field')==='approvalDocuments' && $this->acl->checkEntity($a,'read')) { $documents[]=$document; }
                    }
                }
                $result['documents']=$documents;
                return $result + ['duplicate' => true];
            }
            if ((int) $request->get('accountingRevision') !== $data['revision']) { throw new Conflict('Stale Holiday request revision. Reload the record.'); }
            $periods = [[$request->get('dateStartDate'), $request->get('dateEndDate')]];
            if ($operation === 'update') { $periods[] = [$data['dateStart'], $data['dateEnd']]; }
            try { $monthStates = $this->months->lockPeriods($periods); }
            catch (InvalidArgumentException $e) { throw new BadRequest($this->translateValidationMessage($e->getMessage())); }
            $status = $request->get('status');
            $reason = trim($data['reason'] ?? '');
            if (in_array($operation, ['requestCancellation', 'cancel'], true) && $reason === '') { throw new BadRequest($this->message('cancellationReasonRequired')); }
            if ($operation === 'approve') {
                if ($status !== 'Pending') { throw new Conflict('Only Pending requests can enter approval.'); }
                $this->assertNoRequestOverlap($request, $userId, (string) $request->get('dateStartDate'), (string) $request->get('dateEndDate'));
                $this->attendanceGuard->assertCompatible($request);
                $this->approvals->initiate($request,$target,$profile,$this->user,$this->getPendingDays($userId));
            } elseif ($operation === 'retryApproval') {
                $this->approvals->retry($request);
            }
            if (in_array($operation,['approve','retryApproval'],true)) {
                // Same reservation/ledger. Only the native worker may publish a new Approved decision.
            } elseif ($operation === 'update') {
                $request->set('accountingRevision', $data['revision'] + 1);
                if ($status !== 'Pending') { throw new Conflict($this->message('onlyPendingEditable')); }
                $start = $data['dateStart']; $end = $data['dateEnd'];
                $this->assertBookingDatesAllowed($start, $end, (string) $request->get('dateStartDate'), (string) $request->get('dateEndDate'));
                $datesChanged = $start !== $request->get('dateStartDate') || $end !== $request->get('dateEndDate');
                $request->set(['dateStartDate' => $start, 'dateEndDate' => $end, 'description' => $data['description'] ?? '']);
                if ($datesChanged) { $request->set('days', $this->calculate($target, $start, $end, $request)); }
                $this->adjustHoliday($request);
            } elseif (in_array($operation, ['withdraw', 'reject', 'cancel'], true) || ($operation === 'decideCancellation' && $data['decision'] === 'Cancelled')) {
                $request->set('accountingRevision', $data['revision'] + 1);
                if (($operation === 'withdraw' || $operation === 'reject') && $status !== 'Pending') { throw new Conflict('Only Pending requests can be withdrawn or rejected.'); }
                if ($operation === 'cancel' && $status !== 'Approved') { throw new Conflict($this->message('onlyApprovedCanBeCancelled')); }
                if ($operation === 'decideCancellation' && $status !== 'CancellationPending') { throw new Conflict($this->message('requestDecisionUnavailable')); }
                $newStatus = $operation === 'reject' ? 'Rejected' : 'Cancelled';
                $type = $operation === 'reject' ? 'holidayRejected' : 'holidayCancelled';
                $before = $this->profiles->snapshot($profile);
                $days = max(0, (int) $request->get('days'));
                $profile->set('balance', (float) $profile->get('balance') + $days);
                $this->entityManager->saveEntity($profile);
                $request->set('status', $newStatus);
                $this->profiles->createLedger($profile, $type, $days, $before, $this->profiles->snapshot($profile),
                    $reason ?: sprintf('Holiday %s for %s through %s.', $newStatus, $request->get('dateStartDate'), $request->get('dateEndDate')),
                    $type . ':' . $request->get('accountingKey') . ':' . $request->get('accountingRevision'), $request);
                if ($operation === 'reject') { $this->stamp($request, 'decided'); }
                else { $request->set('cancellationReason', $reason ?: $request->get('cancellationReason')); $this->stamp($request, 'cancellationDecided'); }
                if ($operation === 'cancel') { $this->stamp($request, 'cancellationRequested'); }
            } elseif ($operation === 'requestCancellation') {
                $request->set('accountingRevision', $data['revision'] + 1);
                if ($status !== 'Approved') { throw new Conflict($this->message('onlyApprovedCanBeCancelled')); }
                if ((string) $request->get('dateStartDate') < $this->dateTime->getToday()->toString()) { throw new Conflict($this->message('startedHolidayApproverOnly')); }
                $request->set(['status' => 'CancellationPending', 'cancellationReason' => $reason,
                    'cancellationDecidedById' => null, 'cancellationDecidedAt' => null]);
                $this->stamp($request, 'cancellationRequested');
            } elseif ($operation === 'decideCancellation') {
                $request->set('accountingRevision', $data['revision'] + 1);
                if ($status !== 'CancellationPending' || $data['decision'] !== 'Approved') { throw new Conflict($this->message('requestDecisionUnavailable')); }
                // This restores an existing documented approval, never approves an undocumented Pending request.
                $documents = $this->entityManager->getRDBRepository('Attachment')->where([
                    'parentType' => self::REQUEST, 'parentId' => $request->getId(), 'field' => 'approvalDocuments',
                ])->find();
                $hasDocument = false;
                foreach ($documents as $document) {
                    if (!$this->fileStorage->exists($document)) { throw new Conflict('Existing approval document file is missing.'); }
                    $hasDocument = true;
                }
                if (!$hasDocument) { throw new Conflict('Existing approval document required to retain Approved.'); }
                $request->set('status', 'Approved');
                $this->stamp($request, 'cancellationDecided');
            } else { throw new BadRequest('Unsupported Holiday operation.'); }
            if (in_array($status, ['Approved', 'CancellationPending'], true)) { $this->months->touch($monthStates); }
            $result = $this->result($request);
            $receipts[$receiptKey] = (object) ['hash' => $hash, 'result' => (object) $result];
            $request->set('operationReceipts', (object) $receipts);
            $this->entityManager->saveEntity($request);

            if ($operation === 'reject') {
                $this->decisionNotifier->notify($request, 'Rejected');
            }

            return $result + ['duplicate' => false];
        });
    }

    public function listRequests(bool $queue = false): array
    {
        $this->assertActor();
        $this->assertFields(self::REQUEST, ['assignedUserId', 'assignedUserName', 'dateStartDate', 'dateEndDate', 'description', 'cancellationReason', 'days', 'status', 'accountingRevision']);
        if ($queue) {
            $this->assertManagerListAccess();
            return $this->buildRequestList(['status' => ['Pending', 'ApprovalProcessing', 'CancellationPending']]);
        }
        return $this->buildRequestList(['assignedUserId' => $this->user->getId()]);
    }

    public function listApprovedRequests(): array
    {
        $this->assertActor();
        $this->assertFields(self::REQUEST, ['assignedUserId', 'assignedUserName', 'dateStartDate', 'dateEndDate', 'description', 'cancellationReason', 'days', 'status', 'accountingRevision']);
        $this->assertManagerListAccess();
        return $this->buildRequestList(['status' => ['Approved']]);
    }

    private function assertManagerListAccess(): void
    {
        $this->assertApprover();
        if ($this->acl->getLevel(self::REQUEST, 'read') !== 'all') { throw new Forbidden('Holiday manager lists require native read-all.'); }
    }

    private function buildRequestList(array $where): array
    {
        $requests = [];
        $eligible = [];
        foreach ($this->entityManager->getRDBRepository('User')->where(['type' => User::TYPE_REGULAR, 'isActive' => true])->select(['id'])->find() as $owner) {
            $eligible[(string) $owner->getId()] = true;
        }
        foreach ($this->entityManager->getRDBRepository(self::REQUEST)->where($where)->select(self::LIST_FIELDS)->order('dateStartDate', 'DESC')->find() as $request) {
            if (!isset($eligible[(string) $request->get('assignedUserId')])) { continue; }
            $this->assertRequestAccess($request);
            $requests[] = $request;
        }
        $documents = $this->buildApprovalDocumentMap($requests);
        $list = [];
        foreach ($requests as $request) {
            $list[] = $this->result($request, $documents[(string) $request->getId()] ?? []);
        }
        $canDecide = false;
        try { $this->assertApprover(); $canDecide = $this->acl->getLevel(self::REQUEST, 'read') === 'all'; } catch (Forbidden) {}
        return ['list' => $list, 'total' => count($list), 'approvalEnabled' => true, 'canDecide' => $canDecide];
    }

    /** @param list<Entity> $requests @return array<string, list<array{id: string, name: string}>> */
    private function buildApprovalDocumentMap(array $requests): array
    {
        $documents = [];
        foreach ($requests as $request) { $documents[(string) $request->getId()] = []; }
        if (!$documents) { return $documents; }
        $forbidden = $this->acl->getScopeForbiddenAttributeList(self::REQUEST, 'read');
        if (in_array('approvalDocumentsIds', $forbidden, true) || !$this->acl->checkField(self::REQUEST, 'approvalDocuments', 'read')) {
            return $documents;
        }
        foreach ($this->entityManager->getRDBRepository('Attachment')->where([
            'parentId' => array_keys($documents), 'parentType' => self::REQUEST, 'field' => 'approvalDocuments',
        ])->select(['id', 'name', 'parentId', 'parentType', 'field'])->find() as $attachment) {
            $parentId = (string) $attachment->get('parentId');
            if (isset($documents[$parentId]) && $this->acl->checkEntity($attachment, 'read')) {
                $documents[$parentId][] = ['id' => (string) $attachment->getId(), 'name' => (string) $attachment->get('name')];
            }
        }
        return $documents;
    }

    /** @param null|list<array{id: string, name: string}> $documents */
    private function result(Entity $request, ?array $documents = null): array
    {
        $result = [];
        foreach (['id', 'assignedUserId', 'assignedUserName', 'dateStartDate', 'dateEndDate', 'days', 'status', 'description', 'cancellationReason', 'accountingRevision'] as $field) { $result[$field] = $request->get($field); }
        $forbidden=$this->acl->getScopeForbiddenAttributeList(self::REQUEST,'read');
        foreach ($this->approvals->state($request) as $field=>$value) {
            if (!in_array($field==='approvalRetryAvailable'?'approvalState':$field,$forbidden,true)) { $result[$field]=$value; }
        }
        $result['documents']=$documents ?? [];
        if ($documents === null && !in_array('approvalDocumentsIds',$forbidden,true) && $this->acl->checkField(self::REQUEST,'approvalDocuments','read')) {
            foreach ($this->entityManager->getRDBRepository('Attachment')->where(['parentId'=>$request->getId(),'parentType'=>self::REQUEST,'field'=>'approvalDocuments'])->find() as $a) {
                if ($this->acl->checkEntity($a,'read')) { $result['documents'][]=['id'=>$a->getId(),'name'=>$a->get('name')]; }
            }
        }
        return $result;
    }

    private function stamp(Entity $request, string $prefix): void
    {
        $request->set([$prefix . 'ById' => $this->user->getId(), $prefix . 'At' => DateTimeUtil::getSystemNowString()]);
    }

    private function fingerprint(string $operation, array $data): string
    {
        // Command payload keys are normalized at the service boundary as well as in the API.
        return hash('sha256', json_encode([$this->user->getId(), $operation, $data], JSON_THROW_ON_ERROR));
    }

    public function getMyBalance(): array
    {
        $this->assertActor();
        return $this->entityManager->getTransactionManager()->run(function (): array {
            $this->lockUser((string) $this->user->getId());
            return $this->readMyBalanceLocked();
        });
    }

    private function readMyBalanceLocked(): array
    {
        $this->assertActor();
        $this->assertFields(self::PROFILE, ['balance', 'annualEntitlement', 'nextResetDate', 'isInitialized']);
        $this->assertFields(self::REQUEST, ['days', 'status']);
        $profile = $this->entityManager
            ->getRDBRepository(self::PROFILE)
            ->where(['assignedUserId' => $this->user->getId()])
            ->findOne();

        if (!$profile || !(bool) $profile->get('isInitialized')) {
            return [
                'initialized' => false,
                'profileId' => $profile?->getId(),
                'balance' => null,
                'availableDays' => null,
                'pendingDays' => 0.0,
                'annualEntitlement' => null,
                'nextResetDate' => null,
            ];
        }

        $balance = (float) $profile->get('balance');
        $pendingDays = $this->getPendingDays((string) $this->user->getId());

        return [
            'initialized' => true,
            'profileId' => $profile->getId(),
            'balance' => $balance,
            'availableDays' => $balance + $pendingDays,
            'pendingDays' => $pendingDays,
            'annualEntitlement' => (float) $profile->get('annualEntitlement'),
            'nextResetDate' => $profile->get('nextResetDate'),
        ];
    }

    private function getPendingDays(string $userId): float
    {
        $pendingDays = 0.0;
        $requests = $this->entityManager
            ->getRDBRepository(self::REQUEST)
            ->where([
                'assignedUserId' => $userId,
                'OR' => [
                    ['status' => [self::STATUS_PENDING, 'ApprovalProcessing']],
                    ['status' => null],
                ],
            ])
            ->find();

        foreach ($requests as $request) {
            $pendingDays += max(0.0, (float) $request->get('days'));
        }

        return $pendingDays;
    }

    public function listManagedBookingUsers(): array
    {
        $this->assertManager();
        $this->assertFields(self::PROFILE, ['balance', 'assignedUserId', 'assignedUserName']);
        if ($this->acl->getLevel(self::PROFILE, 'read') !== 'all') { throw new Forbidden('Profile read-all required.'); }
        $profiles = $this->entityManager
            ->getRDBRepository(self::PROFILE)
            ->where(['isInitialized' => true])
            ->order('assignedUserName')
            ->find();
        $result = [];
        $eligible = [];
        foreach ($this->entityManager->getRDBRepository('User')->where(['type' => User::TYPE_REGULAR, 'isActive' => true])->find() as $owner) {
            $eligible[(string) $owner->getId()] = $owner;
        }

        foreach ($profiles as $profile) {
            $user = $eligible[(string) $profile->get('assignedUserId')] ?? null;

            if (
                !$user ||
                !(bool) $user->get('isActive') ||
                $user->get('type') !== User::TYPE_REGULAR
            ) {
                continue;
            }

            $result[] = [
                'id' => (string) $user->getId(),
                'name' => (string) $user->get('name'),
                'balance' => (float) $profile->get('balance'),
            ];
        }

        return $result;
    }

    private function prepareHolidayForUser(
        Entity $request,
        User $targetUser,
        bool $managedBooking,
    ): void {
        [$dateStart, $dateEnd] = $this->normalizeRequestDates($request);
        if ($managedBooking) {
            $this->assertManagedBookingDatesAllowed($dateStart);
        } else {
            $this->assertBookingDatesAllowed($dateStart, $dateEnd);
        }
        $userId = (string) $targetUser->getId();
        $profile = $this->findProfileByUser($userId);
        $days = $this->calculate($targetUser, $dateStart, $dateEnd, $request);

        $requestId = $this->recordIdGenerator->generate();
        $accountingKey = hash('sha256', $requestId . ':' . $userId);
        $request->set([
            'id' => $requestId,
            'name' => 'Holiday - ' . $targetUser->get('name'),
            'dateStart' => null,
            'dateEnd' => null,
            'dateStartDate' => $dateStart,
            'dateEndDate' => $dateEnd,
            'days' => $days,
            'status' => self::STATUS_PENDING,
            'decidedById' => null,
            'decidedByName' => null,
            'decidedAt' => null,
            'cancellationReason' => null,
            'cancellationRequestedById' => null,
            'cancellationRequestedByName' => null,
            'cancellationRequestedAt' => null,
            'cancellationDecidedById' => null,
            'cancellationDecidedByName' => null,
            'cancellationDecidedAt' => null,
            'assignedUserId' => $userId,
            'assignedUserName' => $targetUser->get('name'),
            'profileId' => $profile->getId(),
            'profileName' => $profile->get('name'),
            'accountingKey' => $accountingKey,
            'accountingRevision' => 1,
        ]);
    }

    private function reserveHoliday(Entity $request): void
    {
        $userId = (string) $request->get('assignedUserId');
        
        $profile = $this->lockProfileByUser($userId);
        $dateStart = (string) $request->get('dateStartDate');
        $dateEnd = (string) $request->get('dateEndDate');
        $days = (int) $request->get('days');

        $this->assertNoRequestOverlap($request, $userId, $dateStart, $dateEnd);
        $this->attendanceGuard->assertCompatible($request);

        $balanceBefore = (float) $profile->get('balance');
        $balanceAfter = $balanceBefore - $days;
        $this->assertBalanceLimit($balanceBefore, $days);

        $before = $this->profiles->snapshot($profile);
        $profile->set('balance', $balanceAfter);
        $this->entityManager->saveEntity($profile);
        $this->profiles->createLedger(
            $profile,
            'holidayBooked',
            -$days,
            $before,
            $this->profiles->snapshot($profile),
            sprintf('Holiday booked for %s through %s.', $dateStart, $dateEnd),
            'holiday-booked:' . $request->get('accountingKey'),
            $request,
        );
    }

    private function adjustHoliday(Entity $request): void
    {
        $userId = (string) $request->get('assignedUserId');
        
        $profile = $this->lockProfileByUser($userId);
        $dateStart = (string) $request->get('dateStartDate');
        $dateEnd = (string) $request->get('dateEndDate');
        $daysBefore = (int) $request->getFetched('days');
        $daysAfter = (int) $request->get('days');
        $dayDifference = $daysAfter - $daysBefore;

        $this->assertNoRequestOverlap($request, $userId, $dateStart, $dateEnd);
        // Repeated even for description-only changes: a Pending reservation may have been worked since booking.
        $this->attendanceGuard->assertCompatible($request);

        if ($dayDifference === 0) {
            return;
        }

        $balanceBefore = (float) $profile->get('balance');
        $balanceAfter = $balanceBefore - $dayDifference;
        $this->assertBalanceLimit($balanceBefore, $dayDifference, true);
        $revision = (int) $request->get('accountingRevision');
        $request->set('accountingRevision', $revision);
        $before = $this->profiles->snapshot($profile);
        $profile->set('balance', $balanceAfter);
        $this->entityManager->saveEntity($profile);
        $this->profiles->createLedger(
            $profile,
            'holidayAdjusted',
            -$dayDifference,
            $before,
            $this->profiles->snapshot($profile),
            sprintf('Holiday changed to %s through %s.', $dateStart, $dateEnd),
            sprintf('holiday-adjusted:%s:%d', $request->get('accountingKey'), $revision),
            $request,
        );
    }

    private function findManagedEmployee(string $userId): User
    {
        $employee = $this->entityManager
            ->getRDBRepositoryByClass(User::class)
            ->where([
                'id' => $userId,
                'isActive' => true,
                'type' => User::TYPE_REGULAR,
            ])
            ->findOne();

        if (!$employee) {
            throw new BadRequest($this->message('managedBookingEmployeeRequired'));
        }

        return $employee;
    }

    private function normalizeRequestDates(Entity $request): array
    {
        $dateStart = $this->requestDate($request, 'dateStartDate', 'dateStart');
        $dateEnd = $this->requestDate($request, 'dateEndDate', 'dateEnd');

        return [$dateStart, $dateEnd];
    }

    private function requestDate(Entity $request, string $dateField, string $dateTimeField): string
    {
        $date = $request->get($dateField);

        if (is_string($date) && $date !== '') {
            return $date;
        }

        $dateTime = $request->get($dateTimeField);

        if (!is_string($dateTime) || strlen($dateTime) < 10) {
            throw new BadRequest($this->message('holidayDatesRequired'));
        }

        return substr($dateTime, 0, 10);
    }

    private function assertBookingDatesAllowed(
        string $dateStart,
        string $dateEnd,
        ?string $originalDateStart = null,
        ?string $originalDateEnd = null,
    ): void {
        try {
            $this->bookingDatePolicy->assertAllowed(
                $dateStart,
                $dateEnd,
                $this->dateTime->getToday()->toString(),
                $originalDateStart,
                $originalDateEnd,
            );
        } catch (InvalidArgumentException $e) {
            throw new BadRequest($this->translateValidationMessage($e->getMessage()));
        }
    }

    private function assertManagedBookingDatesAllowed(string $dateStart): void
    {
        $today = $this->dateTime->getToday()->toString();
        $currentMonthStart = substr($today, 0, 7) . '-01';

        if ($dateStart < $currentMonthStart) {
            throw new BadRequest($this->message('managedBookingCurrentMonthOnly'));
        }
    }

    private function assertNoRequestOverlap(
        Entity $request,
        string $userId,
        string $dateStart,
        string $dateEnd,
    ): void {
        $where = [
            'assignedUserId' => $userId,
            'dateStartDate<=' => $dateEnd,
            'dateEndDate>=' => $dateStart,
            'status' => ['Pending', 'ApprovalProcessing', 'Approved', 'CancellationPending'],
        ];

        if (!$request->isNew()) {
            $where['id!='] = $request->getId();
        }

        $overlap = $this->entityManager
            ->getRDBRepository('EmployeeHolidayRequest')
            ->where($where)
            ->findOne();

        if ($overlap) {
            throw new Conflict($this->message('datesOverlap'));
        }
    }

    private function assertBalanceLimit(
        float $currentBalance,
        float $daysToDeduct,
        bool $isAdjustment = false,
    ): void
    {
        $limit = (float) ($this->config->get('employeeHolidayNegativeBalanceLimitDays') ?? -21.0);
        $balanceAfter = $currentBalance - $daysToDeduct;

        if ($balanceAfter >= $limit) {
            return;
        }

        $availableDays = max(0.0, $currentBalance - $limit);
        $shortfallDays = max(0.0, $daysToDeduct - $availableDays);
        throw new Conflict($this->message('balanceLimitExceeded', [
            'action' => $this->message($isAdjustment ? 'changeAction' : 'bookingAction'),
            'requested' => $this->formatDays($daysToDeduct),
            'kind' => $this->message(
                $isAdjustment ? 'additionalHolidayDays' : 'holidayDays'
            ),
            'available' => $this->formatDays($availableDays),
            'shortfall' => $this->formatDays($shortfallDays),
        ]));
    }

    private function formatDays(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function validateIdempotencyKey(string $idempotencyKey): void
    {
        if (!preg_match('/^[A-Za-z0-9._:-]{1,190}$/', $idempotencyKey)) {
            throw new BadRequest($this->message('idempotencyKeyInvalid'));
        }
    }

    private function message(string $key, array $replacements = []): string
    {
        $message = $this->language->translateLabel($key, 'messages', self::REQUEST);

        foreach ($replacements as $name => $value) {
            $message = str_replace('{' . $name . '}', $value, $message);
        }

        return $message;
    }

    private function translateValidationMessage(string $message): string
    {
        $key = match ($message) {
            'The last day cannot be before the first day.' => 'lastDayBeforeFirst',
            'A holiday booking cannot span more than 367 days.' => 'periodTooLong',
            'The selected period contains no working days.' => 'noWorkingDays',
            'Dates must use the YYYY-MM-DD format.' => 'dateFormatInvalid',
            'Holiday requests cannot start before today.' => 'startDateInPast',
            default => null,
        };

        return $key ? $this->message($key) : $message;
    }

}
