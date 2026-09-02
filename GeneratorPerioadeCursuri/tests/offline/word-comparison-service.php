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

$findWordRow = static function (array $wordRows, string $title): ?array {
    foreach ($wordRows as $row) {
        if ($row['title'] === $title) {
            return $row;
        }
    }

    return null;
};

$excelTitleAt = static function (array $excelOptions, ?int $excelIndex): ?string {
    if ($excelIndex === null) {
        return null;
    }

    foreach ($excelOptions as $option) {
        if ($option['excelIndex'] === $excelIndex) {
            return $option['title'];
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

// The endpoint now returns a review payload (a suggested default plus candidates
// per Word row, and the full website course list) instead of a fixed diff, so the
// browser can render an editable dropdown and let the user correct a wrong or
// missing guess. The client-side diff logic itself (same/different per field) is
// covered by tests/offline/word-comparator-view-state.mjs, which exercises the
// production rendering code directly.

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
$assertSame(2, count($result['wordRows']), 'Every compatible Word row must be included.');
$assertSame(2, count($result['excelOptions']), 'Every compatible Excel row must be offered as a selectable option.');

$renamedRow = $findWordRow($result['wordRows'], 'Indicatori de performanță bazat pe ISO 9001:2026');
$assertSame(
    'Indicatori de performanță bazat pe SR EN ISO 9001:2015',
    $excelTitleAt($result['excelOptions'], $renamedRow['selectedExcelIndex'] ?? null),
    'A retitled course (dropped "SR EN", changed year) must still be suggested as the default selection.'
);
$assertSame('5 zile', $renamedRow['duration'] ?? null, 'The Word duration text must be preserved for client-side diffing.');
$assertSame('1500 lei', $renamedRow['price'] ?? null, 'The Word price text must be preserved for client-side diffing.');
$candidateExcelIndexes = array_column($renamedRow['candidates'] ?? [], 'excelIndex');
$assertSame(
    true,
    in_array($renamedRow['selectedExcelIndex'], $candidateExcelIndexes, true),
    'The suggested default must also appear among the offered candidates.'
);

$wordOnlyRow = $findWordRow($result['wordRows'], 'Managementul proiectelor europene');
$assertSame(null, $wordOnlyRow['selectedExcelIndex'], 'A Word course with no plausible Excel counterpart must have no default selection.');

$excelOnlyOption = null;

foreach ($result['excelOptions'] as $option) {
    if ($option['title'] === 'Expert achizitii publice') {
        $excelOnlyOption = $option;
    }
}

$assertSame(
    true,
    $excelOnlyOption !== null,
    'A website course with no plausible Word counterpart must still be offered as a selectable option.'
);
$assertSame(
    false,
    in_array($excelOnlyOption['excelIndex'], array_column($result['wordRows'], 'selectedExcelIndex'), true),
    'A website course with no plausible Word counterpart must not be any row\'s default selection.'
);

// --- Scenario 2: two similarly worded but genuinely different courses (different ISO
// standard numbers) must not be force-paired just because the surrounding words match.
// The user can still pick either manually - this only governs the *default* guess.

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

$assertSame(
    null,
    $distinctResult['wordRows'][0]['selectedExcelIndex'],
    'Courses naming two different ISO standards must not be paired by default despite similar wording.'
);
$assertSame(
    true,
    in_array('Auditor intern ISO 14001', array_column($distinctResult['wordRows'][0]['candidates'] ?? [], 'title'), true),
    'The differently-numbered course must still be offered as a manual candidate, even though it is not the default.'
);

// --- Scenario 3: the Word title carries extra parenthetical detail the website
// title does not ("(ISO 14064-1)"). The shorter title's tokens are fully
// contained in the longer one, so this must still be suggested as the default
// pairing rather than left for the user to find manually.

$containmentWordBytes = $makeDocx([
    ['Emisii GES - Cuantificare (ISO 14064-1)', '4 zile', '1200 lei', '', '', ''],
]);
$containmentExcelBytes = $makeXlsx(
    ['title', 'Permalink', 'Durata Curs', 'Investitie', 'Ianuarie'],
    [['Emisii GES - Cuantificare', '/curs/emisii-ges', '4 zile', '1200 lei', '10.01.2026']]
);

$setUpRecord('record-3', $containmentWordBytes, $containmentExcelBytes);
$containmentResult = $service->compare('record-3');

$assertSame(
    'Emisii GES - Cuantificare',
    $excelTitleAt($containmentResult['excelOptions'], $containmentResult['wordRows'][0]['selectedExcelIndex'] ?? null),
    'A Word title with an extra "(ISO 14064-1)" suffix must still be suggested as the default match for its shorter website title.'
);

// --- Scenario 4: the only difference between two titles is punctuation (an en
// dash vs. a hyphen, an extra separator dash). Pairing must not be defeated by
// that noise, since the matcher normalizes punctuation away before scoring.

$punctuationWordBytes = $makeDocx([
    ['Responsabil conformare PPWR – Ambalaje și deșeuri de ambalaje', '2 zile', '200 euro', '', '', ''],
    ['Regulamentul REACH - Anexa XVII, intrarea 74-Diizocianați - Nivel Inițiere', '2 zile', '280 euro', '', '', ''],
]);
$punctuationExcelBytes = $makeXlsx(
    ['title', 'Permalink', 'Durata Curs', 'Investitie', 'Ianuarie'],
    [
        ['Responsabil conformare PPWR - Ambalaje și deșeuri de ambalaje', '/curs/ppwr', '2 zile', '200 euro', '10.01.2026'],
        ['Regulamentul REACH – Anexa XVII, intrarea 74-Diizocianați Nivel Inițiere', '/curs/reach', '2 zile', '280 euro', '10.01.2026'],
    ]
);

$setUpRecord('record-4', $punctuationWordBytes, $punctuationExcelBytes);
$punctuationResult = $service->compare('record-4');

$ppwrRow = $findWordRow($punctuationResult['wordRows'], 'Responsabil conformare PPWR – Ambalaje și deșeuri de ambalaje');
$assertSame(
    'Responsabil conformare PPWR - Ambalaje și deșeuri de ambalaje',
    $excelTitleAt($punctuationResult['excelOptions'], $ppwrRow['selectedExcelIndex'] ?? null),
    'An en dash vs. a hyphen must not prevent the default pairing.'
);

$reachRow = $findWordRow($punctuationResult['wordRows'], 'Regulamentul REACH - Anexa XVII, intrarea 74-Diizocianați - Nivel Inițiere');
$assertSame(
    'Regulamentul REACH – Anexa XVII, intrarea 74-Diizocianați Nivel Inițiere',
    $excelTitleAt($punctuationResult['excelOptions'], $reachRow['selectedExcelIndex'] ?? null),
    'An extra separator dash must not prevent the default pairing.'
);

// --- Missing required Excel columns must fail with a clear message.

$missingColumnsExcel = $makeXlsx(['title'], [['Curs fara coloane']]);
$setUpRecord('record-5', $wordBytes, $missingColumnsExcel);
$missingColumnsException = $captureException(fn () => $service->compare('record-5'));
$assertSame(true, $missingColumnsException instanceof BadRequest, 'A schedule Excel file missing Durata Curs must raise a BadRequest.');
$assertContains('Durata Curs', $missingColumnsException?->getMessage() ?? '', 'The error must name the missing duration column.');

// --- A record without an uploaded Word document must fail before any file is read.

$entityManager->entities[$entityType . ':record-6'] = new Record('record-6', [
    'wordTemplateFileId' => null,
    'wordScheduleFileId' => 'record-1-excel',
]);
$missingWordException = $captureException(fn () => $service->compare('record-6'));
$assertSame(true, $missingWordException instanceof BadRequest, 'A record without a Word document must raise a BadRequest.');

if ($failures !== []) {
    fwrite(STDERR, "Word comparison service: " . count($failures) . " failure(s) across {$checks} checks.\n" . implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "Word comparison service: {$checks} checks passed; no network used.\n");
