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

assertSameValue(31.0, BalanceMath::calculateResetBalance(10.0, 21.0, 90.0), 'Positive carry-over failed.');
assertSameValue(90.0, BalanceMath::calculateResetBalance(80.0, 21.0, 90.0), 'Reset balance cap failed.');
assertSameValue(90.0, BalanceMath::calculateResetBalance(90.0, 21.0, 90.0), 'Balance at cap must remain capped.');
assertSameValue(90.0, BalanceMath::calculateResetBalance(100.0, 21.0, 90.0), 'Balance above cap must return to the cap.');
assertSameValue(16.0, BalanceMath::calculateResetBalance(-5.0, 21.0, 90.0), 'Deficit deduction failed.');
assertSameValue(-9.0, BalanceMath::calculateResetBalance(-30.0, 21.0, 90.0), 'Large deficit deduction failed.');

echo "PHASE-002 balance math tests passed.\n";
