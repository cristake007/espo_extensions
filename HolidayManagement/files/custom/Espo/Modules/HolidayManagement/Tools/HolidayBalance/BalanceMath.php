<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Tools\HolidayBalance;

final class BalanceMath
{
    public static function calculateResetBalance(
        float $balance,
        float $entitlement,
        float $balanceCap,
    ): float
    {
        return min($balance + $entitlement, max(0.0, $balanceCap));
    }
}
