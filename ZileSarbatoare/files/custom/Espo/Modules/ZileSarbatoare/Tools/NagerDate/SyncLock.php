<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\NagerDate;

use DateTimeImmutable;

interface SyncLock
{
    /** Returns an ownership token, or null when another run owns the lock. */
    public function acquire(DateTimeImmutable $now): ?string;

    /** Renews the lease only when the token still owns it. */
    public function refresh(string $token, DateTimeImmutable $now): bool;

    public function release(string $token): void;
}
