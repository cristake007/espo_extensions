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

// A word row that has a duration mismatch, a price mismatch, and an exact title match.
$wordRows = [
    ['Curs de auditor intern', '5 zile', '1500 lei', '', '', ''],
    ['Curs care nu exista pe site', '3 zile', '900 lei', '', '', ''],
];
$wordBytes = $makeDocx($wordRows);

$excelHeader = ['title', 'Permalink', 'Durata Curs', 'Investitie', 'Ianuarie'];
$excelRows = [
    ['Curs de auditor intern', '/curs/auditor', '6 zile', '1500,00 lei', '05-06.01.2026'],
    ['Curs care nu are pereche in Word', '/curs/altul', '2 zile', '500 lei', '10.01.2026'],
];
$excelBytes = $makeXlsx($excelHeader, $excelRows);

$entityManager = new EntityManager();
$fileStorageManager = new FileStorageManager();
$service = new WordComparisonService($entityManager, $fileStorageManager);
$entityType = 'GeneratorPerioadeCursuriWordComparator';

$entityManager->entities[$entityType . ':record-1'] = new Record('record-1', [
    'wordTemplateFileId' => 'word-1',
    'wordScheduleFileId' => 'excel-1',
]);
$entityManager->entities['Attachment:word-1'] = new Attachment('word-1', 'template.docx', $entityType, 'record-1', 'wordTemplateFile');
$entityManager->entities['Attachment:excel-1'] = new Attachment('excel-1', 'export.xlsx', $entityType, 'record-1', 'wordScheduleFile');
$fileStorageManager->contents['word-1'] = $wordBytes;
$fileStorageManager->contents['excel-1'] = $excelBytes;

$result = $service->compare('record-1');

$assertSame(true, $result['success'], 'A successful comparison must report success.');
$assertSame(2, $result['wordCount'], 'Every compatible Word row must be counted.');
$assertSame(2, $result['excelCount'], 'Every compatible Excel row must be counted.');
$assertSame(1, $result['wordOnlyCount'], 'A Word row without a confident match must be counted as Word-only.');
$assertSame(1, $result['excelOnlyCount'], 'An Excel row never matched must be counted as Excel-only.');

$matchedRow = $result['rows'][0];
$assertSame(true, $matchedRow['matched'], 'An exact-title Word row must be matched to its Excel counterpart.');
$assertSame('same', $matchedRow['title']['status'], 'Identical titles must be reported as the same.');
$assertSame('different', $matchedRow['duration']['status'], '5 zile vs 6 zile must be reported as different.');
$assertSame('5 zile', $matchedRow['duration']['word'], 'The Word duration text must be preserved.');
$assertSame('6 zile', $matchedRow['duration']['excel'], 'The Excel duration text must be preserved.');
$assertSame(
    'same',
    $matchedRow['price']['status'],
    '1500 lei and 1500,00 lei must compare equal once formatting is normalized.'
);

$unmatchedRow = $result['rows'][1];
$assertSame(false, $unmatchedRow['matched'], 'A Word row with no close Excel title must be reported as unmatched.');
$assertSame('missingExcel', $unmatchedRow['title']['status'], 'An unmatched Word row must report a missing Excel title.');

$assertSame(1, count($result['excelOnly']), 'Exactly one Excel row must remain unmatched.');
$assertSame(
    'Curs care nu are pereche in Word',
    $result['excelOnly'][0]['title'],
    'The unmatched Excel row must report its own title.'
);

// Missing required Excel columns must fail with a clear message.
$missingColumnsExcel = $makeXlsx(['title'], [['Curs fara coloane']]);
$entityManager->entities[$entityType . ':record-2'] = new Record('record-2', [
    'wordTemplateFileId' => 'word-1',
    'wordScheduleFileId' => 'excel-2',
]);
$entityManager->entities['Attachment:excel-2'] = new Attachment('excel-2', 'export.xlsx', $entityType, 'record-2', 'wordScheduleFile');
$fileStorageManager->contents['excel-2'] = $missingColumnsExcel;

$missingColumnsException = $captureException(fn () => $service->compare('record-2'));
$assertSame(true, $missingColumnsException instanceof BadRequest, 'A schedule Excel file missing Durata Curs must raise a BadRequest.');
$assertContains('Durata Curs', $missingColumnsException?->getMessage() ?? '', 'The error must name the missing duration column.');

// A record without an uploaded Word document must fail before any file is read.
$entityManager->entities[$entityType . ':record-3'] = new Record('record-3', [
    'wordTemplateFileId' => null,
    'wordScheduleFileId' => 'excel-1',
]);
$missingWordException = $captureException(fn () => $service->compare('record-3'));
$assertSame(true, $missingWordException instanceof BadRequest, 'A record without a Word document must raise a BadRequest.');

if ($failures !== []) {
    fwrite(STDERR, "Word comparison service: " . count($failures) . " failure(s) across {$checks} checks.\n" . implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "Word comparison service: {$checks} checks passed; no network used.\n");
