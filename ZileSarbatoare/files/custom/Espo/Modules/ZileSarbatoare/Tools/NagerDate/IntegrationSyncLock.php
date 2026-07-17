<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\NagerDate;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Entities\Integration;
use Espo\ORM\EntityManager;
use RuntimeException;

final class IntegrationSyncLock implements SyncLock
{
    private const INTEGRATION = 'NagerDate';

    public function __construct(
        private EntityManager $entityManager,
        private LockPolicy $policy,
    ) {}

    public function acquire(DateTimeImmutable $now): ?string
    {
        return $this->entityManager->getTransactionManager()->run(function () use ($now): ?string {
            $entity = $this->getEntityForUpdate();
            $startedAt = $this->parseStoredDate($entity->get('syncStartedAt'));
            $utcNow = $now->setTimezone(new DateTimeZone('UTC'));

            if ($this->policy->isOwnedByActiveRun(
                $entity->get('syncInProgress') === true,
                $startedAt,
                $utcNow,
            )) {
                return null;
            }

            $token = bin2hex(random_bytes(16));
            $entity->set([
                'syncInProgress' => true,
                'syncStartedAt' => $this->storeDate($now),
                'syncLockTokenHash' => hash('sha256', $token),
            ]);
            $this->entityManager->saveEntity($entity);

            return $token;
        });
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
        $this->entityManager->getTransactionManager()->run(function () use ($operation): void {
            $entity = $this->getEntityForUpdate();
            $operation($entity);
        });
    }

    private function getEntityForUpdate(): Integration
    {
        $entity = $this->entityManager
            ->getRDBRepositoryByClass(Integration::class)
            ->forUpdate()
            ->where(['id' => self::INTEGRATION])
            ->findOne();

        if (!$entity instanceof Integration) {
            throw new RuntimeException('The Nager.Date integration record does not exist.');
        }

        return $entity;
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
