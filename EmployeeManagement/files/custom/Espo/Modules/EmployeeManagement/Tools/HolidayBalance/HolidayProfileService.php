<?php
declare(strict_types=1);
namespace Espo\Modules\EmployeeManagement\Tools\HolidayBalance;
use DateTimeImmutable;
use Espo\Core\Exceptions\{BadRequest, Conflict, NotFound};
use Espo\Core\Utils\{Config, Language};
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Entities\User;
use Espo\ORM\{Entity, EntityManager};
use Espo\Modules\EmployeeManagement\Tools\Administration\AdministrationAccessChecker;

/** Profile subset extracted from HolidayBalanceService, not a replacement ledger. */
final class HolidayProfileService
{
    private const PROFILE = 'EmployeeHolidayProfile';
    private const LEDGER = 'EmployeeHolidayLedger';
    private const REQUEST = 'EmployeeHolidayRequest';
    private const DEFAULT_CALENDAR_COLOR = '#4F8A8B';
    public function __construct(private EntityManager $entityManager, private Config $config,
        private User $user, private DateTimeUtil $dateTime, private Language $language,
        private AdministrationAccessChecker $accessChecker, private \Espo\Core\AclManager $aclManager,
        private \Espo\Modules\EmployeeManagement\Tools\HolidayAdministration\HolidayApproverProvider $approvers) {}

    public function listProfiles(): array
    {
        $this->accessChecker->assertProfiles();
        $profilesByUser = [];
        $defaultEntitlement = $this->config->get('employeeHolidayAnnualEntitlementDays');
        $defaultResetDate = $this->getDefaultNextResetDate();

        foreach ($this->entityManager->getRDBRepository(self::PROFILE)->find() as $profile) {
            $profilesByUser[(string) $profile->get('assignedUserId')] = $profile;
        }

        $result = [];
        $users = $this->entityManager
            ->getRDBRepository(User::ENTITY_TYPE)
            ->where([
                'isActive' => true,
                'type' => User::TYPE_REGULAR,
            ])
            ->order('name')
            ->find();

        foreach ($users as $eligibleUser) {
            $profile = $profilesByUser[$eligibleUser->getId()] ?? null;
            $isInitialized = (bool) ($profile?->get('isInitialized') ?? false);
            $result[] = [
                'userId' => $eligibleUser->getId(),
                'userName' => $eligibleUser->get('name'),
                'profileId' => $profile?->getId(),
                'annualEntitlement' => $isInitialized ?
                    $profile?->get('annualEntitlement') : $defaultEntitlement,
                'balance' => $isInitialized ? $profile?->get('balance') : null,
                'nextResetDate' => $isInitialized ?
                    $profile?->get('nextResetDate') : $defaultResetDate,
                'calendarColor' => $profile?->get('calendarColor') ?: self::DEFAULT_CALENDAR_COLOR,
                'isInitialized' => $isInitialized,
                'resetPending' => (bool) ($profile?->get('resetPending') ?? false),
                'canCompleteInitialSetup' => $profile ? $this->canCompleteInitialSetup($profile) : false,
            ];
        }

        return $result;
    }

    public function bulkInitialize(array $items): array
    {
        $this->accessChecker->assertProfiles();
        if (count($items) > 100) { throw new BadRequest('Too many profile items.'); }
        if ($items === []) {
            throw new BadRequest($this->message('profileItemRequired'));
        }

        return $this->entityManager->getTransactionManager()->run(function () use ($items): array {
            $result = [];
            foreach ($items as $item) {
                if (!is_object($item) && !is_array($item)) { throw new BadRequest('Invalid profile item.'); }
            }
            usort($items, fn ($a, $b) => strcmp((string) (((array) $a)['userId'] ?? ''), (string) (((array) $b)['userId'] ?? '')));
            foreach ($items as $rawItem) {
                $item = is_object($rawItem) ? get_object_vars($rawItem) : $rawItem;
                $result[] = $this->initializeOne($item);
            }
            return $result;
        });
    }

    private function initializeOne(array $item): array
    {
        $this->accessChecker->assertKeys((object) $item, ['userId', 'annualEntitlement', 'openingBalance', 'nextResetDate', 'calendarColor', 'idempotencyKey']);
        foreach (['userId', 'nextResetDate', 'idempotencyKey'] as $key) { if (!is_string($item[$key] ?? null)) { throw new BadRequest('Invalid profile identifier or date type.'); } }
        $userId = trim((string) ($item['userId'] ?? ''));
        $idempotencyKey = trim((string) ($item['idempotencyKey'] ?? ''));
        $nextResetDate = trim((string) ($item['nextResetDate'] ?? ''));
        $annualEntitlement = $this->finiteNumber(
            $item['annualEntitlement'] ?? null,
            $this->message('annualEntitlementField'),
        );
        $openingBalance = array_key_exists('openingBalance', $item) ?
            $this->finiteNumber(
                $item['openingBalance'],
                $this->message('openingBalanceField'),
            ) : null;
        $calendarColor = array_key_exists('calendarColor', $item) ?
            $this->validateCalendarColor($item['calendarColor']) : null;

        if ($userId === '') {
            throw new BadRequest($this->message('userIdRequired'));
        }

        $this->validateDate($nextResetDate);
        $this->validateIdempotencyKey($idempotencyKey);

        $operationHash = hash('sha256', json_encode([$userId, $nextResetDate, $annualEntitlement, $openingBalance, $calendarColor], JSON_THROW_ON_ERROR));
        return $this->entityManager->getTransactionManager()->run(function () use (
            $operationHash,
            $userId,
            $idempotencyKey,
            $nextResetDate,
            $annualEntitlement,
            $openingBalance,
            $calendarColor,
        ): array {
            $eligibleUser = $this->findEligibleUser($userId);
            $existing = $this->findLedgerByKey($idempotencyKey);

            if ($existing) {
                return $this->duplicateResult($existing, $operationHash);
            }

            $profile = $this->entityManager
                ->getRDBRepository(self::PROFILE)
                ->where(['assignedUserId' => $userId])
                ->select(['id','name','assignedUserId','annualEntitlement','balance','nextResetDate','calendarColor','isInitialized','resetPending','pendingResetDate','pendingResetKey'])->forUpdate()
                ->findOne();
            $existingAfterLock = $this->findLedgerByKey($idempotencyKey);

            if ($existingAfterLock) {
                return $this->duplicateResult($existingAfterLock, $operationHash);
            }

            $requiresOpeningBalance = !$profile || !(bool) $profile->get('isInitialized');

            if (!$profile) {
                $profile = $this->entityManager->getNewEntity(self::PROFILE);
                $profile->set([
                    'name' => (string) $eligibleUser->get('name'),
                    'assignedUserId' => $eligibleUser->getId(),
                    'assignedUserName' => $eligibleUser->get('name'),
                    'annualEntitlement' => 0.0,
                    'balance' => 0.0,
                    'nextResetDate' => $nextResetDate,
                    'calendarColor' => $calendarColor ?? self::DEFAULT_CALENDAR_COLOR,
                    'isInitialized' => false,
                    'resetPending' => false,
                ]);
            }

            if ($requiresOpeningBalance && $openingBalance === null) {
                throw new BadRequest($this->message('openingBalanceField'));
            }

            if (!$requiresOpeningBalance && $openingBalance !== null) { throw new BadRequest('Opening balance cannot change an initialized profile.'); }
            $before = $this->snapshot($profile);
            $balance = $requiresOpeningBalance ? $openingBalance : (float) $profile->get('balance');
            $delta = $balance - (float) ($profile->get('balance') ?? 0.0);
            $profile->set([
                'annualEntitlement' => $annualEntitlement,
                'balance' => $balance,
                'nextResetDate' => $nextResetDate,
                'isInitialized' => true,
            ]);

            if ($calendarColor !== null) {
                $profile->set('calendarColor', $calendarColor);
            }

            $this->entityManager->saveEntity($profile);

            $ledger = $this->createLedger(
                $profile,
                $requiresOpeningBalance ? 'initialization' : 'bulkUpdate',
                $delta,
                $before,
                $this->snapshot($profile),
                $requiresOpeningBalance ? 'Profile initialization' : 'Bulk profile update',
                $idempotencyKey, null, $operationHash,
            );

            return $this->mutationResult($profile, $ledger);
        });
    }

    private function findEligibleUser(string $userId): User
    {
        $eligibleUser = $this->entityManager
            ->getRDBRepositoryByClass(User::class)
            ->where([
                'id' => $userId,
                'isActive' => true,
                'type' => User::TYPE_REGULAR,
            ])
            ->select(['id','name','type','isActive','firstName','lastName','workingTimeCalendarId'])->forUpdate()
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

    private function findLedgerByKey(string $idempotencyKey): ?Entity
    {
        return $this->entityManager
            ->getRDBRepository(self::LEDGER)
            ->where(['idempotencyKey' => $idempotencyKey])
            ->findOne();
    }

    public function createLedger(
        Entity $profile,
        string $type,
        float $delta,
        array $before,
        array $after,
        ?string $reason,
        string $idempotencyKey,
        ?Entity $request = null,
        ?string $operationHash = null,
    ): Entity {
        $ledger = $this->entityManager->getNewEntity(self::LEDGER);
        $ledger->set([
            'name' => $type . ' - ' . $profile->get('name'),
            'profileId' => $profile->getId(),
            'profileName' => $profile->get('name'),
            'assignedUserId' => $profile->get('assignedUserId'),
            'assignedUserName' => $profile->get('assignedUserName'),
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
            'operationHash' => $operationHash,
        ]);
        $this->entityManager->saveEntity($ledger);

        return $ledger;
    }

    public function snapshot(Entity $profile): array
    {
        return [
            'balance' => (float) ($profile->get('balance') ?? 0.0),
            'annualEntitlement' => (float) ($profile->get('annualEntitlement') ?? 0.0),
            'nextResetDate' => $profile->get('nextResetDate'),
        ];
    }

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

    private function duplicateResult(Entity $ledger, string $operationHash): array
    {
        if ($ledger->get('operationHash') !== $operationHash) { throw new Conflict('Idempotency key already used for different profile parameters.'); }
        $profile = $this->entityManager
            ->getRDBRepository(self::PROFILE)
            ->where(['id' => $ledger->get('profileId')])
            ->select(['id','name','assignedUserId','annualEntitlement','balance','nextResetDate','calendarColor','isInitialized','resetPending','pendingResetDate','pendingResetKey'])->forUpdate()
            ->findOne();

        if (!$profile) {
            throw new NotFound($this->message('operationProfileNotFound'));
        }

        return $this->mutationResult($profile, $ledger, true);
    }

    private function getDefaultNextResetDate(): ?string
    {
        $configured = $this->config->get('employeeHolidayResetDate');

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

    private function message(string $key, array $replacements = []): string
    {
        $message = $this->language->translateLabel($key, 'messages', self::REQUEST);

        foreach ($replacements as $name => $value) {
            $message = str_replace('{' . $name . '}', $value, $message);
        }

        return $message;
    }

    public function completeInitialSetup(
        string $profileId,
        float $openingBalance,
        string $idempotencyKey,
    ): array {
        $this->accessChecker->assertProfiles();
        $this->validateIdempotencyKey($idempotencyKey);

        if (!is_finite($openingBalance)) {
            throw new BadRequest($this->message('openingBalanceField'));
        }

        $ownerId = $this->profileOwnerId($profileId);
        $operationHash = hash('sha256', json_encode(['openingBalanceSetup', $profileId, $openingBalance], JSON_THROW_ON_ERROR));

        return $this->entityManager->getTransactionManager()->run(function () use (
            $ownerId,
            $operationHash,
            $profileId,
            $openingBalance,
            $idempotencyKey,
        ): array {
            $profile = $this->lockProfile($profileId, $ownerId);
            $existing = $this->findLedgerByKey($idempotencyKey);

            if ($existing) {
                return $this->duplicateResult($existing, $operationHash);
            }

            $profile = $this->lockProfile($profileId, $ownerId);
            $existingAfterLock = $this->findLedgerByKey($idempotencyKey);

            if ($existingAfterLock) {
                return $this->duplicateResult($existingAfterLock, $operationHash);
            }

            if (!$this->canCompleteInitialSetup($profile)) {
                throw new Conflict($this->message('initialSetupNotAvailable'));
            }

            $before = $this->snapshot($profile);
            $profile->set('balance', $openingBalance);
            $this->entityManager->saveEntity($profile);
            $ledger = $this->createLedger(
                $profile,
                'openingBalanceSetup',
                $openingBalance - (float) $before['balance'],
                $before,
                $this->snapshot($profile),
                'Opening balance completed after profile initialization.',
                $idempotencyKey, null, $operationHash,
            );

            return $this->mutationResult($profile, $ledger);
        });
    }

    private function canCompleteInitialSetup(Entity $profile): bool
    {
        if (
            !(bool) $profile->get('isInitialized') ||
            (float) $profile->get('balance') !== 0.0
        ) {
            return false;
        }

        $request = $this->entityManager
            ->getRDBRepository(self::REQUEST)
            ->where(['profileId' => $profile->getId()])
            ->findOne();

        if ($request) {
            return false;
        }

        $hasInitialization = false;
        $entries = $this->entityManager
            ->getRDBRepository(self::LEDGER)
            ->where(['profileId' => $profile->getId()])
            ->find();

        foreach ($entries as $entry) {
            $type = (string) $entry->get('type');

            if ($type === 'initialization') {
                $hasInitialization = true;
            }

            if (
                !in_array($type, ['initialization', 'bulkUpdate'], true) ||
                (float) $entry->get('delta') !== 0.0
            ) {
                return false;
            }
        }

        return $hasInitialization;
    }

    public function reset(
        string $profileId,
        string $idempotencyKey,
        bool $force = false,
        ?string $reason = null,
        ?string $expectedDueDate = null,
    ): array {
        if (!$this->user->isSystem()) {
            $this->accessChecker->assertProfiles();
            $this->accessChecker->assertScope('EmployeeHolidayBalanceCorrection');
            if ($expectedDueDate !== null) { throw new BadRequest('Internal due-date argument.'); }
        }
        $ownerId = $this->profileOwnerId($profileId);
        $operationHash = hash('sha256', json_encode(['annual-reset', $profileId, $force, $reason, $expectedDueDate], JSON_THROW_ON_ERROR));
        $this->validateIdempotencyKey($idempotencyKey);

        if ($force && trim((string) $reason) === '') {
            throw new BadRequest($this->message('forcedResetReasonRequired'));
        }

        return $this->entityManager->getTransactionManager()->run(function () use (
            $ownerId,
            $profileId,
            $idempotencyKey,
            $force,
            $reason, $expectedDueDate, $operationHash,
        ): array {
            // Same User -> Profile order as booking, corrections and approval.
            $profile = $this->lockProfile($profileId, $ownerId);
            $existing = $this->findLedgerByKey($idempotencyKey);

            if ($existing) {
                return $this->duplicateResult($existing, $operationHash);
            }

            $profile = $this->lockProfile($profileId, $ownerId);
            $existingAfterLock = $this->findLedgerByKey($idempotencyKey);

            if ($existingAfterLock) {
                return $this->duplicateResult($existingAfterLock, $operationHash);
            }

            if ($expectedDueDate !== null && (
                $this->config->get('employeeHolidayAutomaticAnnualGrantEnabled') !== true ||
                $profile->get('nextResetDate') !== $expectedDueDate ||
                $expectedDueDate > $this->dateTime->getToday()->toString()
            )) { return ['skipped' => true, 'nextResetDate' => $profile->get('nextResetDate')]; }
            $before = $this->snapshot($profile);
            $ledger = $this->applyResetGrant(
                $profile,
                $force ? 'resetOverride' : 'annualGrant',
                $idempotencyKey,
                $force ? trim((string) $reason) : null,
                $before, $operationHash,
            );

            return $this->mutationResult($profile, $ledger);
        });
    }


    public function processDueResets(): int
    {
        if (!$this->user->isSystem()) { throw new \Espo\Core\Exceptions\Forbidden('Annual processing is an internal native Job operation.'); }
        if ($this->config->get('employeeHolidayAutomaticAnnualGrantEnabled') !== true) {
            return 0;
        }

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
            $owner = $this->entityManager->getEntityById('User', (string) $profile->get('assignedUserId'));
            if (!$owner instanceof User || !$owner->isRegular() || !$owner->isActive()) {
                continue;
            }

            $nextResetDate = (string) $profile->get('nextResetDate');
            $iteration = 0;

            while ($nextResetDate !== '' && $nextResetDate <= $today && $iteration < 100) {
                $key = sprintf('scheduled-reset:%s:%s', $profile->getId(), $nextResetDate);
                $result = $this->reset((string) $profile->getId(), $key, false, null, $nextResetDate);
                $nextResetDate = (string) $result['nextResetDate'];
                if (!($result['duplicate'] ?? false) && !($result['skipped'] ?? false)) { $processed++; }
                $iteration++;
                if ($result['skipped'] ?? false) { break; }
            }
        }

        return $processed;
    }


    private function applyResetGrant(
        Entity $profile,
        string $type,
        string $idempotencyKey,
        ?string $reason,
        array $before,
        string $operationHash,
    ): Entity {
        $entitlement = (float) $profile->get('annualEntitlement');
        $balanceBefore = (float) $profile->get('balance');
        $warningThreshold = $this->getBalanceWarningThreshold();
        $balanceAfter = BalanceMath::calculateResetBalance(
            $balanceBefore,
            $entitlement,
        );
        $profile->set([
            'balance' => $balanceAfter,
            'nextResetDate' => $this->nextYear((string) $profile->get('nextResetDate')),
            'resetPending' => false,
            'pendingResetDate' => null,
            'pendingResetKey' => null,
        ]);
        $this->entityManager->saveEntity($profile);

        $ledger = $this->createLedger(
            $profile,
            $type,
            $balanceAfter - $balanceBefore,
            $before,
            $this->snapshot($profile),
            $reason ?? sprintf(
                'Annual entitlement %s days; resulting balance %s days (warning threshold %s days).',
                $this->formatDays($entitlement),
                $this->formatDays($balanceAfter),
                $this->formatDays($warningThreshold),
            ),
            $idempotencyKey, null, $operationHash,
        );

        if (BalanceMath::exceedsWarningThreshold($balanceAfter, $warningThreshold)) {
            $this->notifyBalanceWarning($profile, $balanceAfter, $warningThreshold);
        }

        return $ledger;
    }

    private function notifyBalanceWarning(
        Entity $profile,
        float $balance,
        float $threshold,
    ): void {
        $employeeId = $profile->get('assignedUserId');
        $employeeName = (string) ($profile->get('assignedUserName') ?: $profile->get('name'));
        $balanceText = $this->formatDays($balance);
        $thresholdText = $this->formatDays($threshold);

        $employee = is_string($employeeId) ? $this->entityManager->getEntityById('User', $employeeId) : null;
        $employeeAcl = $employee instanceof User ? $this->aclManager->createUserAcl($employee) : null;
        if ($employee instanceof User && $employee->isRegular() && $employee->isActive() &&
            $employeeAcl->checkEntity($profile, 'read') && $employeeAcl->checkField(self::PROFILE, 'balance')) {
            $this->createBalanceWarningNotification(
                $employeeId,
                $profile,
                $this->message('balanceWarningEmployeeNotification', [
                    'balance' => $balanceText,
                    'threshold' => $thresholdText,
                ]),
                $balance,
                $threshold,
            );
        }

        foreach ($this->approvers->users() as $candidate) {
            $approverId = $candidate->getId();
            if ($approverId === $employeeId) {
                continue;
            }
            $acl = $this->aclManager->createUserAcl($candidate);
            if (!$acl->checkScope('EmployeeHolidayApproval') || !$acl->checkEntity($profile, 'read') ||
                !$acl->checkField(self::PROFILE, 'balance')) { continue; }

            $this->createBalanceWarningNotification(
                $approverId,
                $profile,
                $this->message('balanceWarningApproverNotification', [
                    'employee' => $employeeName,
                    'balance' => $balanceText,
                    'threshold' => $thresholdText,
                ]),
                $balance,
                $threshold,
            );
        }
    }

    private function createBalanceWarningNotification(
        string $recipientId,
        Entity $profile,
        string $message,
        float $balance,
        float $threshold,
    ): void {
        $notification = $this->entityManager->getNewEntity(\Espo\Entities\Notification::ENTITY_TYPE);
        $notification->set([
            'type' => \Espo\Entities\Notification::TYPE_MESSAGE,
            'userId' => $recipientId,
            'read' => false,
            'message' => $message,
            'relatedType' => self::PROFILE,
            'relatedId' => $profile->getId(),
            'data' => [
                'employeeHolidayType' => 'balanceWarning',
                'holidayProfileId' => $profile->getId(),
                'balance' => $balance,
                'warningThreshold' => $threshold,
            ],
        ]);
        $this->entityManager->saveEntity($notification);
    }


    private function getBalanceWarningThreshold(): float
    {
        return max(0.0, (float) ($this->config->get('employeeHolidayCarryOverLimitDays') ?? 90.0));
    }

    private function formatDays(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }


    private function nextYear(string $date): string
    {
        $this->validateDate($date);

        return (new DateTimeImmutable($date))->modify('+1 year')->format('Y-m-d');
    }


    /** Resolve before entering a transaction: do not establish a stale RR snapshot before the User lock. */
    private function profileOwnerId(string $profileId): string
    {
        $profile = $this->entityManager->getRDBRepository(self::PROFILE)->where(['id' => $profileId])
            ->select(['assignedUserId'])->findOne();
        if (!$profile) { throw new NotFound($this->message('profileNotFound')); }
        return (string) $profile->get('assignedUserId');
    }

    private function lockProfile(string $profileId, string $ownerId): Entity
    {
        $this->findEligibleUser($ownerId);
        $profile = $this->entityManager
            ->getRDBRepository(self::PROFILE)
            ->where(['id' => $profileId])
            ->select(['id','name','assignedUserId','annualEntitlement','balance','nextResetDate','calendarColor','isInitialized','resetPending','pendingResetDate','pendingResetKey'])->forUpdate()
            ->findOne();

        if (!$profile) {
            throw new NotFound($this->message('profileNotFound'));
        }

        if ($profile->get('assignedUserId') !== $ownerId) { throw new Conflict('Profile ownership changed. Reload.'); }

        if (!(bool) $profile->get('isInitialized')) {
            throw new BadRequest($this->message('profileNotInitialized'));
        }

        $this->findEligibleUser((string) $profile->get('assignedUserId'));

        return $profile;
    }
    public function correct(
        string $profileId,
        float $delta,
        string $reason,
        string $idempotencyKey,
        ?float $expectedBalance = null,
    ): array {
        $this->accessChecker->assertProfiles();
        $this->accessChecker->assertScope('EmployeeHolidayBalanceCorrection');
        $this->validateIdempotencyKey($idempotencyKey);

        if (trim($reason) === '') {
            throw new BadRequest($this->message('correctionReasonRequired'));
        }

        if (!is_finite($delta) || $delta === 0.0) {
            throw new BadRequest($this->message('correctionDeltaInvalid'));
        }

        if ($expectedBalance !== null && !is_finite($expectedBalance)) { throw new BadRequest('Expected balance must be finite.'); }
        $ownerId = $this->profileOwnerId($profileId);
        $operationHash = hash('sha256', json_encode(['correction', $profileId, $delta, trim($reason), $expectedBalance], JSON_THROW_ON_ERROR));
        return $this->entityManager->getTransactionManager()->run(function () use (
            $ownerId,
            $operationHash,
            $expectedBalance,
            $profileId,
            $delta,
            $reason,
            $idempotencyKey,
        ): array {
            $profile = $this->lockProfile($profileId, $ownerId);
            $existing = $this->findLedgerByKey($idempotencyKey);

            if ($existing) {
                return $this->duplicateResult($existing, $operationHash);
            }

            $profile = $this->lockProfile($profileId, $ownerId);
            $existingAfterLock = $this->findLedgerByKey($idempotencyKey);

            if ($existingAfterLock) {
                return $this->duplicateResult($existingAfterLock, $operationHash);
            }

            $before = $this->snapshot($profile);
            if ($expectedBalance !== null && (float) $profile->get('balance') !== $expectedBalance) { throw new Conflict('Balance changed since the correction form was opened. Reload the profile.'); }
            $afterBalance = (float) $profile->get('balance') + $delta;

            if (!is_finite($afterBalance)) { throw new BadRequest('The resulting balance must be finite.'); }

            $profile->set('balance', $afterBalance);
            $this->entityManager->saveEntity($profile);

            $ledger = $this->createLedger(
                $profile,
                'correction',
                $delta,
                $before,
                $this->snapshot($profile),
                trim($reason),
                $idempotencyKey, null, $operationHash,
            );

            return $this->mutationResult($profile, $ledger);
        });
    }

    public function listCorrections(string $profileId): array
    {
        $this->accessChecker->assertProfiles();
        $this->accessChecker->assertScope('EmployeeHolidayBalanceCorrection');
        $profile = $this->entityManager
            ->getRDBRepository(self::PROFILE)
            ->where(['id' => $profileId])
            ->findOne();

        if (!$profile) {
            throw new NotFound($this->message('profileNotFound'));
        }

        $owner = $this->entityManager->getEntityById('User', (string) $profile->get('assignedUserId'));
        if (!$owner || !$owner->isRegular() || !$owner->isActive()) { throw new BadRequest('Ineligible employee.'); }
        $result = [];
        $entries = $this->entityManager
            ->getRDBRepository(self::LEDGER)
            ->where([
                'profileId' => $profileId,
                'type' => 'correction',
            ])
            ->order('createdAt', 'DESC')
            ->find();

        foreach ($entries as $entry) {
            $result[] = [
                'id' => $entry->getId(),
                'delta' => (float) $entry->get('delta'),
                'balanceBefore' => (float) $entry->get('balanceBefore'),
                'balanceAfter' => (float) $entry->get('balanceAfter'),
                'actorName' => (string) ($entry->get('actorName') ?? ''),
                'reason' => (string) ($entry->get('reason') ?? ''),
                'effectiveDate' => $entry->get('effectiveDate'),
                'createdAt' => $entry->get('createdAt'),
            ];
        }

        return $result;
    }
}
