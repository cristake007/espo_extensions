<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\NagerDate;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Utils\Config\ApplicationConfig;
use Espo\Core\Utils\Log;
use InvalidArgumentException;
use stdClass;
use Throwable;

final class SyncManager
{
    public function __construct(
        private ApplicationConfig $applicationConfig,
        private SettingsProvider $settingsProvider,
        private Schedule $schedule,
        private SyncLock $syncLock,
        private SynchronizationRunner $runner,
        private Log $log,
    ) {}

    public function runManual(): stdClass
    {
        return $this->run(false);
    }

    public function runAutomatic(): stdClass
    {
        return $this->run(true);
    }

    private function run(bool $automatic): stdClass
    {
        $now = $this->now();
        try {
            $token = $this->syncLock->acquire($now);
        } catch (Throwable $e) {
            $this->log->warning('ZileSarbatoare synchronization lock could not be acquired.', [
                'mode' => $automatic ? 'automatic' : 'manual',
                'exceptionClass' => $e::class,
            ]);

            return $this->result('Failed', 'The synchronization lock could not be acquired safely.');
        }

        if ($token === null) {
            return $this->result('Skipped', 'A Nager.Date synchronization is already in progress.');
        }

        $settings = null;
        $reconciliation = null;

        try {
            $settings = $this->settingsProvider->get($now);

            if (!$settings->enabled) {
                return $this->result('Skipped', 'The Nager.Date integration is disabled.');
            }

            if ($automatic && !$settings->automaticSync) {
                return $this->result('Skipped', 'Automatic synchronization is disabled.');
            }

            if ($automatic && $settings->frequency === 'ManualOnly') {
                return $this->result('Skipped', 'The synchronization frequency is manual only.');
            }

            if ($automatic && !$this->isDue($settings, $now)) {
                return $this->result('Skipped', 'The configured synchronization time is not due.');
            }

            $reconciliation = $this->runner->run(
                $settings,
                $now,
                fn (): bool => $this->syncLock->refresh($token, $this->now()),
            );
            $this->recordSuccess($settings, $now, $reconciliation);
            $this->log->info('ZileSarbatoare synchronization completed.', $this->logContext(
                $automatic,
                $settings,
                $reconciliation,
            ));

            return $this->result(
                'Success',
                sprintf(
                    'Synchronization completed: %d accepted, %d created, %d updated, %d removed.',
                    $reconciliation->accepted,
                    $reconciliation->created,
                    $reconciliation->updated,
                    $reconciliation->removed,
                ),
                $reconciliation,
            );
        } catch (ConcurrentSyncException) {
            return $this->result('Skipped', 'Synchronization lock ownership was transferred to another run.');
        } catch (Throwable $e) {
            if ($reconciliation !== null) {
                $this->log->error('ZileSarbatoare post-run reporting failed after a committed synchronization.', [
                    'mode' => $automatic ? 'automatic' : 'manual',
                    'exceptionClass' => $e::class,
                ]);

                return $this->result(
                    'Success',
                    'Synchronization completed, but post-run reporting could not be completed.',
                    $reconciliation,
                );
            }

            [$category, $safeMessage] = $this->safeFailure($e);
            $this->recordFailureBestEffort($settings, $now, $safeMessage);
            $this->log->warning('ZileSarbatoare synchronization failed.', [
                'mode' => $automatic ? 'automatic' : 'manual',
                'category' => $category,
                'exceptionClass' => $e::class,
                'countryCode' => $settings?->countryCode,
                'years' => $settings?->years ?? [],
            ]);

            return $this->result('Failed', $safeMessage);
        } finally {
            try {
                $this->syncLock->release($token);
            } catch (Throwable $e) {
                $this->log->error('ZileSarbatoare synchronization lock release failed.', [
                    'exceptionClass' => $e::class,
                ]);
            }
        }
    }

    private function isDue(Settings $settings, DateTimeImmutable $now): bool
    {
        $lastAttemptedAt = $this->parseStoredDate(
            $this->settingsProvider->getEntity()->get('lastAttemptedAt')
        );

        return $this->schedule->isDue($settings, $now, $lastAttemptedAt);
    }

    private function recordSuccess(
        Settings $settings,
        DateTimeImmutable $now,
        ReconciliationResult $result,
    ): void {
        $entity = $this->settingsProvider->getEntity();
        $entity->set([
            'lastAttemptedAt' => $this->storeDate($now),
            'lastSuccessfulAt' => $this->storeDate($now),
            'lastResult' => 'Success',
            'lastRequestedYears' => array_map('strval', $settings->years),
            'lastAcceptedCount' => $result->accepted,
            'lastCreatedCount' => $result->created,
            'lastUpdatedCount' => $result->updated,
            'lastRemovedCount' => $result->removed,
            'lastError' => null,
            'nextRunAt' => $this->nextRun($settings, $now),
        ]);
        $this->settingsProvider->saveEntity($entity);
    }

    private function recordFailureBestEffort(
        ?Settings $settings,
        DateTimeImmutable $now,
        string $safeMessage,
    ): void {
        try {
            $entity = $this->settingsProvider->getEntity();
            $entity->set([
                'lastAttemptedAt' => $this->storeDate($now),
                'lastResult' => 'Failed',
                'lastRequestedYears' => $settings ? array_map('strval', $settings->years) : [],
                'lastAcceptedCount' => 0,
                'lastCreatedCount' => 0,
                'lastUpdatedCount' => 0,
                'lastRemovedCount' => 0,
                'lastError' => $safeMessage,
                'nextRunAt' => $settings ? $this->nextRun($settings, $now) : null,
            ]);
            $this->settingsProvider->saveEntity($entity);
        } catch (Throwable $e) {
            $this->log->error('ZileSarbatoare failure status could not be saved.', [
                'exceptionClass' => $e::class,
            ]);
        }
    }

    /** @return array{string, string} */
    private function safeFailure(Throwable $e): array
    {
        if ($e instanceof ClientException) {
            return [$e->getCategory(), $e->getMessage()];
        }

        if ($e instanceof InvalidArgumentException) {
            return ['validation', 'Validated holiday data could not be reconciled safely.'];
        }

        return ['database', 'The synchronization could not be saved; existing holiday data was preserved.'];
    }

    /** @return array<string, mixed> */
    private function logContext(
        bool $automatic,
        Settings $settings,
        ReconciliationResult $result,
    ): array {
        return [
            'mode' => $automatic ? 'automatic' : 'manual',
            'countryCode' => $settings->countryCode,
            'years' => $settings->years,
            'accepted' => $result->accepted,
            'created' => $result->created,
            'updated' => $result->updated,
            'removed' => $result->removed,
        ];
    }

    private function result(
        string $status,
        string $message,
        ?ReconciliationResult $counts = null,
    ): stdClass {
        return (object) [
            'status' => $status,
            'message' => $message,
            'accepted' => $counts?->accepted ?? 0,
            'created' => $counts?->created ?? 0,
            'updated' => $counts?->updated ?? 0,
            'removed' => $counts?->removed ?? 0,
        ];
    }

    private function nextRun(Settings $settings, DateTimeImmutable $now): ?string
    {
        $nextRun = $this->schedule->nextRun($settings, $now);

        return $nextRun ? $this->storeDate($nextRun) : null;
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
