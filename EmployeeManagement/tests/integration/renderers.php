<?php

declare(strict_types=1);

if (getenv('EMPLOYEE_TEST_LAB') !== 'employee-feasibility-20261020') {
    throw new RuntimeException('Run renderer tests only in the isolated lab.');
}
chdir('/var/www/html');
require 'bootstrap.php';
$app = new Espo\Core\Application();
if ($app->getContainer()->get('config')->get('version') !== '10.0.1') {
    throw new RuntimeException('Untested runtime.');
}

$results = [];
function check(string $name, bool $ok): void
{
    global $results;
    $row = ['test' => $name, 'status' => $ok ? 'PASS' : 'FAIL'];
    $results[] = $row;
    echo json_encode($row) . PHP_EOL;
    if (!$ok) { throw new RuntimeException($name); }
}

use Espo\Modules\EmployeeManagement\Tools\Attendance\AttendancePdfGenerator;
use Espo\Modules\EmployeeManagement\Tools\Attendance\AttendanceXlsxGenerator;
use Espo\Modules\EmployeeManagement\Tools\HolidayDocument\HolidayDocumentGenerator;
use Espo\Modules\EmployeeManagement\Tools\HolidayBalance\BalanceMath;
use Espo\Modules\EmployeeManagement\Tools\EmployeeHolidayRequest\BookingDatePolicy;

$overview = [
    'month' => '2026-11',
    'users' => [['id' => 'synthetic', 'name' => 'Synthetic EM', 'schedule' => ['startTime' => '08:00', 'endTime' => '16:00', 'remote' => true]]],
    'rows' => [
        ['date' => '2026-11-02', 'cells' => [['status' => 'AtWork']]],
        ['date' => '2026-11-03', 'cells' => [['status' => 'ChildCareLeave']]],
        ['date' => '2026-11-04', 'cells' => [['status' => 'Holiday']]],
    ],
];
$pdf = (new AttendancePdfGenerator())->generate($overview);
check('reused-pdf-valid-signature', str_starts_with($pdf['contents'], '%PDF-') && strlen($pdf['contents']) > 1000);

$xlsx = (new AttendanceXlsxGenerator())->generate($overview);
$path = tempnam(sys_get_temp_dir(), 'employee-xlsx-test-');
try {
    file_put_contents($path, $xlsx['contents']);
    $zip = new ZipArchive();
    check('reused-xlsx-valid-zip', $zip->open($path) === true);
    $strings = (string) $zip->getFromName('xl/sharedStrings.xml');
    check('reused-xlsx-preserves-remote-CIC-CO', str_contains($strings, 'Remote') && str_contains($strings, 'CIC') && str_contains($strings, 'CO'));
    check('reused-xlsx-sheet', $zip->getFromName('xl/worksheets/sheet1.xml') !== false);
    $zip->close();
} finally { if (is_file($path)) { unlink($path); } }

$docx = (new HolidayDocumentGenerator())->render([
    'dateStart' => '03.11.2026', 'dateEnd' => '03.11.2026', 'year' => '2026', 'month' => '11', 'requestedDays' => '1',
    'lastName' => 'EM-Test', 'firstName' => 'Synthetic', 'approvalBlock1Title' => 'Manager', 'approvalBlock1Name' => 'Synthetic Manager',
    'approvalBlock2Title' => '', 'approvalBlock2Name' => '', 'totalDays' => '21', 'daysAlreadyUsed' => '0',
    'balanceBefore' => '21', 'balanceAfter' => '20', 'documentDate' => '20.10.2026',
]);
$path = tempnam(sys_get_temp_dir(), 'employee-docx-test-');
try {
    file_put_contents($path, $docx);
    $zip = new ZipArchive();
    check('reused-docx-valid-zip', $zip->open($path) === true);
    $xml = (string) $zip->getFromName('word/document.xml');
    check('reused-docx-values', str_contains($xml, 'EM-Test') && str_contains($xml, 'Synthetic Manager') && str_contains($xml, '03.11.2026'));
    $zip->close();
} finally { if (is_file($path)) { unlink($path); } }

foreach ([[10, 21, 31], [80, 21, 101], [-5, 21, 16], [-30, 21, -9]] as [$balance, $entitlement, $expected]) {
    check('reused-balance-' . $balance, BalanceMath::calculateResetBalance($balance, $entitlement) === (float) $expected);
}
$policy = new BookingDatePolicy();
$policy->assertAllowed('2026-11-03', '2026-11-03', '2026-10-20');
check('reused-booking-future', true);
try {
    $policy->assertAllowed('2026-10-01', '2026-10-01', '2026-10-20');
    check('reused-booking-reject-backdate', false);
} catch (InvalidArgumentException) { check('reused-booking-reject-backdate', true); }
$policy->assertAllowed('2026-10-01', '2026-10-01', '2026-10-20', '2026-10-01', '2026-10-01');
check('reused-booking-unchanged-history', true);
file_put_contents('/feasibility/employee-stage1-renderer-results.json', json_encode($results, JSON_PRETTY_PRINT));
