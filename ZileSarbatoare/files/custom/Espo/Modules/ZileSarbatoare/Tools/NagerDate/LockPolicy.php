<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\NagerDate;

use DateTimeImmutable;

final class LockPolicy
{
    private const TIMEOUT = '15 minutes';

    public function isOwnedByActiveRun(
        bool $inProgress,
        ?DateTimeImmutable $startedAt,
        DateTimeImmutable $now,
    ): bool {
        return $inProgress && $startedAt !== null && $startedAt->modify('+' . self::TIMEOUT) > $now;
    }

    public function tokenOwnsLock(?string $storedTokenHash, string $candidateToken): bool
    {
        return $storedTokenHash !== null && hash_equals($storedTokenHash, hash('sha256', $candidateToken));
    }
}
