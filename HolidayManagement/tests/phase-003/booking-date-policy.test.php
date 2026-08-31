<?php

declare(strict_types=1);

use Espo\Modules\HolidayManagement\Tools\HolidayRequest\BookingDatePolicy;

require_once __DIR__ . '/../../files/custom/Espo/Modules/HolidayManagement/Tools/HolidayRequest/BookingDatePolicy.php';

$policy = new BookingDatePolicy();
$today = '2026-08-31';
$checks = 0;

$policy->assertAllowed('2026-08-31', '2026-08-31', $today);
$checks++;
$policy->assertAllowed('2026-09-01', '2026-09-04', $today);
$checks++;

try {
    $policy->assertAllowed('2026-07-07', '2026-07-07', $today);
    throw new RuntimeException('A new backdated request was accepted.');
} catch (InvalidArgumentException $e) {
    $checks++;
}

$policy->assertAllowed(
    '2026-07-07',
    '2026-07-07',
    $today,
    '2026-07-07',
    '2026-07-07',
);
$checks++;

try {
    $policy->assertAllowed(
        '2026-07-08',
        '2026-07-08',
        $today,
        '2026-07-07',
        '2026-07-07',
    );
    throw new RuntimeException('A historical request was moved to another backdate.');
} catch (InvalidArgumentException $e) {
    $checks++;
}

echo "PHASE-003 booking-date policy: {$checks} checks passed.\n";
