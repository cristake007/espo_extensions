<?php

declare(strict_types=1);

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Log;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\MecXmlBuilder;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\XmlConversionService;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\XmlScheduleParser;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$testRoot = dirname(__DIR__) . '/wordpress-updater';
$sourceRoot = dirname(__DIR__, 2) . '/files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri';

require_once $testRoot . '/espo-service-test-double.php';

if (!class_exists(Spreadsheet::class)) {
    require_once $testRoot . '/phpspreadsheet-test-double.php';
}

require_once $sourceRoot . '/CourseTitleHeaderResolver.php';
require_once $sourceRoot . '/XmlScheduleParser.php';
require_once $sourceRoot . '/MecXmlBuilder.php';
require_once $sourceRoot . '/XmlConversionService.php';

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

$title = 'Măsurarea eficacității unui sistem Ș Ț ă â î';
$permalink = 'https://example.test/cursuri/masurare/';
$dates = ['05-06.01.2026', '02-03.02.2026'];
$canonicalXlsx = $makeXlsx(
    ['title', 'Permalink', 'Ianuarie', 'Februarie'],
    [[$title, $permalink, ...$dates]]
);
$legacyXlsx = $makeXlsx(
    ['Nume curs', 'Permalink', 'Ianuarie', 'Februarie'],
    [[$title, $permalink, ...$dates]]
);
$spacedXlsx = $makeXlsx(
    ["  \xEF\xBB\xBF TiTlE  ", ' PERMALINK ', ' IANUARIE ', ' FEBRUARIE '],
    [[$title, $permalink, ...$dates]]
);
$courseNameXlsx = $makeXlsx(
    ['Course Name', 'Permalink', 'Ianuarie', 'Februarie'],
    [[$title, $permalink, ...$dates]]
);

$parser = new XmlScheduleParser();
$canonicalEvents = $parser->parse($canonicalXlsx, 'canonical.xlsx');
$legacyEvents = $parser->parse($legacyXlsx, 'legacy.xlsx');
$assertSame($canonicalEvents, $legacyEvents, 'Canonical title and legacy nume curs XLSX files must produce identical XML events.');
$assertSame($canonicalEvents, $parser->parse($spacedXlsx, 'spaced.xlsx'), 'Case, whitespace, and a leading BOM must be ignored for XML headers.');
$assertSame($canonicalEvents, $parser->parse($courseNameXlsx, 'course-name.xlsx'), 'The existing Course Name XML alias must remain accepted.');
$assertSame(
    ['title', 'dateRange', 'permalink', 'sourceRow', 'sourceColumn'],
    array_keys($canonicalEvents[0]),
    'XML parser events must expose only the canonical title key.'
);
$assertSame(false, array_key_exists('courseTitle', $canonicalEvents[0]), 'XML parser events must not retain the alternate courseTitle key.');
$assertSame($title, $canonicalEvents[0]['title'], 'Romanian title values must survive XML column resolution unchanged.');
$assertSame($dates, array_column($canonicalEvents, 'dateRange'), 'XML date values and month-column order must remain unchanged.');

$builder = new MecXmlBuilder();
$assertSame(
    $builder->build($canonicalEvents, 21000),
    $builder->build($legacyEvents, 21000),
    'Canonical and legacy inputs must generate identical MEC XML.'
);
$assertContains($title, $builder->build($canonicalEvents, 21000), 'MEC XML must retain literal Romanian title text.');

$bothColumnsXlsx = $makeXlsx(
    ['title', 'nume curs', 'course name', 'Permalink', 'Ianuarie'],
    [
        ["  {$title}  ", $title, 'ignored', $permalink, $dates[0]],
        ['', 'Știință și învățare', 'ignored', $permalink, $dates[0]],
        ['Învățare digitală', '', 'ignored', $permalink, $dates[0]],
        ['', '', 'Course Name must not override the both-empty rule', $permalink, $dates[0]],
    ]
);
$bothEvents = $parser->parse($bothColumnsXlsx, 'both-columns.xlsx');
$assertSame(
    [$title, 'Știință și învățare', 'Învățare digitală'],
    array_column($bothEvents, 'title'),
    'XML equal, legacy fallback, canonical fallback, and both-empty rows must follow the shared contract.'
);
$assertSame([2, 3, 4], array_column($bothEvents, 'sourceRow'), 'XML empty-title rows must retain their existing skip behavior.');

$privateTitle = 'PRIVATE-XML-TITLE';
$privateLegacyTitle = 'PRIVATE-XML-LEGACY';
$conflictException = $captureException(fn () => $parser->parse(
    $makeXlsx(
        ['title', 'nume curs', 'Permalink', 'Ianuarie'],
        [[$privateTitle, $privateLegacyTitle, $permalink, $dates[0]]]
    ),
    'conflict.xlsx'
));
$assertSame(true, $conflictException instanceof BadRequest, 'XML title conflicts must use the existing BadRequest channel.');
$assertContains('Source row 2', $conflictException?->getMessage() ?? '', 'XML title conflicts must identify the source row.');
$assertContains('title and nume curs', $conflictException?->getMessage() ?? '', 'XML title conflicts must identify both logical columns.');
$assertSame(false, str_contains($conflictException?->getMessage() ?? '', $privateTitle), 'XML conflict errors must not expose canonical cell contents.');
$assertSame(false, str_contains($conflictException?->getMessage() ?? '', $privateLegacyTitle), 'XML conflict errors must not expose legacy cell contents.');

$duplicateException = $captureException(fn () => $parser->parse(
    $makeXlsx(['Title', '  TITLE  ', 'Permalink', 'Ianuarie'], [[$title, $title, $permalink, $dates[0]]]),
    'duplicate.xlsx'
));
$assertSame(true, $duplicateException instanceof BadRequest, 'XML duplicate headers must use the existing BadRequest channel.');
$assertContains('Duplicate normalized header: title.', $duplicateException?->getMessage() ?? '', 'XML duplicate errors must name the logical header.');

$conversionService = new XmlConversionService(
    new EntityManager(),
    new FileStorageManager(),
    $parser,
    $builder,
    new Acl(),
    new Language(),
    new Log()
);
$assertSame(
    'xmlTitleConflict',
    $invokePrivate($conversionService, 'translateParserError', ['Source row 2 has conflicting values for title and nume curs.']),
    'XML service translation must preserve the title-conflict error channel.'
);
$assertSame(
    'xmlDuplicateHeader',
    $invokePrivate($conversionService, 'translateParserError', ['Duplicate normalized header: title.']),
    'XML service translation must preserve the duplicate-header error channel.'
);

if ($failures !== []) {
    fwrite(STDERR, "XML title compatibility: " . count($failures) . " failure(s) across {$checks} checks.\n" . implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "XML title compatibility: {$checks} checks passed; no network used.\n");
