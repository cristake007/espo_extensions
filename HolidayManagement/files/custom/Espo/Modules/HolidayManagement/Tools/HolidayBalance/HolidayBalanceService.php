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
        $this->assertBalanceLimit($balanceAfter);

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

        $balanceAfter = (float) $profile->get('balance') - $dayDifference;
        $this->assertBalanceLimit($balanceAfter);
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

    /** @return array<int, array<string, mixed>> */
    public function listProfiles(): array
    {
        $profilesByUser = [];

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
                'annualEntitlement' => $profile?->get('annualEntitlement'),
                'balance' => $profile?->get('balance'),
                'nextResetDate' => $profile?->get('nextResetDate'),
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

            $automaticReset = $this->applyPendingResetIfEligible($profile);

            return $this->mutationResult($profile, $ledger, false, $automaticReset);
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
            $balance = (float) $profile->get('balance');
            $entitlement = (float) $profile->get('annualEntitlement');
            $canApply = BalanceMath::canApplyReset($balance, $entitlement, $this->getCeiling());

            if (!$canApply && !$force) {
                $profile->set([
                    'resetPending' => true,
                    'pendingResetDate' => $profile->get('nextResetDate'),
                    'pendingResetKey' => $idempotencyKey,
                ]);
                $this->entityManager->saveEntity($profile);

                $ledger = $this->createLedger(
                    $profile,
                    'resetPending',
                    0.0,
                    $before,
                    $this->snapshot($profile),
                    null,
                    $idempotencyKey,
                );

                return $this->mutationResult($profile, $ledger);
            }

            $type = $force && !$canApply ? 'resetOverride' : 'annualGrant';
            $ledger = $this->applyResetGrant(
                $profile,
                $type,
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

            $automaticReset = $this->applyPendingResetIfEligible($profile);

            return $this->mutationResult($profile, $ledger, false, $automaticReset);
        });
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

    private function assertBalanceLimit(float $balance): void
    {
        $limit = (float) ($this->config->get('holidayManagementNegativeBalanceLimitDays') ?? -21.0);

        if ($balance < $limit) {
            throw new Conflict(sprintf(
                'This booking would exceed the minimum holiday balance of %s days.',
                $limit,
            ));
        }
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
        $profile->set([
            'balance' => BalanceMath::applyEntitlement((float) $profile->get('balance'), $entitlement),
            'nextResetDate' => $this->nextYear((string) $profile->get('nextResetDate')),
            'resetPending' => false,
            'pendingResetDate' => null,
            'pendingResetKey' => null,
        ]);
        $this->entityManager->saveEntity($profile);

        return $this->createLedger(
            $profile,
            $type,
            $entitlement,
            $before,
            $this->snapshot($profile),
            $reason,
            $idempotencyKey,
        );
    }

    private function applyPendingResetIfEligible(Entity $profile): ?Entity
    {
        if (!(bool) $profile->get('resetPending')) {
            return null;
        }

        $balance = (float) $profile->get('balance');
        $entitlement = (float) $profile->get('annualEntitlement');

        if (!BalanceMath::canApplyReset($balance, $entitlement, $this->getCeiling())) {
            return null;
        }

        $before = $this->snapshot($profile);
        $pendingKey = (string) $profile->get('pendingResetKey');
        $automaticKey = 'automatic-reset:' . hash('sha256', $profile->getId() . ':' . $pendingKey);
        $existing = $this->findLedgerByKey($automaticKey);

        if ($existing) {
            return $existing;
        }

        return $this->applyResetGrant(
            $profile,
            'automaticReset',
            $automaticKey,
            'Pending reset became eligible',
            $before,
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

    private function getCeiling(): float
    {
        return (float) ($this->config->get('holidayManagementResetCeilingDays') ?? 90.0);
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
