<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Tools\HolidayBalance;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Utils\Config;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use stdClass;

final class HolidayBalanceService
{
    private const PROFILE = 'HolidayProfile';
    private const LEDGER = 'HolidayLedger';

    public function __construct(
        private EntityManager $entityManager,
        private Config $config,
        private User $user,
    ) {}

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
    ): Entity {
        $ledger = $this->entityManager->getNewEntity(self::LEDGER);
        $ledger->set([
            'name' => $type . ' - ' . $profile->get('name'),
            'profileId' => $profile->getId(),
            'profileName' => $profile->get('name'),
            'userId' => $profile->get('userId'),
            'userName' => $profile->get('userName'),
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
