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

assertSameValue(31.0, BalanceMath::applyEntitlement(10.0, 21.0), 'Positive carry-over failed.');
assertSameValue(16.0, BalanceMath::applyEntitlement(-5.0, 21.0), 'Deficit carry-over failed.');
assertSameValue(false, BalanceMath::canApplyReset(80.0, 21.0, 90.0), '80 + 21 must remain pending.');
assertSameValue(false, BalanceMath::canApplyReset(70.0, 21.0, 90.0), '70 + 21 must remain pending.');
assertSameValue(true, BalanceMath::canApplyReset(69.0, 21.0, 90.0), '69 + 21 must apply.');
assertSameValue(true, BalanceMath::canApplyReset(60.0, 21.0, 90.0), '60 + 21 must apply.');

echo "PHASE-002 balance math tests passed.\n";
