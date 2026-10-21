<?php

declare(strict_types=1);

use Espo\Modules\EmployeeManagement\Tools\Attendance\AttendancePdfGenerator;

require_once dirname(__DIR__, 2) . '/files/custom/Espo/Modules/EmployeeManagement/Tools/Attendance/' .
    'AttendancePdfGenerator.php';

function assertPdfLayout(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$users = [];

for ($index = 1; $index <= 7; $index++) {
    $users[] = [
        'name' => 'Angajat ' . $index,
        'schedule' => [
            'startTime' => '23:59',
            'endTime' => '00:00',
            'remote' => false,
        ],
    ];
}

$rows = [[
    'date' => '2026-10-31',
    'cells' => array_fill(0, 7, ['status' => 'BusinessTrip']),
]];

$generator = (new ReflectionClass(AttendancePdfGenerator::class))->newInstanceWithoutConstructor();
$buildPage = new ReflectionMethod(AttendancePdfGenerator::class, 'buildPage');
$buildPage->setAccessible(true);
$html = $buildPage->invoke($generator, 'Test PDF', $users, $rows, array_keys($users));
$statusLabel = new ReflectionMethod(AttendancePdfGenerator::class, 'statusLabel');
$statusLabel->setAccessible(true);

assertPdfLayout(is_string($html), 'PDF page HTML was not generated.');
assertPdfLayout(
    $statusLabel->invoke($generator, 'AtWork') === 'Prezent',
    'The AtWork status must be rendered as Prezent in the Romanian PDF.',
);
assertPdfLayout(
    substr_count($html, '>23:59</td>') === 7,
    'The widest time fixture must remain intact for all seven employees.',
);
assertPdfLayout(
    str_contains($html, 'header-label time-cell') &&
        str_contains($html, 'header-label status-header'),
    'Time and attendance headings must use the same header font class.',
);
assertPdfLayout(
    !str_contains($html, 'header-label status-value'),
    'The reduced status-value font must not be applied to the attendance heading.',
);
assertPdfLayout(
    preg_match('/time-cell" width="([0-9.]+)%/', $html, $timeMatch) === 1 &&
        preg_match('/status-header" width="([0-9.]+)%/', $html, $statusMatch) === 1 &&
        (float) $statusMatch[1] > (float) $timeMatch[1] * 2,
    'The attendance column must be more than twice as wide as the time column.',
);
assertPdfLayout(
    substr_count($html, '>In deplasare</td>') === 7,
    'The longest attendance label must remain intact for all seven employees.',
);

echo "Attendance PDF layout tests passed.\n";
