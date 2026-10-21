<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Tools\HolidayBalance;

final class BalanceMath
{
    public static function calculateResetBalance(
        float $balance,
        float $entitlement,
    ): float
    {
        return $balance + $entitlement;
    }

    public static function exceedsWarningThreshold(float $balance, float $threshold): bool
    {
        return $balance > max(0.0, $threshold);
    }
}
