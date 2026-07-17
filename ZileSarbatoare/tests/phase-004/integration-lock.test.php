<?php

declare(strict_types=1);

namespace Espo\Entities {
    class Integration
    {
        /** @param array<string, mixed> $values */
        public function __construct(private array $values = [])
        {}

        public function get(string $name): mixed
        {
            return $this->values[$name] ?? null;
        }

        public function set(array|string $name, mixed $value = null): void
        {
            if (is_array($name)) {
                $this->values = array_replace($this->values, $name);

                return;
            }

            $this->values[$name] = $value;
        }
    }
}

namespace Espo\ORM {
    use Closure;
    use Espo\Entities\Integration;

    final class FakeTransactionManager
    {
        public int $runCount = 0;

        public function run(Closure $operation): mixed
        {
            $this->runCount++;

            return $operation();
        }
    }

    final class FakeSelectBuilder
    {
        public int $forUpdateCount = 0;
        /** @var array<string, mixed>|null */
        public ?array $where = null;

        public function __construct(public ?Integration $entity)
        {}

        public function forUpdate(): self
        {
            $this->forUpdateCount++;

            return $this;
        }

        /** @param array<string, mixed> $where */
        public function where(array $where): self
        {
            $this->where = $where;

            return $this;
        }

        public function findOne(): ?Integration
        {
            return $this->entity;
        }
    }

    class EntityManager
    {
        public FakeTransactionManager $transactionManager;
        public FakeSelectBuilder $repository;
        public int $saveCount = 0;

        public function __construct(?Integration $entity)
        {
            $this->transactionManager = new FakeTransactionManager();
            $this->repository = new FakeSelectBuilder($entity);
        }

        public function getTransactionManager(): FakeTransactionManager
        {
            return $this->transactionManager;
        }

        public function getRDBRepositoryByClass(string $className): FakeSelectBuilder
        {
            return $this->repository;
        }

        public function saveEntity(object $entity): void
        {
            $this->saveCount++;
        }
    }
}

namespace {
    use Espo\Entities\Integration;
    use Espo\Modules\ZileSarbatoare\Tools\NagerDate\IntegrationSyncLock;
    use Espo\Modules\ZileSarbatoare\Tools\NagerDate\LockPolicy;
    use Espo\ORM\EntityManager;

    $source = __DIR__ . '/../../files/custom/Espo/Modules/ZileSarbatoare/Tools/NagerDate';
    require_once "$source/SyncLock.php";
    require_once "$source/LockPolicy.php";
    require_once "$source/IntegrationSyncLock.php";

    function assertSameValue(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException($message . ' Expected ' . var_export($expected, true) .
                ', received ' . var_export($actual, true) . '.');
        }
    }

    $now = new DateTimeImmutable('2026-07-17 10:00:00', new DateTimeZone('UTC'));
    $entity = new Integration();
    $entityManager = new EntityManager($entity);
    $lock = new IntegrationSyncLock($entityManager, new LockPolicy());

    $token = $lock->acquire($now);

    if (!is_string($token) || strlen($token) !== 32) {
        throw new RuntimeException('Lock acquisition did not return a 128-bit hexadecimal ownership token.');
    }

    assertSameValue(true, $entity->get('syncInProgress'), 'Acquisition did not mark the lock active.');
    assertSameValue(
        hash('sha256', $token),
        $entity->get('syncLockTokenHash'),
        'Acquisition did not store only the ownership-token hash.',
    );
    assertSameValue(['id' => 'NagerDate'], $entityManager->repository->where, 'Wrong row was locked.');
    assertSameValue(1, $entityManager->repository->forUpdateCount, 'Acquisition did not use FOR UPDATE.');
    assertSameValue(1, $entityManager->transactionManager->runCount, 'Acquisition was not transactional.');

    assertSameValue(null, $lock->acquire($now->modify('+1 minute')), 'A fresh lock was stolen.');
    assertSameValue(false, $lock->refresh('wrong-token', $now), 'A foreign token refreshed the lock.');
    $lock->release('wrong-token');
    assertSameValue(true, $entity->get('syncInProgress'), 'A foreign token released the lock.');

    assertSameValue(true, $lock->refresh($token, $now->modify('+2 minutes')), 'Owner could not refresh lock.');
    assertSameValue('2026-07-17 10:02:00', $entity->get('syncStartedAt'), 'Refresh timestamp is invalid.');
    $lock->release($token);
    assertSameValue(false, $entity->get('syncInProgress'), 'Owner could not release lock.');
    assertSameValue(null, $entity->get('syncLockTokenHash'), 'Release retained the token hash.');

    $entity->set([
        'syncInProgress' => true,
        'syncStartedAt' => '2026-07-17 09:40:00',
        'syncLockTokenHash' => hash('sha256', 'expired-token'),
    ]);
    $replacementToken = $lock->acquire($now);

    if (!is_string($replacementToken) || $replacementToken === $token) {
        throw new RuntimeException('An expired lock was not replaced with a new ownership token.');
    }

    $missingManager = new EntityManager(null);
    $missingLock = new IntegrationSyncLock($missingManager, new LockPolicy());

    try {
        $missingLock->acquire($now);
        throw new RuntimeException('Missing Integration row did not fail lock acquisition.');
    } catch (RuntimeException $e) {
        assertSameValue(
            'The Nager.Date integration record does not exist.',
            $e->getMessage(),
            'Missing-row failure was not actionable.',
        );
    }

    echo "PHASE-004 Integration row-lock tests passed.\n";
}
