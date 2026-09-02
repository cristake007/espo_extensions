<?php

declare(strict_types=1);

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Core\ORM\EntityManager;
use Espo\Entities\Attachment;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\WordComparisonService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use WordPressUpdaterTest\Record;

$testRoot = dirname(__DIR__) . '/wordpress-updater';
$sourceRoot = dirname(__DIR__, 2) . '/files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri';

require_once $testRoot . '/espo-service-test-double.php';

if (!class_exists(Spreadsheet::class)) {
    require_once $testRoot . '/phpspreadsheet-test-double.php';
}

require_once $sourceRoot . '/CourseTitleHeaderResolver.php';
require_once $sourceRoot . '/WordComparisonService.php';

$checks = 0;
$failures = [];

$assertSame = static function (mixed $expected, mixed $actual, string $message) use (&$checks, &$failures): void {
    $checks++;

    if ($expected !== $actual) {
        $failures[] = $message . '\n  expected: ' . var_export($expected, true) . '\n  actual:   ' . var_export($actual, true);
    }
};

$assertContains = static function (string $needle, string $actual, string $message) use (&$checks, &$failures): void {
    $checks++;

    if (!str_contains($actual, $needle)) {
        $failures[] = $message . '\n  expected to contain: ' . var_export($needle, true) . '\n  actual: ' . var_export($actual, true);
    }
};

$captureException = static function (callable $callback): ?Throwable {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception;
    }

    return null;
};

$findRowByWordTitle = static function (array $rows, string $title): ?array {
    foreach ($rows as $row) {
        if ($row['wordTitle'] === $title) {
            return $row;
        }
    }

    return null;
};

$findRowByExcelTitle = static function (array $rows, string $title): ?array {
    foreach ($rows as $row) {
        if ($row['excelTitle'] === $title) {
            return $row;
        }
    }

    return null;
};

$makeXlsx = static function (array $header, array $rows): string {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Program');
    $sheet->fromArray($header, null, 'A1');

    foreach ($rows as $offset => $row) {
        $sheet->fromArray($row, null, 'A' . ($offset + 2));
    }

    $writer = new Xlsx($spreadsheet);
    ob_start();
    $writer->save('php://output');
    $contents = ob_get_clean();
    $spreadsheet->disconnectWorksheets();

    return is_string($contents) ? $contents : '';
};

$makeDocx = static function (array $rows): string {
    $path = tempnam(sys_get_temp_dir(), 'word-comparison-service-');

    if ($path === false) {
        throw new RuntimeException('Unable to create the test DOCX path.');
    }

    $cell = static fn (string $value): string =>
        '<w:tc><w:p><w:r><w:t>' . htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</w:t></w:r></w:p></w:tc>';
    $tr = static fn (array $cells): string =>
        '<w:tr>' . implode('', array_map($cell, $cells)) . '</w:tr>';

    $body = implode('', array_map($tr, $rows));
    $documentXml = '<?xml version="1.0" encoding="UTF-8"?>' .
        '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl>' .
        $body .
        '</w:tbl></w:body></w:document>';

    try {
        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the test DOCX.');
        }

        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();
        $contents = file_get_contents($path);

        if (!is_string($contents)) {
            throw new RuntimeException('Unable to read the test DOCX.');
        }

        return $contents;
    } finally {
        @unlink($path);
    }
};

$entityManager = new EntityManager();
$fileStorageManager = new FileStorageManager();
$service = new WordComparisonService($entityManager, $fileStorageManager);
$entityType = 'GeneratorPerioadeCursuriWordComparator';

$setUpRecord = static function (
    string $recordId,
    string $wordBytes,
    string $excelBytes
) use ($entityManager, $fileStorageManager, $entityType): void {
    $entityManager->entities[$entityType . ':' . $recordId] = new Record($recordId, [
        'wordTemplateFileId' => $recordId . '-word',
        'wordScheduleFileId' => $recordId . '-excel',
    ]);
    $entityManager->entities['Attachment:' . $recordId . '-word'] = new Attachment(
        $recordId . '-word',
        'template.docx',
        $entityType,
        $recordId,
        'wordTemplateFile'
    );
    $entityManager->entities['Attachment:' . $recordId . '-excel'] = new Attachment(
        $recordId . '-excel',
        'export.xlsx',
        $entityType,
        $recordId,
        'wordScheduleFile'
    );
    $fileStorageManager->contents[$recordId . '-word'] = $wordBytes;
    $fileStorageManager->contents[$recordId . '-excel'] = $excelBytes;
};

// --- Scenario 1: a course renamed in Word (SR EN ISO 9001:2015 -> ISO 9001:2026), a
// course that only exists in Word, and a course that only exists on the website.

$wordRows = [
    ['Indicatori de performanță bazat pe ISO 9001:2026', '5 zile', '1500 lei', '', '', ''],
    ['Managementul proiectelor europene', '3 zile', '900 lei', '', '', ''],
];
$wordBytes = $makeDocx($wordRows);

$excelHeader = ['title', 'Permalink', 'Durata Curs', 'Investitie', 'Ianuarie'];
$excelRows = [
    ['Indicatori de performanță bazat pe SR EN ISO 9001:2015', '/curs/iso9001', '6 zile', '1500,00 lei', '05-06.01.2026'],
    ['Expert achizitii publice', '/curs/altul', '2 zile', '500 lei', '10.01.2026'],
];
$excelBytes = $makeXlsx($excelHeader, $excelRows);

$setUpRecord('record-1', $wordBytes, $excelBytes);
$result = $service->compare('record-1');

$assertSame(true, $result['success'], 'A successful comparison must report success.');
$assertSame(2, $result['wordCount'], 'Every compatible Word row must be counted.');
$assertSame(2, $result['excelCount'], 'Every compatible Excel row must be counted.');
$assertSame(1, $result['matchedCount'], 'The renamed course must still be paired across Word and the website.');
$assertSame(1, $result['wordOnlyCount'], 'A Word row without a plausible Excel counterpart must be counted as Word-only.');
$assertSame(1, $result['excelOnlyCount'], 'An Excel row never matched must be counted as Excel-only.');
$assertSame(3, count($result['rows']), 'The unified row list must include the matched pair, the Word-only row, and the Excel-only row together (no separate tables).');

$renamedRow = $findRowByWordTitle($result['rows'], 'Indicatori de performanță bazat pe ISO 9001:2026');
$assertSame(true, $renamedRow['matched'] ?? null, 'A retitled course (dropped "SR EN", changed year) must still be recognized as the same course.');
$assertSame(
    'Indicatori de performanță bazat pe SR EN ISO 9001:2015',
    $renamedRow['excelTitle'] ?? null,
    'The matched row must carry the website title alongside the Word title.'
);
$assertSame('different', $renamedRow['title']['status'], 'A changed course name must be flagged as different even though the courses were paired.');
$assertSame('different', $renamedRow['duration']['status'], '5 zile vs 6 zile must be reported as different.');
$assertSame('5 zile', $renamedRow['duration']['word'], 'The Word duration text must be preserved.');
$assertSame('6 zile', $renamedRow['duration']['excel'], 'The Excel duration text must be preserved.');
$assertSame(
    'same',
    $renamedRow['price']['status'],
    '1500 lei and 1500,00 lei must compare equal once formatting is normalized.'
);

$wordOnlyRow = $findRowByWordTitle($result['rows'], 'Managementul proiectelor europene');
$assertSame(false, $wordOnlyRow['matched'] ?? null, 'A Word course with no plausible Excel counterpart must stay unmatched.');
$assertSame(null, $wordOnlyRow['excelTitle'], 'An unmatched Word row must not carry an Excel title.');

$excelOnlyRow = $findRowByExcelTitle($result['rows'], 'Expert achizitii publice');
$assertSame(false, $excelOnlyRow['matched'] ?? null, 'An Excel course with no plausible Word counterpart must stay unmatched.');
$assertSame(null, $excelOnlyRow['wordTitle'], 'An Excel-only row must not carry a Word title, and must live in the same rows list as everything else.');

// --- Scenario 2: two similarly worded but genuinely different courses (different ISO
// standard numbers) must not be force-paired just because the surrounding words match.

$distinctWordRows = [
    ['Auditor intern ISO 9001', '2 zile', '800 lei', '', '', ''],
];
$distinctWordBytes = $makeDocx($distinctWordRows);
$distinctExcelBytes = $makeXlsx(
    ['title', 'Permalink', 'Durata Curs', 'Investitie', 'Ianuarie'],
    [['Auditor intern ISO 14001', '/curs/14001', '2 zile', '800 lei', '10.01.2026']]
);

$setUpRecord('record-2', $distinctWordBytes, $distinctExcelBytes);
$distinctResult = $service->compare('record-2');

$assertSame(0, $distinctResult['matchedCount'], 'Courses naming two different ISO standards must not be paired despite similar wording.');
$assertSame(1, $distinctResult['wordOnlyCount'], 'The Word-only ISO 9001 course must be reported on its own.');
$assertSame(1, $distinctResult['excelOnlyCount'], 'The Excel-only ISO 14001 course must be reported on its own.');

// --- Missing required Excel columns must fail with a clear message.

$missingColumnsExcel = $makeXlsx(['title'], [['Curs fara coloane']]);
$setUpRecord('record-3', $wordBytes, $missingColumnsExcel);
$missingColumnsException = $captureException(fn () => $service->compare('record-3'));
$assertSame(true, $missingColumnsException instanceof BadRequest, 'A schedule Excel file missing Durata Curs must raise a BadRequest.');
$assertContains('Durata Curs', $missingColumnsException?->getMessage() ?? '', 'The error must name the missing duration column.');

// --- A record without an uploaded Word document must fail before any file is read.

$entityManager->entities[$entityType . ':record-4'] = new Record('record-4', [
    'wordTemplateFileId' => null,
    'wordScheduleFileId' => 'record-1-excel',
]);
$missingWordException = $captureException(fn () => $service->compare('record-4'));
$assertSame(true, $missingWordException instanceof BadRequest, 'A record without a Word document must raise a BadRequest.');

if ($failures !== []) {
    fwrite(STDERR, "Word comparison service: " . count($failures) . " failure(s) across {$checks} checks.\n" . implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "Word comparison service: {$checks} checks passed; no network used.\n");
