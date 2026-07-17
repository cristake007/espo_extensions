<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\NagerDate;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Utils\Config\ApplicationConfig;
use Espo\Entities\Integration;
use Espo\ORM\EntityManager;
use stdClass;
use Throwable;

final class SyncManager
{
    private const LOCK_TIMEOUT = '15 minutes';

    public function __construct(
        private EntityManager $entityManager,
        private ApplicationConfig $applicationConfig,
        private SettingsProvider $settingsProvider,
        private Schedule $schedule,
    ) {}

    public function runManual(): stdClass
    {
        $now = $this->now();
        $settings = $this->settingsProvider->get($now);

        if (!$settings->enabled) {
            return $this->result('Skipped', 'The Nager.Date integration is disabled.');
        }

        return $this->runPhaseTwoShell($settings, $now);
    }

    public function runAutomatic(): stdClass
    {
        $now = $this->now();
        $settings = $this->settingsProvider->get($now);

        if (!$settings->enabled) {
            return $this->result('Skipped', 'The Nager.Date integration is disabled.');
        }

        if (!$settings->automaticSync) {
            return $this->result('Skipped', 'Automatic synchronization is disabled.');
        }

        if ($settings->frequency === 'ManualOnly') {
            return $this->result('Skipped', 'The synchronization frequency is manual only.');
        }

        $lastAttemptedAt = $this->parseStoredDate($this->settingsProvider->getEntity()->get('lastAttemptedAt'));

        if (!$this->schedule->isDue($settings, $now, $lastAttemptedAt)) {
            return $this->result('Skipped', 'The configured synchronization time is not due.');
        }

        return $this->runPhaseTwoShell($settings, $now);
    }

    private function runPhaseTwoShell(Settings $settings, DateTimeImmutable $now): stdClass
    {
        if (!$this->acquire($now)) {
            return $this->result('Skipped', 'A Nager.Date synchronization is already in progress.');
        }

        try {
            $message = 'Synchronization settings are ready; external retrieval starts in Phase 3.';
            $this->finish($settings, $now, 'Skipped', $message);

            return $this->result('Skipped', $message);
        } catch (Throwable $e) {
            $this->releaseAfterFailure();

            throw $e;
        }
    }

    private function acquire(DateTimeImmutable $now): bool
    {
        $locker = $this->entityManager->getLocker();
        $locker->lockExclusive(Integration::ENTITY_TYPE);

        try {
            $entity = $this->settingsProvider->getEntity();
            $startedAt = $this->parseStoredDate($entity->get('syncStartedAt'));
            $lockIsFresh = $startedAt !== null &&
                $startedAt->modify('+' . self::LOCK_TIMEOUT) > $now->setTimezone(new DateTimeZone('UTC'));

            if ($entity->get('syncInProgress') === true && $lockIsFresh) {
                $locker->rollback();

                return false;
            }

            $entity->set([
                'syncInProgress' => true,
                'syncStartedAt' => $this->storeDate($now),
            ]);
            $this->entityManager->saveEntity($entity);
            $locker->commit();

            return true;
        } catch (Throwable $e) {
            if ($locker->isLocked()) {
                $locker->rollback();
            }

            throw $e;
        }
    }

    private function finish(Settings $settings, DateTimeImmutable $now, string $result, string $message): void
    {
        $entity = $this->settingsProvider->getEntity();
        $nextRun = $this->schedule->nextRun($settings, $now);

        $entity->set([
            'syncInProgress' => false,
            'syncStartedAt' => null,
            'lastAttemptedAt' => $this->storeDate($now),
            'lastResult' => $result,
            'lastRequestedYears' => array_map('strval', $settings->years),
            'lastAcceptedCount' => 0,
            'lastCreatedCount' => 0,
            'lastUpdatedCount' => 0,
            'lastRemovedCount' => 0,
            'lastError' => $result === 'Failed' ? $message : null,
            'nextRunAt' => $nextRun ? $this->storeDate($nextRun) : null,
        ]);
        $this->entityManager->saveEntity($entity);
    }

    private function releaseAfterFailure(): void
    {
        $entity = $this->settingsProvider->getEntity();
        $entity->set(['syncInProgress' => false, 'syncStartedAt' => null]);
        $this->entityManager->saveEntity($entity);
    }

    private function result(string $status, string $message): stdClass
    {
        return (object) [
            'status' => $status,
            'message' => $message,
            'accepted' => 0,
            'created' => 0,
            'updated' => 0,
            'removed' => 0,
        ];
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone($this->applicationConfig->getTimeZone()));
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
