<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\NagerDate;

use DateTimeImmutable;
use DateTimeZone;
use Closure;

final class SynchronizationRunner
{
    public function __construct(
        private HolidayProvider $holidayProvider,
        private Reconciler $reconciler,
    ) {}

    /** @param ?Closure(): bool $beforeReconciliation */
    public function run(
        Settings $settings,
        DateTimeImmutable $now,
        ?Closure $beforeReconciliation = null,
    ): ReconciliationResult
    {
        $holidays = [];

        foreach ($settings->years as $year) {
            array_push(
                $holidays,
                ...$this->holidayProvider->fetch(
                    $settings->countryCode,
                    $year,
                    $settings->holidayTypes,
                    $settings->nationalOnly,
                ),
            );
        }

        if ($beforeReconciliation && !$beforeReconciliation()) {
            throw new ConcurrentSyncException('Synchronization lock ownership was lost.');
        }

        return $this->reconciler->reconcile(
            $settings->countryCode,
            $settings->years,
            $holidays,
            $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        );
    }
}
