<?php

declare(strict_types=1);

use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\CourseInputParser;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\GenerationService;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\XlsxExportService;
use PhpOffice\PhpSpreadsheet\IOFactory;

$testRoot = dirname(__DIR__) . '/wordpress-updater';
$sourceRoot = dirname(__DIR__, 2) . '/files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri';

require_once $testRoot . '/espo-service-test-double.php';

if (!class_exists(IOFactory::class)) {
    require_once $testRoot . '/phpspreadsheet-test-double.php';
}

require_once $sourceRoot . '/CourseInputParser.php';
require_once $sourceRoot . '/GenerationService.php';
require_once $sourceRoot . '/XlsxExportService.php';

$checks = 0;
$failures = [];

$assertSame = static function (mixed $expected, mixed $actual, string $message) use (&$checks, &$failures): void {
    $checks++;

    if ($expected !== $actual) {
        $failures[] = $message . '\n  expected: ' . var_export($expected, true) . '\n  actual:   ' . var_export($actual, true);
    }
};

$invokePrivate = static function (object $object, string $method, array $arguments): mixed {
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invokeArgs($object, $arguments);
};

$title = 'Măsurarea eficacității unui sistem';
$parser = new CourseInputParser();
$courses = $parser->parse(
    "TITLE;Durata Curs;Permalink;Investitie\n{$title};2 zile;https://example.test/curs;=SUM(1,1)\n",
    'cursuri.csv'
);

$assertSame([
    'sourceRow',
    'originalOrder',
    'title',
    'durationLabel',
    'duration',
    'permalink',
    'investment',
], array_keys($courses[0]), 'The parser must expose the documented canonical course shape.');
$assertSame($title, $courses[0]['title'], 'The parser must preserve the Romanian title value.');
$assertSame(false, array_key_exists('courseTitle', $courses[0]), 'The parser must not introduce the legacy transient key.');

$generation = (new ReflectionClass(GenerationService::class))->newInstanceWithoutConstructor();
$generatedRow = $invokePrivate($generation, 'buildRow', [
    $courses[0],
    1,
    '05-06.01.2026',
    false,
]);

$assertSame($title, $generatedRow['title'], 'A generated row must retain the canonical title key.');
$assertSame(false, array_key_exists('courseTitle', $generatedRow), 'A generated row must not rename title to courseTitle.');
$assertSame('Ianuarie', $generatedRow['monthName'], 'Generation must preserve localized month names.');
$assertSame('05-06.01.2026', $generatedRow['dateRange'], 'Generation must preserve the scheduled date range.');

$rows = [
    $generatedRow,
    array_merge($generatedRow, [
        'month' => 2,
        'monthName' => 'Februarie',
        'dateRange' => '02-03.02.2026',
    ]),
    [
        'title' => 'Știință și învățare',
        'permalink' => 'https://example.test/al-doilea-curs',
        'durationLabel' => '1 zi',
        'investment' => '200',
        'month' => 1,
        'monthName' => 'Ianuarie',
        'dateRange' => '12.01.2026',
        'isIncomplete' => false,
        'sourceRow' => 3,
        'originalOrder' => 1,
    ],
];

$exporter = (new ReflectionClass(XlsxExportService::class))->newInstanceWithoutConstructor();
$contents = $invokePrivate($exporter, 'createContents', [$rows, [1, 2], ['01.01.2026']]);
$assertSame('PK', substr($contents, 0, 2), 'The exporter must produce XLSX ZIP bytes.');

$path = tempnam(sys_get_temp_dir(), 'generator-title-pipeline-');

if ($path === false) {
    throw new RuntimeException('Unable to create a temporary XLSX path.');
}

try {
    file_put_contents($path, $contents);
    $reader = IOFactory::createReader('Xlsx');
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($path);
    $sheet = $spreadsheet->getActiveSheet();

    $assertSame('Rand', $sheet->getCell('A1')->getCalculatedValue(), 'The row-number header must remain unchanged.');
    $assertSame('title', $sheet->getCell('B1')->getCalculatedValue(), 'The Program title header must be exact lowercase title.');
    $assertSame($title, $sheet->getCell('B2')->getCalculatedValue(), 'The XLSX must preserve the Romanian title value.');
    $assertSame('Știință și învățare', $sheet->getCell('B3')->getCalculatedValue(), 'Grouped course order must remain stable.');
    $assertSame("'=SUM(1,1)", $sheet->getCell('E2')->getCalculatedValue(), 'Formula-injection protection must remain active.');
    $assertSame('Ianuarie', $sheet->getCell('F1')->getCalculatedValue(), 'The first selected month header must remain unchanged.');
    $assertSame('Februarie', $sheet->getCell('G1')->getCalculatedValue(), 'The second selected month header must remain unchanged.');
    $assertSame('05-06.01.2026', $sheet->getCell('F2')->getCalculatedValue(), 'The first generated date range must remain unchanged.');
    $assertSame('02-03.02.2026', $sheet->getCell('G2')->getCalculatedValue(), 'The second generated date range must remain unchanged.');
    $spreadsheet->disconnectWorksheets();

    $zip = new ZipArchive();

    if ($zip->open($path) !== true) {
        throw new RuntimeException('Unable to reopen the generated XLSX archive.');
    }

    $programXml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $holidaysXml = (string) $zip->getFromName('xl/worksheets/sheet2.xml');
    $workbookXml = (string) $zip->getFromName('xl/workbook.xml');
    $zip->close();

    $assertSame(false, str_contains($programXml, 'Nume curs'), 'New Program worksheets must not generate the legacy Nume curs header.');
    $assertSame(true, str_contains($programXml, $title), 'The Program worksheet XML must contain literal Romanian UTF-8.');
    $assertSame(true, str_contains($workbookXml, 'name="Program"'), 'The Program sheet name must remain unchanged.');
    $assertSame(true, str_contains($workbookXml, 'name="Zile nelucratoare"'), 'The holidays sheet name must remain unchanged.');
    $assertSame(true, str_contains($holidaysXml, '01.01.2026'), 'The holidays sheet values must remain present.');
} finally {
    @unlink($path);
}

if ($failures !== []) {
    fwrite(STDERR, "Main title pipeline: " . count($failures) . " failure(s) across {$checks} checks.\n" . implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "Main title pipeline: {$checks} checks passed; no network used.\n");
