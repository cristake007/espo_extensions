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
use Espo\Entities\User;
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
                'annualEntitlement' => null,
                'nextResetDate' => null,
            ];
        }

        return [
            'initialized' => true,
            'profileId' => $profile->getId(),
            'balance' => (float) $profile->get('balance'),
            'annualEntitlement' => (float) $profile->get('annualEntitlement'),
            'nextResetDate' => $profile->get('nextResetDate'),
        ];
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
            throw new Conflict('Only a pending holiday request can be edited.');
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

        if ($request->get('status') === self::STATUS_REJECTED) {
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
            throw new NotFound('Holiday request not found.');
        }

        $status = (string) ($request->get('status') ?: self::STATUS_PENDING);

        return [
            'id' => $request->getId(),
            'status' => $status,
            'canDecide' =>
                $status === self::STATUS_PENDING &&
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
            ->where(['status' => self::STATUS_PENDING])
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
                'status' => self::STATUS_PENDING,
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
        if (!in_array($decision, [self::STATUS_APPROVED, self::STATUS_REJECTED], true)) {
            throw new BadRequest('Decision must be Approved or Rejected.');
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
                    throw new NotFound('Holiday request not found.');
                }

                $currentStatus = (string) ($request->get('status') ?: self::STATUS_PENDING);

                if ($currentStatus !== self::STATUS_PENDING) {
                    throw new Conflict(sprintf(
                        'This holiday request has already been %s.',
                        strtolower($currentStatus),
                    ));
                }

                $request->set([
                    'status' => $decision,
                    'decidedById' => $this->user->getId(),
                    'decidedByName' => $this->user->get('name'),
                    'decidedAt' => DateTimeUtil::getSystemNowString(),
                ]);
                $this->entityManager->saveEntity($request);

                return [
                    'id' => $request->getId(),
                    'status' => $request->get('status'),
                    'decidedById' => $request->get('decidedById'),
                    'decidedByName' => $request->get('decidedByName'),
                    'decidedAt' => $request->get('decidedAt'),
                ];
            },
        );
    }

    public function processApprovalDecision(Entity $request): void
    {
        $statusBefore = (string) ($request->getFetched('status') ?: self::STATUS_PENDING);
        $statusAfter = (string) $request->get('status');

        if (
            $statusBefore !== self::STATUS_PENDING ||
            !in_array($statusAfter, [self::STATUS_APPROVED, self::STATUS_REJECTED], true)
        ) {
            throw new Conflict('A holiday approval decision can only be made once.');
        }

        $this->assertConfiguredApprover();

        if ($statusAfter !== self::STATUS_REJECTED) {
            return;
        }

        $profile = $this->lockProfileByUser((string) $request->get('assignedUserId'));
        $days = max(0, (int) $request->get('days'));
        $before = $this->snapshot($profile);
        $profile->set('balance', (float) $profile->get('balance') + $days);
        $this->entityManager->saveEntity($profile);
        $this->createLedger(
            $profile,
            'holidayRejected',
            $days,
            $before,
            $this->snapshot($profile),
            sprintf(
                'Holiday rejected for %s through %s.',
                $request->get('dateStartDate'),
                $request->get('dateEndDate'),
            ),
            sprintf(
                'holiday-rejected:%s:%d',
                $request->get('accountingKey'),
                $request->get('accountingRevision'),
            ),
            $request,
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
            throw new BadRequest('At least one profile item is required.');
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
            throw new BadRequest('Correction reason is required.');
        }

        if (!is_finite($delta) || $delta === 0.0) {
            throw new BadRequest('Correction delta must be a non-zero finite number.');
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
            throw new BadRequest('Forced reset reason is required.');
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
        $annualEntitlement = $this->finiteNumber($item['annualEntitlement'] ?? null, 'Annual entitlement');
        $openingBalance = $this->finiteNumber($item['openingBalance'] ?? null, 'Opening balance');
        $calendarColor = array_key_exists('calendarColor', $item) ?
            $this->validateCalendarColor($item['calendarColor']) : null;

        if ($userId === '') {
            throw new BadRequest('User ID is required.');
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
            throw new BadRequest('User must be an active regular or administrator user.');
        }

        return $eligibleUser;
    }

    private function validateCalendarColor(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
            throw new BadRequest('Calendar color must be a six-digit hexadecimal color.');
        }

        return strtoupper($value);
    }

    private function assertInternalUser(): void
    {
        if (
            !(bool) $this->user->get('isActive') ||
            !in_array($this->user->get('type'), [User::TYPE_REGULAR, User::TYPE_ADMIN], true)
        ) {
            throw new Forbidden('Only active internal users can book holiday.');
        }
    }

    private function assertRequestOwner(string $userId): void
    {
        $this->assertInternalUser();

        if (!$this->user->isAdmin() && $userId !== $this->user->getId()) {
            throw new Forbidden('A holiday booking can only be changed by its owner.');
        }
    }

    private function assertConfiguredApprover(): void
    {
        $this->assertInternalUser();

        if (!$this->isConfiguredApprover()) {
            throw new Forbidden('Only a configured holiday approver can make this decision.');
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
            throw new BadRequest('The holiday profile is not initialized.');
        }

        return $profile;
    }

    private function findProfileByUser(string $userId): Entity
    {
        $profile = $this->entityManager
            ->getRDBRepository(self::PROFILE)
            ->where(['userId' => $userId])
            ->findOne();

        if (!$profile || !(bool) $profile->get('isInitialized')) {
            throw new BadRequest('The holiday profile is not initialized.');
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
            throw new BadRequest('Both the first and last holiday day are required.');
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
            throw new BadRequest($e->getMessage());
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
            throw new BadRequest($e->getMessage());
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
            throw new Conflict('The selected dates overlap another holiday booking.');
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
        $action = $isAdjustment ? 'change requires' : 'booking requires';
        $kind = $isAdjustment ? 'additional holiday days' : 'holiday days';

        throw new Conflict(sprintf(
            'This %s %s %s, but only %s are available. Requested: %s days; available: %s days; shortfall: %s days.',
            $action,
            $this->formatDays($daysToDeduct),
            $kind,
            $this->formatDays($availableDays),
            $this->formatDays($daysToDeduct),
            $this->formatDays($availableDays),
            $this->formatDays($shortfallDays),
        ));
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
            throw new NotFound('Holiday profile not found.');
        }

        if (!(bool) $profile->get('isInitialized')) {
            throw new BadRequest('Holiday profile is not initialized.');
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
            throw new NotFound('Holiday profile for existing operation not found.');
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
            throw new BadRequest('A valid idempotency key is required.');
        }
    }

    private function validateDate(string $date): void
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new BadRequest('Reset date must use YYYY-MM-DD.');
        }
    }

    private function finiteNumber(mixed $value, string $label): float
    {
        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            throw new BadRequest($label . ' must be a number.');
        }

        if (!is_numeric($value) || !is_finite((float) $value)) {
            throw new BadRequest($label . ' must be a finite number.');
        }

        return (float) $value;
    }

    private function nextYear(string $date): string
    {
        $this->validateDate($date);

        return (new DateTimeImmutable($date))->modify('+1 year')->format('Y-m-d');
    }
}
