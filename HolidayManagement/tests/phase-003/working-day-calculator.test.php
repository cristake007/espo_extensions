<?php

declare(strict_types=1);

require_once __DIR__ . '/../../files/custom/Espo/Modules/HolidayManagement/Tools/HolidayRequest/WorkingDayCalculator.php';

use Espo\Modules\HolidayManagement\Tools\HolidayRequest\WorkingDayCalculator;

$calculator = new WorkingDayCalculator();
$checks = 0;

$assertSame = static function (mixed $expected, mixed $actual, string $message) use (&$checks): void {
    $checks++;

    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . ' Expected ' . var_export($expected, true) .
            ', received ' . var_export($actual, true) . '.',
        );
    }
};

$assertThrows = static function (callable $operation, string $message) use (&$checks): void {
    $checks++;

    try {
        $operation();
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException($message);
};

$assertSame(1, $calculator->count('2026-08-31', '2026-08-31'), 'One Monday must count once.');
$assertSame(5, $calculator->count('2026-08-31', '2026-09-06'), 'A full week must count five days.');
$assertSame(2, $calculator->count('2026-09-04', '2026-09-07'), 'A weekend bridge must count Friday and Monday.');
$assertSame(
    1,
    $calculator->count(
        '2026-11-29',
        '2026-12-02',
        ['2026-11-30', '2026-12-01'],
    ),
    'Romanian non-working days must be excluded from the selected range.',
);
$assertSame(
    1,
    $calculator->count(
        '2026-11-30',
        '2026-12-02',
        ['2026-11-30', '2026-11-30', '2026-12-01'],
    ),
    'Duplicate non-working-day records must not affect the result.',
);
$assertSame(261, $calculator->count('2026-01-01', '2026-12-31'), 'The 2026 weekday count changed.');

$assertThrows(
    fn () => $calculator->count('2026-09-06', '2026-09-05'),
    'A reversed range was accepted.',
);
$assertThrows(
    fn () => $calculator->count('2026-09-05', '2026-09-06'),
    'A weekend-only range was accepted.',
);
$assertThrows(
    fn () => $calculator->count('2026-02-30', '2026-03-01'),
    'An invalid date was accepted.',
);
$assertThrows(
    fn () => $calculator->count('2026-01-01', '2028-01-03'),
    'An excessive range was accepted.',
);

echo "PHASE-003 working-day calculator: {$checks} checks passed.\n";
