<?php

declare(strict_types=1);

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Core\ORM\EntityManager;
use Espo\Entities\Attachment;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\CourseTitleHeaderException;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\CourseTitleHeaderResolver;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\WordConversionService;
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
require_once $sourceRoot . '/CourseScheduler.php';
require_once $sourceRoot . '/WordConversionService.php';

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

$invokePrivate = static function (object $object, string $method, array $arguments): mixed {
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invokeArgs($object, $arguments);
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

$makeDocx = static function (string $title): string {
    $path = tempnam(sys_get_temp_dir(), 'word-title-compatibility-');

    if ($path === false) {
        throw new RuntimeException('Unable to create the test DOCX path.');
    }

    $cell = static fn (string $value): string =>
        '<w:tc><w:p><w:r><w:t>' . htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</w:t></w:r></w:p></w:tc>';
    $documentXml = '<?xml version="1.0" encoding="UTF-8"?>' .
        '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl><w:tr>' .
        $cell($title) . $cell('2 zile') . $cell('') . $cell('') . $cell('') . $cell('') .
        '</w:tr></w:tbl></w:body></w:document>';

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

$readWordCells = static function (string $contents): array {
    $path = tempnam(sys_get_temp_dir(), 'word-title-result-');

    if ($path === false) {
        throw new RuntimeException('Unable to create the result DOCX path.');
    }

    try {
        file_put_contents($path, $contents);
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open the result DOCX.');
        }

        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        $document = new DOMDocument();

        if (!$document->loadXML($xml)) {
            throw new RuntimeException('Unable to parse the result DOCX XML.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $values = [];

        foreach ($xpath->query('//w:tbl//w:tr[1]/w:tc') ?: [] as $cell) {
            $text = '';

            foreach ($xpath->query('.//w:t', $cell) ?: [] as $textNode) {
                $text .= $textNode->textContent;
            }

            $values[] = $text;
        }

        return $values;
    } finally {
        @unlink($path);
    }
};

$title = 'Măsurarea eficacității unui sistem';
$dates = ['05-06.01.2026', '02-03.02.2026', '09-10.03.2026'];
$headerTail = ['Ianuarie', 'Februarie', 'Martie'];
$titleXlsx = $makeXlsx(['title', ...$headerTail], [[$title, ...$dates]]);
$legacyXlsx = $makeXlsx(['Nume curs', ...$headerTail], [[$title, ...$dates]]);
$spacedXlsx = $makeXlsx(["  \xEF\xBB\xBF TiTlE  ", ...$headerTail], [[$title, ...$dates]]);
$courseNameXlsx = $makeXlsx(['Course Name', ...$headerTail], [[$title, ...$dates]]);
$wordBytes = $makeDocx($title);

$entityManager = new EntityManager();
$fileStorageManager = new FileStorageManager();
$service = new WordConversionService($entityManager, $fileStorageManager);
$entityType = 'GeneratorPerioadeCursuriWordMatcher';
$entityManager->entities['Attachment:word-template'] = new Attachment(
    'word-template',
    'template.docx',
    $entityType,
    'title-record',
    'wordTemplateFile'
);
$fileStorageManager->contents['word-template'] = $wordBytes;

$addPreviewFixture = static function (
    string $recordId,
    string $scheduleId,
    string $scheduleBytes
) use ($entityManager, $fileStorageManager, $entityType): void {
    $entityManager->entities[$entityType . ':' . $recordId] = new Record($recordId, [
        'wordTemplateFileId' => 'word-template',
        'wordScheduleFileId' => $scheduleId,
    ]);
    $entityManager->entities['Attachment:' . $scheduleId] = new Attachment(
        $scheduleId,
        $scheduleId . '.xlsx',
        $entityType,
        $recordId,
        'wordScheduleFile'
    );
    $fileStorageManager->contents[$scheduleId] = $scheduleBytes;
};

$addPreviewFixture('title-record', 'title-schedule', $titleXlsx);
$addPreviewFixture('legacy-record', 'legacy-schedule', $legacyXlsx);
$addPreviewFixture('spaced-record', 'spaced-schedule', $spacedXlsx);
$addPreviewFixture('course-name-record', 'course-name-schedule', $courseNameXlsx);

$titlePreview = $service->preview('title-record');
$legacyPreview = $service->preview('legacy-record');
$assertSame($titlePreview, $legacyPreview, 'Canonical title and legacy nume curs files must produce identical preview payloads.');
$assertSame($titlePreview, $service->preview('spaced-record'), 'Header case, surrounding whitespace, and a leading BOM must be ignored.');
$assertSame($titlePreview, $service->preview('course-name-record'), 'The existing Course Name compatibility alias must remain accepted.');
$assertSame($title, $titlePreview['scheduleOptions'][0]['title'], 'Romanian title values must survive header resolution unchanged.');
$assertSame(0, $titlePreview['rows'][0]['selectedRowIndex'], 'Canonical and legacy schedules must retain the same exact-match selection.');
$assertSame($dates, $titlePreview['scheduleOptions'][0]['dates'], 'Canonical and legacy schedules must retain the same date choices.');

$bothColumnsXlsx = $makeXlsx(
    ['title', 'nume curs', 'course name', ...$headerTail],
    [
        ["  {$title}  ", $title, 'ignored', ...$dates],
        ['', 'Știință și învățare', 'ignored', ...$dates],
        ['Învățare digitală', '', 'ignored', ...$dates],
        ['', '', 'Course Name must not override the both-empty rule', ...$dates],
    ]
);
$bothRows = $invokePrivate($service, 'readScheduleRows', [$bothColumnsXlsx]);
$assertSame(
    [$title, 'Știință și învățare', 'Învățare digitală'],
    array_column($bothRows, 'title'),
    'Equal, legacy fallback, canonical fallback, and both-empty rows must follow the title contract.'
);
$assertSame([0, 1, 2], array_column($bothRows, 'rowIndex'), 'The existing Word Matcher row-index contract must remain unchanged.');

$preferredTitleRows = $invokePrivate($service, 'readScheduleRows', [
    $makeXlsx(['title', 'course name', 'Ianuarie'], [['Canonical', 'Compatibility', $dates[0]]]),
]);
$assertSame('Canonical', $preferredTitleRows[0]['title'], 'title must remain preferred when Course Name is also present.');

$conflictingTitle = 'PRIVATE-CONFLICT-TITLE';
$conflictingLegacyTitle = 'PRIVATE-CONFLICT-LEGACY';
$conflictException = $captureException(fn () => $invokePrivate($service, 'readScheduleRows', [
    $makeXlsx(
        ['title', 'nume curs', 'Ianuarie'],
        [[$conflictingTitle, $conflictingLegacyTitle, $dates[0]]]
    ),
]));
$assertSame(true, $conflictException instanceof BadRequest, 'Different non-empty title aliases must use the Word Matcher BadRequest channel.');
$assertContains('Randul 2', $conflictException?->getMessage() ?? '', 'A title conflict must identify its source row.');
$assertContains('"title" si "nume curs"', $conflictException?->getMessage() ?? '', 'A title conflict must identify both logical columns.');
$assertSame(false, str_contains($conflictException?->getMessage() ?? '', $conflictingTitle), 'A title conflict must not expose source cell contents.');
$assertSame(false, str_contains($conflictException?->getMessage() ?? '', $conflictingLegacyTitle), 'A title conflict must not expose legacy cell contents.');

$duplicateException = $captureException(fn () => $invokePrivate($service, 'readScheduleRows', [
    $makeXlsx(['Title', '  TITLE  ', 'Ianuarie'], [[$title, $title, $dates[0]]]),
]));
$assertSame(true, $duplicateException instanceof BadRequest, 'Duplicate normalized title headers must use the Word Matcher BadRequest channel.');
$assertContains('antetul duplicat "title"', $duplicateException?->getMessage() ?? '', 'A duplicate error must name the logical title header.');

$duplicateMonthException = $captureException(fn () => $invokePrivate($service, 'readScheduleRows', [
    $makeXlsx(['title', 'Ianuarie', '  IANUARIE  '], [[$title, $dates[0], $dates[1]]]),
]));
$assertSame(true, $duplicateMonthException instanceof BadRequest, 'Every duplicate normalized header must use the Word Matcher BadRequest channel.');
$assertContains('antetul duplicat "ianuarie"', $duplicateMonthException?->getMessage() ?? '', 'A duplicate error must name a non-title logical header too.');

foreach (['nume curs', 'course name'] as $alias) {
    $aliasDuplicate = $captureException(fn () => new CourseTitleHeaderResolver([
        mb_strtoupper($alias),
        "  {$alias}  ",
    ]));
    $assertSame(true, $aliasDuplicate instanceof CourseTitleHeaderException, "Duplicate {$alias} headers must be rejected by the shared resolver.");
    $assertSame($alias, $aliasDuplicate?->getHeader(), "The duplicate error must name the normalized {$alias} header.");
}

$resolver = new CourseTitleHeaderResolver(['nume curs', 'Ianuarie']);
$assertSame($title, $resolver->resolveTitle(["  {$title}  ", $dates[0]], 2), 'The shared resolver must trim values without changing Romanian characters.');

$legacyRows = $invokePrivate($service, 'readScheduleRows', [$legacyXlsx]);
[$convertedBytes, $matchedCount, $skippedCount] = $invokePrivate(
    $service,
    'applyMatches',
    [$wordBytes, $legacyRows, [0 => 0]]
);
$assertSame(1, $matchedCount, 'Reviewed generation must apply the selected legacy schedule row.');
$assertSame(0, $skippedCount, 'Reviewed generation must not skip the selected legacy row.');
$assertSame(
    [$title, '2 zile', '', ...$dates],
    $readWordCells($convertedBytes),
    'Reviewed generation from a nume curs file must preserve the Word title and replace all selected dates.'
);

if ($failures !== []) {
    fwrite(STDERR, "Word title compatibility: " . count($failures) . " failure(s) across {$checks} checks.\n" . implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "Word title compatibility: {$checks} checks passed; no network used.\n");
