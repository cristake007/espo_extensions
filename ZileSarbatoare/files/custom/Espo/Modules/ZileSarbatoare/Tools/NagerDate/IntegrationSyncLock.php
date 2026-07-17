<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\NagerDate;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Entities\Integration;
use Espo\ORM\EntityManager;
use Throwable;

final class IntegrationSyncLock implements SyncLock
{
    public function __construct(
        private EntityManager $entityManager,
        private SettingsProvider $settingsProvider,
        private LockPolicy $policy,
    ) {}

    public function acquire(DateTimeImmutable $now): ?string
    {
        $locker = $this->entityManager->getLocker();
        $locker->lockExclusive(Integration::ENTITY_TYPE);

        try {
            $entity = $this->settingsProvider->getEntity();
            $startedAt = $this->parseStoredDate($entity->get('syncStartedAt'));
            $utcNow = $now->setTimezone(new DateTimeZone('UTC'));

            if ($this->policy->isOwnedByActiveRun(
                $entity->get('syncInProgress') === true,
                $startedAt,
                $utcNow,
            )) {
                $locker->rollback();

                return null;
            }

            $token = bin2hex(random_bytes(16));
            $entity->set([
                'syncInProgress' => true,
                'syncStartedAt' => $this->storeDate($now),
                'syncLockTokenHash' => hash('sha256', $token),
            ]);
            $this->entityManager->saveEntity($entity);
            $locker->commit();

            return $token;
        } catch (Throwable $e) {
            if ($locker->isLocked()) {
                $locker->rollback();
            }

            throw $e;
        }
    }

    public function release(string $token): void
    {
        $this->withLockedEntity(function (Integration $entity) use ($token): void {
            if (!$this->policy->tokenOwnsLock($entity->get('syncLockTokenHash'), $token)) {
                return;
            }

            $entity->set([
                'syncInProgress' => false,
                'syncStartedAt' => null,
                'syncLockTokenHash' => null,
            ]);
            $this->entityManager->saveEntity($entity);
        });
    }

    public function refresh(string $token, DateTimeImmutable $now): bool
    {
        $refreshed = false;

        $this->withLockedEntity(function (Integration $entity) use ($token, $now, &$refreshed): void {
            if (!$this->policy->tokenOwnsLock($entity->get('syncLockTokenHash'), $token)) {
                return;
            }

            $entity->set('syncStartedAt', $this->storeDate($now));
            $this->entityManager->saveEntity($entity);
            $refreshed = true;
        });

        return $refreshed;
    }

    /** @param callable(Integration): void $operation */
    private function withLockedEntity(callable $operation): void
    {
        $locker = $this->entityManager->getLocker();
        $locker->lockExclusive(Integration::ENTITY_TYPE);

        try {
            $entity = $this->settingsProvider->getEntity();
            $operation($entity);
            $locker->commit();
        } catch (Throwable $e) {
            if ($locker->isLocked()) {
                $locker->rollback();
            }

            throw $e;
        }
    }

    private function storeDate(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function parseStoredDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
