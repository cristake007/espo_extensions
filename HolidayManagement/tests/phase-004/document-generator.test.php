<?php

declare(strict_types=1);

use Espo\Modules\HolidayManagement\Tools\HolidayDocument\HolidayDocumentGenerator;

require_once __DIR__ . '/../../files/custom/Espo/Modules/HolidayManagement/Tools/HolidayDocument/HolidayDocumentGenerator.php';

function assertContains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message . ': missing ' . $needle);
    }
}

$contents = (new HolidayDocumentGenerator())->render([
    'dateStart' => '29.10.2026',
    'dateEnd' => '30.10.2026',
    'year' => '2026',
    'month' => '10',
    'requestedDays' => '2',
    'lastName' => 'Ionescu-Test',
    'firstName' => 'Ana-Test',
    'approvalBlock1Title' => 'Director Test',
    'approvalBlock1Name' => 'Aprobator Unu',
    'approvalBlock2Title' => 'Economic Test',
    'approvalBlock2Name' => 'Aprobator Doi',
    'totalDays' => '21',
    'daysAlreadyUsed' => '10',
    'balanceBefore' => '11',
    'balanceAfter' => '9',
    'documentDate' => '14.09.2026',
]);

$temporaryPath = tempnam(sys_get_temp_dir(), 'holiday-docx-test-');

if ($temporaryPath === false || file_put_contents($temporaryPath, $contents) === false) {
    throw new RuntimeException('Could not write the generated DOCX test file.');
}

try {
    $zip = new ZipArchive();

    if ($zip->open($temporaryPath) !== true) {
        throw new RuntimeException('Generated content is not a valid DOCX ZIP archive.');
    }

    $documentXml = $zip->getFromName('word/document.xml');
    $zip->close();

    if ($documentXml === false) {
        throw new RuntimeException('Generated DOCX has no Word document body.');
    }

    foreach ([
        'De la: 29.10.2026',
        'Pana la: 30.10.2026',
        'Ionescu-Test',
        'Ana-Test',
        'Director Test',
        'Aprobator Unu',
        'Economic Test',
        'Aprobator Doi',
        '14.09.2026',
    ] as $expected) {
        assertContains($expected, $documentXml, 'Generated DOCX was not populated');
    }

    assertContains('21 - 2026', $documentXml, 'Annual total and year were not populated');
} finally {
    if (is_file($temporaryPath)) {
        unlink($temporaryPath);
    }
}

echo "PHASE-004 DOCX generator tests passed.\n";
