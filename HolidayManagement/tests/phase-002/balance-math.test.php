<?php

declare(strict_types=1);

require_once __DIR__ . '/../../files/custom/Espo/Modules/HolidayManagement/Tools/HolidayBalance/BalanceMath.php';

use Espo\Modules\HolidayManagement\Tools\HolidayBalance\BalanceMath;

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . " Expected " . var_export($expected, true) . ", got " . var_export($actual, true));
    }
}

assertSameValue(31.0, BalanceMath::calculateResetBalance(10.0, 21.0), 'Positive carry-over failed.');
assertSameValue(101.0, BalanceMath::calculateResetBalance(80.0, 21.0), 'Entitlement was capped at the warning threshold.');
assertSameValue(111.0, BalanceMath::calculateResetBalance(90.0, 21.0), 'Balance at the warning threshold lost entitlement.');
assertSameValue(121.0, BalanceMath::calculateResetBalance(100.0, 21.0), 'Balance above the warning threshold lost days.');
assertSameValue(16.0, BalanceMath::calculateResetBalance(-5.0, 21.0), 'Deficit deduction failed.');
assertSameValue(-9.0, BalanceMath::calculateResetBalance(-30.0, 21.0), 'Large deficit deduction failed.');
assertSameValue(false, BalanceMath::exceedsWarningThreshold(90.0, 90.0), 'Threshold equality must not warn.');
assertSameValue(true, BalanceMath::exceedsWarningThreshold(90.01, 90.0), 'Threshold excess must warn.');
assertSameValue(true, BalanceMath::exceedsWarningThreshold(1.0, 0.0), 'Zero threshold must warn without capping.');

echo "PHASE-002 balance math tests passed.\n";
