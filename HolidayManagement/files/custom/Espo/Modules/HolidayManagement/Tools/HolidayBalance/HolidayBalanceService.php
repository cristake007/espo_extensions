<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Tools\HolidayBalance;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Core\Utils\Id\RecordIdGenerator;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Entities\User;
use Espo\Modules\HolidayManagement\Tools\HolidayDocument\HolidayApprovalDocumentService;
use Espo\Modules\HolidayManagement\Tools\HolidayRequest\WorkingDayCalculator;
use Espo\Modules\HolidayManagement\Tools\HolidayRequest\BookingDatePolicy;
use Espo\Modules\HolidayManagement\Tools\HolidayRequest\NonWorkingDayProvider;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use InvalidArgumentException;
use stdClass;

final class HolidayBalanceService
{
    private const PROFILE = 'HolidayProfile';
    private const LEDGER = 'HolidayLedger';
    private const REQUEST = 'HolidayRequest';
    private const STATUS_PENDING = 'Pending';
    private const STATUS_APPROVED = 'Approved';
    private const STATUS_REJECTED = 'Rejected';
    private const STATUS_CANCELLATION_PENDING = 'CancellationPending';
    private const STATUS_CANCELLED = 'Cancelled';
    private const DEFAULT_CALENDAR_COLOR = '#4F8A8B';

    public function __construct(
        private EntityManager $entityManager,
        private Config $config,
        private User $user,
        private WorkingDayCalculator $workingDayCalculator,
        private NonWorkingDayProvider $nonWorkingDayProvider,
        private BookingDatePolicy $bookingDatePolicy,
        private DateTimeUtil $dateTime,
        private RecordIdGenerator $recordIdGenerator,
        private HolidayApprovalDocumentService $approvalDocumentService,
        private Language $language,
    ) {}

    /** @return array<string, mixed> */
    public function getMyBalance(): array
    {
        $profile = $this->entityManager
            ->getRDBRepository(self::PROFILE)
            ->where(['userId' => $this->user->getId()])
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
                    ['status' => self::STATUS_PENDING],
                    ['status' => null],
                ],
            ])
            ->find();

        foreach ($requests as $request) {
            $pendingDays += max(0.0, (float) $request->get('days'));
        }

        return $pendingDays;
    }

    public function prepareHolidayForCreate(Entity $request): void
    {
        $this->assertInternalUser();
        [$dateStart, $dateEnd] = $this->normalizeRequestDates($request);
        $this->assertBookingDatesAllowed($dateStart, $dateEnd);
        $userId = (string) $this->user->getId();
        $profile = $this->findProfileByUser($userId);
        $days = $this->countWorkingDays($dateStart, $dateEnd);

        $requestId = $this->recordIdGenerator->generate();
        $accountingKey = hash('sha256', $requestId . ':' . $userId);
        $request->set([
            'id' => $requestId,
            'name' => 'Holiday - ' . $this->user->get('name'),
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
            'assignedUserName' => $this->user->get('name'),
            'profileId' => $profile->getId(),
            'profileName' => $profile->get('name'),
            'accountingKey' => $accountingKey,
            'accountingRevision' => 1,
        ]);
    }

    public function reserveHoliday(Entity $request): void
    {
        $userId = (string) $request->get('assignedUserId');
        $this->assertRequestOwner($userId);
        $profile = $this->lockProfileByUser($userId);
        $dateStart = (string) $request->get('dateStartDate');
        $dateEnd = (string) $request->get('dateEndDate');
        $days = (int) $request->get('days');

        $this->assertNoRequestOverlap($request, $userId, $dateStart, $dateEnd);

        $balanceBefore = (float) $profile->get('balance');
        $balanceAfter = $balanceBefore - $days;
        $this->assertBalanceLimit($balanceBefore, $days);

        $before = $this->snapshot($profile);
        $profile->set('balance', $balanceAfter);
        $this->entityManager->saveEntity($profile);
        $this->createLedger(
            $profile,
            'holidayBooked',
            -$days,
            $before,
            $this->snapshot($profile),
            sprintf('Holiday booked for %s through %s.', $dateStart, $dateEnd),
            'holiday-booked:' . $request->get('accountingKey'),
            $request,
        );
    }

    public function prepareHolidayForUpdate(Entity $request): void
    {
        $userId = (string) $request->getFetched('assignedUserId');
        $this->assertRequestOwner($userId);
        $status = (string) ($request->getFetched('status') ?: self::STATUS_PENDING);

        if ($status !== self::STATUS_PENDING) {
            throw new Conflict($this->message('onlyPendingEditable'));
        }

        [$dateStart, $dateEnd] = $this->normalizeRequestDates($request);
        $this->assertBookingDatesAllowed(
            $dateStart,
            $dateEnd,
            (string) $request->getFetched('dateStartDate'),
            (string) $request->getFetched('dateEndDate'),
        );
        $profile = $this->findProfileByUser($userId);
        $daysAfter = $this->countWorkingDays($dateStart, $dateEnd);

        $request->set([
            'name' => (string) $request->getFetched('name'),
            'dateStart' => null,
            'dateEnd' => null,
            'dateStartDate' => $dateStart,
            'dateEndDate' => $dateEnd,
            'days' => $daysAfter,
            'status' => $status,
            'decidedById' => $request->getFetched('decidedById'),
            'decidedAt' => $request->getFetched('decidedAt'),
            'assignedUserId' => $userId,
            'profileId' => $profile->getId(),
            'accountingKey' => $request->getFetched('accountingKey'),
            'accountingRevision' => $request->getFetched('accountingRevision'),
        ]);
    }

    public function adjustHoliday(Entity $request): void
    {
        $userId = (string) $request->get('assignedUserId');
        $this->assertRequestOwner($userId);
        $profile = $this->lockProfileByUser($userId);
        $dateStart = (string) $request->get('dateStartDate');
        $dateEnd = (string) $request->get('dateEndDate');
        $daysBefore = (int) $request->getFetched('days');
        $daysAfter = (int) $request->get('days');
        $dayDifference = $daysAfter - $daysBefore;

        $this->assertNoRequestOverlap($request, $userId, $dateStart, $dateEnd);

        if ($dayDifference === 0) {
            return;
        }

        $balanceBefore = (float) $profile->get('balance');
        $balanceAfter = $balanceBefore - $dayDifference;
        $this->assertBalanceLimit($balanceBefore, $dayDifference, true);
        $revision = (int) $request->getFetched('accountingRevision') + 1;
        $request->set('accountingRevision', $revision);
        $before = $this->snapshot($profile);
        $profile->set('balance', $balanceAfter);
        $this->entityManager->saveEntity($profile);
        $this->createLedger(
            $profile,
            'holidayAdjusted',
            -$dayDifference,
            $before,
            $this->snapshot($profile),
            sprintf('Holiday changed to %s through %s.', $dateStart, $dateEnd),
            sprintf('holiday-adjusted:%s:%d', $request->getFetched('accountingKey'), $revision),
            $request,
        );
    }

    public function cancelHoliday(Entity $request): void
    {
        $userId = (string) $request->get('assignedUserId');
        $this->assertRequestOwner($userId);
        $status = (string) ($request->get('status') ?: self::STATUS_PENDING);

        if (in_array($status, [
            self::STATUS_APPROVED,
            self::STATUS_CANCELLATION_PENDING,
            self::STATUS_CANCELLED,
        ], true)) {
            throw new Conflict($this->message('finalizedCannotDelete'));
        }

        if ($status === self::STATUS_REJECTED) {
            return;
        }

        $profile = $this->lockProfileByUser($userId);
        $days = max(0, (int) $request->get('days'));
        $before = $this->snapshot($profile);
        $profile->set('balance', (float) $profile->get('balance') + $days);
        $this->entityManager->saveEntity($profile);
        $this->createLedger(
            $profile,
            'holidayCancelled',
            $days,
            $before,
            $this->snapshot($profile),
            sprintf(
                'Holiday cancelled for %s through %s.',
                $request->get('dateStartDate'),
                $request->get('dateEndDate'),
            ),
            sprintf(
                'holiday-cancelled:%s:%d',
                $request->get('accountingKey'),
                $request->get('accountingRevision'),
            ),
            $request,
        );
    }

    /** @return array<string, mixed> */
    public function getApprovalState(string $requestId): array
    {
        $this->assertInternalUser();

        $request = $this->entityManager
            ->getRDBRepository(self::REQUEST)
            ->where(['id' => $requestId])
            ->findOne();

        if (!$request) {
            throw new NotFound($this->message('requestNotFound'));
        }

        $status = (string) ($request->get('status') ?: self::STATUS_PENDING);

        return [
            'id' => $request->getId(),
            'status' => $status,
            'canDecide' =>
                in_array($status, [
                    self::STATUS_PENDING,
                    self::STATUS_CANCELLATION_PENDING,
                ], true) &&
                $this->isConfiguredApprover(),
            'canRequestCancellation' =>
                $status === self::STATUS_APPROVED &&
                $request->get('assignedUserId') === $this->user->getId() &&
                (string) $request->get('dateStartDate') > $this->dateTime->getToday()->toString(),
            'canCancelDirectly' =>
                $status === self::STATUS_APPROVED &&
                $this->isConfiguredApprover(),
        ];
    }

    /** @return array<string, mixed> */
    public function listPendingApprovals(): array
    {
        $this->assertInternalUser();

        if (!$this->isConfiguredApprover()) {
            return [
                'isApprover' => false,
                'list' => [],
                'total' => 0,
            ];
        }

        $requests = $this->entityManager
            ->getRDBRepository(self::REQUEST)
            ->where(['status' => [
                self::STATUS_PENDING,
                self::STATUS_CANCELLATION_PENDING,
            ]])
            ->order('dateStartDate')
            ->find();
        $list = [];

        foreach ($requests as $request) {
            $list[] = [
                'id' => $request->getId(),
                'requesterId' => $request->get('assignedUserId'),
                'requesterName' => $request->get('assignedUserName'),
                'dateStart' => $request->get('dateStartDate'),
                'dateEnd' => $request->get('dateEndDate'),
                'days' => $request->get('days'),
                'description' => $request->get('description'),
                'cancellationReason' => $request->get('cancellationReason'),
                'status' => $request->get('status') ?: self::STATUS_PENDING,
            ];
        }

        return [
            'isApprover' => true,
            'list' => $list,
            'total' => count($list),
        ];
    }

    /** @return array<string, mixed> */
    public function decideHoliday(string $requestId, string $decision): array
    {
        if (!in_array($decision, [
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED,
        ], true)) {
            throw new BadRequest($this->message('decisionInvalid'));
        }

        $this->assertConfiguredApprover();

        return $this->entityManager->getTransactionManager()->run(
            function () use ($requestId, $decision): array {
                $request = $this->entityManager
                    ->getRDBRepository(self::REQUEST)
                    ->where(['id' => $requestId])
                    ->forUpdate()
                    ->findOne();

                if (!$request) {
                    throw new NotFound($this->message('requestNotFound'));
                }

                $currentStatus = (string) ($request->get('status') ?: self::STATUS_PENDING);

                $allowedDecisions = $currentStatus === self::STATUS_PENDING ?
                    [self::STATUS_APPROVED, self::STATUS_REJECTED] :
                    [self::STATUS_CANCELLED, self::STATUS_APPROVED];

                if (
                    !in_array($currentStatus, [
                        self::STATUS_PENDING,
                        self::STATUS_CANCELLATION_PENDING,
                    ], true) ||
                    !in_array($decision, $allowedDecisions, true)
                ) {
                    throw new Conflict($this->message('requestDecisionUnavailable'));
                }

                $decisionData = ['status' => $decision];

                if ($currentStatus === self::STATUS_CANCELLATION_PENDING) {
                    $decisionData += [
                        'cancellationDecidedById' => $this->user->getId(),
                        'cancellationDecidedByName' => $this->user->get('name'),
                        'cancellationDecidedAt' => DateTimeUtil::getSystemNowString(),
                    ];
                } else {
                    $decisionData += [
                        'decidedById' => $this->user->getId(),
                        'decidedByName' => $this->user->get('name'),
                        'decidedAt' => DateTimeUtil::getSystemNowString(),
                    ];
                }

                $request->set($decisionData);
                $this->entityManager->saveEntity($request);

                return [
                    'id' => $request->getId(),
                    'status' => $request->get('status'),
                    'decidedById' => $request->get('decidedById'),
                    'decidedByName' => $request->get('decidedByName'),
                    'decidedAt' => $request->get('decidedAt'),
                    'cancellationDecidedById' => $request->get('cancellationDecidedById'),
                    'cancellationDecidedByName' => $request->get('cancellationDecidedByName'),
                    'cancellationDecidedAt' => $request->get('cancellationDecidedAt'),
                ];
            },
        );
    }

    public function processApprovalDecision(Entity $request): void
    {
        $statusBefore = (string) ($request->getFetched('status') ?: self::STATUS_PENDING);
        $statusAfter = (string) $request->get('status');

        $validTransition =
            ($statusBefore === self::STATUS_PENDING && in_array(
                $statusAfter,
                [self::STATUS_APPROVED, self::STATUS_REJECTED],
                true,
            )) ||
            ($statusBefore === self::STATUS_APPROVED && in_array(
                $statusAfter,
                [self::STATUS_CANCELLATION_PENDING, self::STATUS_CANCELLED],
                true,
            )) ||
            ($statusBefore === self::STATUS_CANCELLATION_PENDING && in_array(
                $statusAfter,
                [self::STATUS_APPROVED, self::STATUS_CANCELLED],
                true,
            ));

        if (!$validTransition) {
            throw new Conflict($this->message('approvalDecisionOnce'));
        }

        if ($statusAfter !== self::STATUS_CANCELLATION_PENDING) {
            $this->assertConfiguredApprover();
        }

        if ($statusBefore === self::STATUS_PENDING && $statusAfter === self::STATUS_APPROVED) {
            $this->approvalDocumentService->generate($request);

            return;
        }

        if (
            $statusAfter === self::STATUS_CANCELLATION_PENDING ||
            ($statusBefore === self::STATUS_CANCELLATION_PENDING &&
                $statusAfter === self::STATUS_APPROVED)
        ) {
            return;
        }

        $type = $statusAfter === self::STATUS_CANCELLED ?
            'holidayCancelled' :
            'holidayRejected';
        $reason = $statusAfter === self::STATUS_CANCELLED ?
            trim((string) $request->get('cancellationReason')) :
            sprintf(
                'Holiday rejected for %s through %s.',
                $request->get('dateStartDate'),
                $request->get('dateEndDate'),
            );
        $keyPrefix = $statusAfter === self::STATUS_CANCELLED ?
            'approved-holiday-cancelled:' :
            'holiday-rejected:';

        $profile = $this->lockProfileByUser((string) $request->get('assignedUserId'));
        $days = max(0, (int) $request->get('days'));
        $before = $this->snapshot($profile);
        $profile->set('balance', (float) $profile->get('balance') + $days);
        $this->entityManager->saveEntity($profile);
        $this->createLedger(
            $profile,
            $type,
            $days,
            $before,
            $this->snapshot($profile),
            $reason,
            sprintf(
                '%s%s:%d',
                $keyPrefix,
                $request->get('accountingKey'),
                $request->get('accountingRevision'),
            ),
            $request,
        );
    }

    /** @return array<string, mixed> */
    public function requestCancellation(string $requestId, string $reason): array
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new BadRequest($this->message('cancellationReasonRequired'));
        }

        return $this->entityManager->getTransactionManager()->run(
            function () use ($requestId, $reason): array {
                $request = $this->lockHolidayRequest($requestId);
                $this->assertCancellationRequester($request);

                if (($request->get('status') ?: self::STATUS_PENDING) !== self::STATUS_APPROVED) {
                    throw new Conflict($this->message('onlyApprovedCanBeCancelled'));
                }

                if ((string) $request->get('dateStartDate') <= $this->dateTime->getToday()->toString()) {
                    throw new Conflict($this->message('startedHolidayApproverOnly'));
                }

                $request->set([
                    'status' => self::STATUS_CANCELLATION_PENDING,
                    'cancellationReason' => $reason,
                    'cancellationRequestedById' => $this->user->getId(),
                    'cancellationRequestedByName' => $this->user->get('name'),
                    'cancellationRequestedAt' => DateTimeUtil::getSystemNowString(),
                    'cancellationDecidedById' => null,
                    'cancellationDecidedByName' => null,
                    'cancellationDecidedAt' => null,
                ]);
                $this->entityManager->saveEntity($request);

                return $this->cancellationResult($request);
            },
        );
    }

    /** @return array<string, mixed> */
    public function cancelApprovedHoliday(string $requestId, string $reason): array
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new BadRequest($this->message('cancellationReasonRequired'));
        }

        $this->assertConfiguredApprover();

        return $this->entityManager->getTransactionManager()->run(
            function () use ($requestId, $reason): array {
                $request = $this->lockHolidayRequest($requestId);

                if (($request->get('status') ?: self::STATUS_PENDING) !== self::STATUS_APPROVED) {
                    throw new Conflict($this->message('onlyApprovedCanBeCancelled'));
                }

                $request->set([
                    'status' => self::STATUS_CANCELLED,
                    'cancellationReason' => $reason,
                    'cancellationRequestedById' => $this->user->getId(),
                    'cancellationRequestedByName' => $this->user->get('name'),
                    'cancellationRequestedAt' => DateTimeUtil::getSystemNowString(),
                    'cancellationDecidedById' => $this->user->getId(),
                    'cancellationDecidedByName' => $this->user->get('name'),
                    'cancellationDecidedAt' => DateTimeUtil::getSystemNowString(),
                ]);
                $this->entityManager->saveEntity($request);

                return $this->cancellationResult($request);
            },
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listProfiles(): array
    {
        $profilesByUser = [];
        $defaultEntitlement = $this->config->get('holidayManagementAnnualEntitlementDays');
        $defaultResetDate = $this->getDefaultNextResetDate();

        foreach ($this->entityManager->getRDBRepository(self::PROFILE)->find() as $profile) {
            $profilesByUser[(string) $profile->get('userId')] = $profile;
        }

        $result = [];
        $users = $this->entityManager
            ->getRDBRepository(User::ENTITY_TYPE)
            ->where([
                'isActive' => true,
                'type' => [User::TYPE_REGULAR, User::TYPE_ADMIN],
            ])
            ->order('name')
            ->find();

        foreach ($users as $eligibleUser) {
            $profile = $profilesByUser[$eligibleUser->getId()] ?? null;
            $result[] = [
                'userId' => $eligibleUser->getId(),
                'userName' => $eligibleUser->get('name'),
                'profileId' => $profile?->getId(),
                'annualEntitlement' => $profile?->get('annualEntitlement') ?? $defaultEntitlement,
                'balance' => $profile?->get('balance'),
                'nextResetDate' => $profile?->get('nextResetDate') ?? $defaultResetDate,
                'calendarColor' => $profile?->get('calendarColor') ?: self::DEFAULT_CALENDAR_COLOR,
                'isInitialized' => (bool) ($profile?->get('isInitialized') ?? false),
                'resetPending' => (bool) ($profile?->get('resetPending') ?? false),
            ];
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>|stdClass> $items
     * @return array<int, array<string, mixed>>
     */
    public function bulkInitialize(array $items): array
    {
        if ($items === []) {
            throw new BadRequest($this->message('profileItemRequired'));
        }

        $result = [];

        foreach ($items as $rawItem) {
            $item = is_object($rawItem) ? get_object_vars($rawItem) : $rawItem;
            $result[] = $this->initializeOne($item);
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public function correct(
        string $profileId,
        float $delta,
        string $reason,
        string $idempotencyKey,
    ): array {
        $this->validateIdempotencyKey($idempotencyKey);

        if (trim($reason) === '') {
            throw new BadRequest($this->message('correctionReasonRequired'));
        }

        if (!is_finite($delta) || $delta === 0.0) {
            throw new BadRequest($this->message('correctionDeltaInvalid'));
        }

        return $this->entityManager->getTransactionManager()->run(function () use (
            $profileId,
            $delta,
            $reason,
            $idempotencyKey,
        ): array {
            $existing = $this->findLedgerByKey($idempotencyKey);

            if ($existing) {
                return $this->duplicateResult($existing);
            }

            $profile = $this->lockProfile($profileId);
            $existingAfterLock = $this->findLedgerByKey($idempotencyKey);

            if ($existingAfterLock) {
                return $this->mutationResult($profile, $existingAfterLock, true);
            }

            $before = $this->snapshot($profile);
            $afterBalance = (float) $profile->get('balance') + $delta;

            $profile->set('balance', $afterBalance);
            $this->entityManager->saveEntity($profile);

            $ledger = $this->createLedger(
                $profile,
                'correction',
                $delta,
                $before,
                $this->snapshot($profile),
                trim($reason),
                $idempotencyKey,
            );

            return $this->mutationResult($profile, $ledger);
        });
    }

    /** @return array<string, mixed> */
    public function reset(
        string $profileId,
        string $idempotencyKey,
        bool $force = false,
        ?string $reason = null,
    ): array {
        $this->validateIdempotencyKey($idempotencyKey);

        if ($force && trim((string) $reason) === '') {
            throw new BadRequest($this->message('forcedResetReasonRequired'));
        }

        return $this->entityManager->getTransactionManager()->run(function () use (
            $profileId,
            $idempotencyKey,
            $force,
            $reason,
        ): array {
            $existing = $this->findLedgerByKey($idempotencyKey);

            if ($existing) {
                return $this->duplicateResult($existing);
            }

            $profile = $this->lockProfile($profileId);
            $existingAfterLock = $this->findLedgerByKey($idempotencyKey);

            if ($existingAfterLock) {
                return $this->mutationResult($profile, $existingAfterLock, true);
            }

            $before = $this->snapshot($profile);
            $ledger = $this->applyResetGrant(
                $profile,
                $force ? 'resetOverride' : 'annualGrant',
                $idempotencyKey,
                $force ? trim((string) $reason) : null,
                $before,
            );

            return $this->mutationResult($profile, $ledger);
        });
    }

    /** @param array<string, mixed> $item @return array<string, mixed> */
    private function initializeOne(array $item): array
    {
        $userId = trim((string) ($item['userId'] ?? ''));
        $idempotencyKey = trim((string) ($item['idempotencyKey'] ?? ''));
        $nextResetDate = trim((string) ($item['nextResetDate'] ?? ''));
        $annualEntitlement = $this->finiteNumber(
            $item['annualEntitlement'] ?? null,
            $this->message('annualEntitlementField'),
        );
        $openingBalance = $this->finiteNumber(
            $item['openingBalance'] ?? null,
            $this->message('openingBalanceField'),
        );
        $calendarColor = array_key_exists('calendarColor', $item) ?
            $this->validateCalendarColor($item['calendarColor']) : null;

        if ($userId === '') {
            throw new BadRequest($this->message('userIdRequired'));
        }

        $this->validateDate($nextResetDate);
        $this->validateIdempotencyKey($idempotencyKey);

        return $this->entityManager->getTransactionManager()->run(function () use (
            $userId,
            $idempotencyKey,
            $nextResetDate,
            $annualEntitlement,
            $openingBalance,
            $calendarColor,
        ): array {
            $existing = $this->findLedgerByKey($idempotencyKey);

            if ($existing) {
                return $this->duplicateResult($existing);
            }

            $eligibleUser = $this->findEligibleUser($userId);
            $profile = $this->entityManager
                ->getRDBRepository(self::PROFILE)
                ->where(['userId' => $userId])
                ->forUpdate()
                ->findOne();
            $existingAfterLock = $this->findLedgerByKey($idempotencyKey);

            if ($existingAfterLock) {
                return $this->duplicateResult($existingAfterLock);
            }

            $isNew = !$profile;

            if (!$profile) {
                $profile = $this->entityManager->getNewEntity(self::PROFILE);
                $profile->set([
                    'name' => (string) $eligibleUser->get('name'),
                    'userId' => $eligibleUser->getId(),
                    'userName' => $eligibleUser->get('name'),
                    'annualEntitlement' => 0.0,
                    'balance' => 0.0,
                    'nextResetDate' => $nextResetDate,
                    'calendarColor' => $calendarColor ?? self::DEFAULT_CALENDAR_COLOR,
                    'isInitialized' => false,
                    'resetPending' => false,
                ]);
            }

            $before = $this->snapshot($profile);
            $delta = $openingBalance - (float) ($profile->get('balance') ?? 0.0);
            $profile->set([
                'annualEntitlement' => $annualEntitlement,
                'balance' => $openingBalance,
                'nextResetDate' => $nextResetDate,
                'isInitialized' => true,
            ]);

            if ($calendarColor !== null) {
                $profile->set('calendarColor', $calendarColor);
            }

            $this->entityManager->saveEntity($profile);

            $ledger = $this->createLedger(
                $profile,
                $isNew ? 'initialization' : 'bulkUpdate',
                $delta,
                $before,
                $this->snapshot($profile),
                $isNew ? 'Bulk profile initialization' : 'Bulk profile update',
                $idempotencyKey,
            );

            return $this->mutationResult($profile, $ledger);
        });
    }

    public function processDueResets(): int
    {
        $today = $this->dateTime->getToday()->toString();
        $profiles = $this->entityManager
            ->getRDBRepository(self::PROFILE)
            ->where([
                'isInitialized' => true,
                'nextResetDate<=' => $today,
            ])
            ->order('nextResetDate')
            ->find();
        $processed = 0;

        foreach ($profiles as $profile) {
            $nextResetDate = (string) $profile->get('nextResetDate');
            $iteration = 0;

            while ($nextResetDate !== '' && $nextResetDate <= $today && $iteration < 100) {
                $key = sprintf('scheduled-reset:%s:%s', $profile->getId(), $nextResetDate);
                $result = $this->reset((string) $profile->getId(), $key);
                $nextResetDate = (string) $result['nextResetDate'];
                $processed++;
                $iteration++;
            }
        }

        return $processed;
    }

    private function findEligibleUser(string $userId): User
    {
        $eligibleUser = $this->entityManager
            ->getRDBRepositoryByClass(User::class)
            ->where([
                'id' => $userId,
                'isActive' => true,
                'type' => [User::TYPE_REGULAR, User::TYPE_ADMIN],
            ])
            ->findOne();

        if (!$eligibleUser) {
            throw new BadRequest($this->message('eligibleUserRequired'));
        }

        return $eligibleUser;
    }

    private function validateCalendarColor(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
            throw new BadRequest($this->message('calendarColorInvalid'));
        }

        return strtoupper($value);
    }

    private function assertInternalUser(): void
    {
        if (
            !(bool) $this->user->get('isActive') ||
            !in_array($this->user->get('type'), [User::TYPE_REGULAR, User::TYPE_ADMIN], true)
        ) {
            throw new Forbidden($this->message('internalUserOnly'));
        }
    }

    private function assertRequestOwner(string $userId): void
    {
        $this->assertInternalUser();

        if (!$this->user->isAdmin() && $userId !== $this->user->getId()) {
            throw new Forbidden($this->message('ownerOnly'));
        }
    }

    private function assertCancellationRequester(Entity $request): void
    {
        $this->assertInternalUser();

        if ($request->get('assignedUserId') !== $this->user->getId()) {
            throw new Forbidden($this->message('cancellationOwnerOnly'));
        }
    }

    private function assertConfiguredApprover(): void
    {
        $this->assertInternalUser();

        if (!$this->isConfiguredApprover()) {
            throw new Forbidden($this->message('approverOnly'));
        }
    }

    private function isConfiguredApprover(): bool
    {
        $approverIds = $this->config->get('holidayManagementApproversIds') ?? [];

        return
            is_array($approverIds) &&
            is_string($this->user->getId()) &&
            in_array($this->user->getId(), $approverIds, true);
    }

    private function lockProfileByUser(string $userId): Entity
    {
        $profile = $this->entityManager
            ->getRDBRepository(self::PROFILE)
            ->where(['userId' => $userId])
            ->forUpdate()
            ->findOne();

        if (!$profile || !(bool) $profile->get('isInitialized')) {
            throw new BadRequest($this->message('profileNotInitialized'));
        }

        return $profile;
    }

    private function lockHolidayRequest(string $requestId): Entity
    {
        $request = $this->entityManager
            ->getRDBRepository(self::REQUEST)
            ->where(['id' => $requestId])
            ->forUpdate()
            ->findOne();

        if (!$request) {
            throw new NotFound($this->message('requestNotFound'));
        }

        return $request;
    }

    /** @return array<string, mixed> */
    private function cancellationResult(Entity $request): array
    {
        return [
            'id' => $request->getId(),
            'status' => $request->get('status'),
            'cancellationReason' => $request->get('cancellationReason'),
            'cancellationRequestedById' => $request->get('cancellationRequestedById'),
            'cancellationRequestedByName' => $request->get('cancellationRequestedByName'),
            'cancellationRequestedAt' => $request->get('cancellationRequestedAt'),
            'cancellationDecidedById' => $request->get('cancellationDecidedById'),
            'cancellationDecidedByName' => $request->get('cancellationDecidedByName'),
            'cancellationDecidedAt' => $request->get('cancellationDecidedAt'),
        ];
    }

    private function findProfileByUser(string $userId): Entity
    {
        $profile = $this->entityManager
            ->getRDBRepository(self::PROFILE)
            ->where(['userId' => $userId])
            ->findOne();

        if (!$profile || !(bool) $profile->get('isInitialized')) {
            throw new BadRequest($this->message('profileNotInitialized'));
        }

        return $profile;
    }

    /** @return array{string, string} */
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

    private function countWorkingDays(string $dateStart, string $dateEnd): int
    {
        try {
            return $this->workingDayCalculator->count(
                $dateStart,
                $dateEnd,
                $this->nonWorkingDayProvider->getDates($dateStart, $dateEnd),
            );
        } catch (InvalidArgumentException $e) {
            throw new BadRequest($this->translateValidationMessage($e->getMessage()));
        }
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
            'OR' => [
                ['status!=' => self::STATUS_REJECTED],
                ['status' => null],
            ],
        ];

        if (!$request->isNew()) {
            $where['id!='] = $request->getId();
        }

        $overlap = $this->entityManager
            ->getRDBRepository('HolidayRequest')
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
        $limit = (float) ($this->config->get('holidayManagementNegativeBalanceLimitDays') ?? -21.0);
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

    private function lockProfile(string $profileId): Entity
    {
        $profile = $this->entityManager
            ->getRDBRepository(self::PROFILE)
            ->where(['id' => $profileId])
            ->forUpdate()
            ->findOne();

        if (!$profile) {
            throw new NotFound($this->message('profileNotFound'));
        }

        if (!(bool) $profile->get('isInitialized')) {
            throw new BadRequest($this->message('profileNotInitialized'));
        }

        return $profile;
    }

    private function findLedgerByKey(string $idempotencyKey): ?Entity
    {
        return $this->entityManager
            ->getRDBRepository(self::LEDGER)
            ->where(['idempotencyKey' => $idempotencyKey])
            ->findOne();
    }

    /** @param array<string, mixed> $before */
    private function applyResetGrant(
        Entity $profile,
        string $type,
        string $idempotencyKey,
        ?string $reason,
        array $before,
    ): Entity {
        $entitlement = (float) $profile->get('annualEntitlement');
        $balanceBefore = (float) $profile->get('balance');
        $carryOverLimit = $this->getCarryOverLimit();
        $balanceAfter = BalanceMath::calculateResetBalance(
            $balanceBefore,
            $entitlement,
            $carryOverLimit,
        );
        $profile->set([
            'balance' => $balanceAfter,
            'nextResetDate' => $this->nextYear((string) $profile->get('nextResetDate')),
            'resetPending' => false,
            'pendingResetDate' => null,
            'pendingResetKey' => null,
        ]);
        $this->entityManager->saveEntity($profile);

        return $this->createLedger(
            $profile,
            $type,
            $balanceAfter - $balanceBefore,
            $before,
            $this->snapshot($profile),
            $reason ?? sprintf(
                'Annual entitlement %s days; resulting balance %s days (cap %s days).',
                $this->formatDays($entitlement),
                $this->formatDays($balanceAfter),
                $this->formatDays($carryOverLimit),
            ),
            $idempotencyKey,
        );
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    private function createLedger(
        Entity $profile,
        string $type,
        float $delta,
        array $before,
        array $after,
        ?string $reason,
        string $idempotencyKey,
        ?Entity $request = null,
    ): Entity {
        $ledger = $this->entityManager->getNewEntity(self::LEDGER);
        $ledger->set([
            'name' => $type . ' - ' . $profile->get('name'),
            'profileId' => $profile->getId(),
            'profileName' => $profile->get('name'),
            'userId' => $profile->get('userId'),
            'userName' => $profile->get('userName'),
            'requestId' => $request?->getId(),
            'requestName' => $request?->get('name'),
            'type' => $type,
            'delta' => $delta,
            'balanceBefore' => $before['balance'],
            'balanceAfter' => $after['balance'],
            'entitlementBefore' => $before['annualEntitlement'],
            'entitlementAfter' => $after['annualEntitlement'],
            'resetDateBefore' => $before['nextResetDate'],
            'resetDateAfter' => $after['nextResetDate'],
            'actorId' => $this->user->getId(),
            'actorName' => $this->user->get('name'),
            'reason' => $reason,
            'effectiveDate' => gmdate('Y-m-d'),
            'idempotencyKey' => $idempotencyKey,
        ]);
        $this->entityManager->saveEntity($ledger);

        return $ledger;
    }

    /** @return array<string, mixed> */
    private function snapshot(Entity $profile): array
    {
        return [
            'balance' => (float) ($profile->get('balance') ?? 0.0),
            'annualEntitlement' => (float) ($profile->get('annualEntitlement') ?? 0.0),
            'nextResetDate' => $profile->get('nextResetDate'),
        ];
    }

    /** @return array<string, mixed> */
    private function mutationResult(
        Entity $profile,
        Entity $ledger,
        bool $duplicate = false,
        ?Entity $automaticReset = null,
    ): array {
        return [
            'profileId' => $profile->getId(),
            'ledgerId' => $ledger->getId(),
            'balance' => (float) $profile->get('balance'),
            'annualEntitlement' => (float) $profile->get('annualEntitlement'),
            'nextResetDate' => $profile->get('nextResetDate'),
            'calendarColor' => $profile->get('calendarColor') ?: self::DEFAULT_CALENDAR_COLOR,
            'resetPending' => (bool) $profile->get('resetPending'),
            'duplicate' => $duplicate,
            'automaticResetLedgerId' => $automaticReset?->getId(),
        ];
    }

    /** @return array<string, mixed> */
    private function duplicateResult(Entity $ledger): array
    {
        $profile = $this->entityManager
            ->getRDBRepository(self::PROFILE)
            ->where(['id' => $ledger->get('profileId')])
            ->forUpdate()
            ->findOne();

        if (!$profile) {
            throw new NotFound($this->message('operationProfileNotFound'));
        }

        return $this->mutationResult($profile, $ledger, true);
    }

    private function getDefaultNextResetDate(): ?string
    {
        $configured = $this->config->get('holidayManagementResetDate');

        if (!is_string($configured) || !preg_match('/^\d{4}-(\d{2})-(\d{2})$/', $configured, $match)) {
            return null;
        }

        $month = (int) $match[1];
        $day = (int) $match[2];
        $today = $this->dateTime->getToday()->toString();
        $year = (int) substr($today, 0, 4);

        for ($offset = 0; $offset <= 8; $offset++) {
            $candidateYear = $year + $offset;

            if (!checkdate($month, $day, $candidateYear)) {
                continue;
            }

            $candidate = sprintf('%04d-%02d-%02d', $candidateYear, $month, $day);

            if ($candidate >= $today) {
                return $candidate;
            }
        }

        return null;
    }

    private function getCarryOverLimit(): float
    {
        return max(0.0, (float) ($this->config->get('holidayManagementCarryOverLimitDays') ?? 90.0));
    }

    private function validateIdempotencyKey(string $idempotencyKey): void
    {
        if (!preg_match('/^[A-Za-z0-9._:-]{1,190}$/', $idempotencyKey)) {
            throw new BadRequest($this->message('idempotencyKeyInvalid'));
        }
    }

    private function validateDate(string $date): void
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new BadRequest($this->message('resetDateInvalid'));
        }
    }

    private function finiteNumber(mixed $value, string $label): float
    {
        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            throw new BadRequest($this->message('numberRequired', ['field' => $label]));
        }

        if (!is_numeric($value) || !is_finite((float) $value)) {
            throw new BadRequest($this->message('finiteNumberRequired', ['field' => $label]));
        }

        return (float) $value;
    }

    private function nextYear(string $date): string
    {
        $this->validateDate($date);

        return (new DateTimeImmutable($date))->modify('+1 year')->format('Y-m-d');
    }

    /** @param array<string, string> $replacements */
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
