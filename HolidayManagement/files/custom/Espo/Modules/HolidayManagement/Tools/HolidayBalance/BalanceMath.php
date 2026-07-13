<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Tools\HolidayBalance;

final class BalanceMath
{
    public static function canApplyReset(float $balance, float $entitlement, float $ceiling): bool
    {
        return $balance + $entitlement <= $ceiling;
    }

    public static function applyEntitlement(float $balance, float $entitlement): float
    {
        return $balance + $entitlement;
    }
}
