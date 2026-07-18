<?php

declare(strict_types=1);

use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\WordPressProgramMerger;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\WordPressScheduleParser;

$testRoot = dirname(__DIR__) . '/wordpress-updater';
$sourceRoot = dirname(__DIR__, 2) . '/files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri';

if (!class_exists(PhpOffice\PhpSpreadsheet\IOFactory::class)) {
    require $testRoot . '/phpspreadsheet-test-double.php';
}

require_once $sourceRoot . '/CourseTitleHeaderResolver.php';
require_once $sourceRoot . '/WordPressScheduleParser.php';
require_once $sourceRoot . '/WordPressProgramMerger.php';

$checks = 0;
$failures = [];

$assertSame = static function (mixed $expected, mixed $actual, string $message) use (&$checks, &$failures): void {
    $checks++;

    if ($expected !== $actual) {
        $failures[] = $message . '\n  expected: ' . var_export($expected, true) . '\n  actual:   ' . var_export($actual, true);
    }
};

$assertThrows = static function (string $class, callable $callback, string $message) use (&$checks, &$failures): void {
    $checks++;

    try {
        $callback();
        $failures[] = $message . "\n  expected: {$class}";
    } catch (Throwable $exception) {
        if (!$exception instanceof $class) {
            $failures[] = $message . '\n  actual: ' . $exception::class . ': ' . $exception->getMessage();
        }
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

$parser = new WordPressScheduleParser();
$assertThrows(InvalidArgumentException::class, fn () => $parser->parse('', 'empty.csv'), 'An empty file must be rejected.');
$assertThrows(InvalidArgumentException::class, fn () => $parser->parse("\xFF\xFE", 'bad.csv'), 'Invalid UTF-8 must be rejected.');
$assertThrows(InvalidArgumentException::class, fn () => $parser->parse("Permalink\nhttps://example.test/cursuri/a/\n", 'missing-title.csv'), 'A title column is required.');
$assertThrows(InvalidArgumentException::class, fn () => $parser->parse("Title\nCourse\n", 'missing-permalink.csv'), 'A permalink column is required.');

$rows = $parser->parse(
    "\xEF\xBB\xBF  cOuRsE NaMe  ;  PeRmAlInK  ;  JANUARY  ;  Ianuarie  ; Extra\n" .
    " Course A ; https://example.test/cursuri/a/ ; 10.01.2030 ; 11.01.2030 ; ignored\n" .
    ";;;;\n" .
    ";https://example.test/cursuri/untitled/;nan;;x\n",
    'mixed.csv'
);
$assertSame(2, count($rows), 'Completely empty rows must be skipped while partially populated rows remain.');
$assertSame('Course A', $rows[0]['title'], 'Header and cell whitespace must be trimmed.');
$assertSame(['10.01.2030'], $rows[0]['excelDates'], 'English month values must take precedence over Romanian fallback values.');
$assertSame('Curs fără titlu', $rows[1]['title'], 'A partially populated row must receive the safe untitled label.');
$assertSame([], $rows[1]['excelDates'], 'Textual nan and empty date cells must be ignored.');

$romanian = $parser->parse(
    "Nume curs|Permalink|Ianuarie|Februarie\nCurs|https://example.test/cursuri/curs/|10.01.2030|10.01.2030\n",
    'romanian.csv'
);
$assertSame(['10.01.2030'], $romanian[0]['excelDates'], 'Duplicate dates across Romanian month columns must be stable-deduplicated.');
$canonical = $parser->parse(
    "title|Permalink|Ianuarie|Februarie\nCurs|https://example.test/cursuri/curs/|10.01.2030|10.01.2030\n",
    'canonical.csv'
);
$assertSame($canonical, $romanian, 'Canonical title and legacy nume curs inputs must parse identically.');
$assertSame(
    $canonical,
    $parser->parse(
        "  \xEF\xBB\xBF TiTlE  | PERMALINK | IANUARIE | FEBRUARIE \nCurs|https://example.test/cursuri/curs/|10.01.2030|10.01.2030\n",
        'normalized-headers.csv'
    ),
    'Header case, surrounding whitespace, and a leading BOM must be ignored.'
);

$bothColumns = $parser->parse(
    "title|nume curs|course name|Permalink|Ianuarie\n" .
    " Curs egal |Curs egal|ignored|https://example.test/cursuri/equal/|10.01.2030\n" .
    "|Titlu vechi|ignored|https://example.test/cursuri/legacy/|11.01.2030\n" .
    "Titlu nou||ignored|https://example.test/cursuri/canonical/|12.01.2030\n" .
    "||Course Name must not override both-empty|https://example.test/cursuri/empty/|13.01.2030\n",
    'both-columns.csv'
);
$assertSame(
    ['Curs egal', 'Titlu vechi', 'Titlu nou', 'Curs fără titlu'],
    array_column($bothColumns, 'title'),
    'WordPress rows must apply equal, legacy fallback, canonical fallback, and consumer-specific both-empty behavior.'
);

$privateTitle = 'PRIVATE-WP-TITLE';
$privateLegacyTitle = 'PRIVATE-WP-LEGACY';
$conflictException = $captureException(fn () => $parser->parse(
    "title|nume curs|Permalink|Ianuarie\n{$privateTitle}|{$privateLegacyTitle}|https://example.test/cursuri/conflict/|10.01.2030\n",
    'conflict.csv'
));
$assertSame(true, $conflictException instanceof InvalidArgumentException, 'WordPress title conflicts must use the parser validation channel.');
$assertSame(
    'Source row 2 has conflicting values for title and nume curs.',
    $conflictException?->getMessage(),
    'WordPress title conflicts must identify the row and logical columns without cell values.'
);

$duplicateException = $captureException(fn () => $parser->parse(
    "Title| TITLE |Permalink|Ianuarie\nCurs|Curs|https://example.test/cursuri/duplicate/|10.01.2030\n",
    'duplicate.csv'
));
$assertSame(true, $duplicateException instanceof InvalidArgumentException, 'WordPress duplicate headers must use the parser validation channel.');
$assertSame(
    'Duplicate normalized header: title.',
    $duplicateException?->getMessage(),
    'WordPress duplicate errors must name the logical header.'
);

foreach ([',', ';', '|', "\t", '@'] as $delimiter) {
    $csv = implode($delimiter, ['Title', 'Permalink', 'January']) . "\n" .
        implode($delimiter, ['Course', 'https://example.test/cursuri/course/', '1.2.2030']) . "\n";
    $assertSame(['1.2.2030'], $parser->parse($csv, 'delimiter.csv')[0]['excelDates'], 'Supported delimiter failed: ' . json_encode($delimiter));
}

$headers50 = array_merge(['Title', 'Permalink', 'January'], array_map(static fn (int $i): string => "Extra{$i}", range(1, 47)));
$values50 = array_merge(['Course', 'https://example.test/cursuri/max-columns/', '10.01.2030'], array_fill(0, 47, ''));
$assertSame(1, count($parser->parse(implode(',', $headers50) . "\n" . implode(',', $values50) . "\n", '50.csv')), 'Exactly 50 columns must be accepted.');
$headers51 = array_merge($headers50, ['TooMany']);
$assertThrows(LengthException::class, fn () => $parser->parse(implode(',', $headers51) . "\n", '51.csv'), 'More than 50 columns must be rejected.');

$csv5000 = "Title,Permalink,January\n";

for ($row = 1; $row <= 5000; $row++) {
    $csv5000 .= "Course {$row},https://example.test/cursuri/course-{$row}/,10.01.2030\n";
}

$parsed5000 = $parser->parse($csv5000, '5000.csv');
$assertSame(5000, count($parsed5000), 'Exactly 5,000 non-empty rows must be accepted.');
$assertSame(5001, $parsed5000[4999]['sourceRow'], 'Source row numbering must remain exact at the row limit.');
$assertThrows(
    LengthException::class,
    fn () => $parser->parse($csv5000 . "Course 5001,https://example.test/cursuri/course-5001/,10.01.2030\n", '5001.csv'),
    'More than 5,000 non-empty rows must be rejected.'
);
unset($parsed5000, $csv5000);

$maxBytes = 20 * 1024 * 1024;
$prefix = "Title,Permalink,January,Extra\nCourse,https://example.test/cursuri/size/,10.01.2030,\"";
$atLimit = $prefix . str_repeat(' ', $maxBytes - strlen($prefix) - 1) . '"';
$assertSame($maxBytes, strlen($atLimit), 'The maximum-size fixture must be exact.');
$assertSame(1, count($parser->parse($atLimit, 'at-limit.csv')), 'A file exactly at 20 MiB must be accepted.');
$assertThrows(LengthException::class, fn () => $parser->parse($atLimit . 'x', 'over-limit.csv'), 'A file over 20 MiB must be rejected.');
unset($atLimit);

$assertThrows(InvalidArgumentException::class, fn () => $parser->parse('PK malformed zip', 'malformed.xlsx'), 'Malformed XLSX ZIP content must be rejected.');
$typedXlsx = (string) file_get_contents($testRoot . '/fixtures/schedules/typed-date.xlsx');
$typedRows = $parser->parse($typedXlsx, 'typed-date.xlsx');
$assertSame(['10.01.2026'], $typedRows[0]['excelDates'], 'A typed numeric XLSX date cell must use its calculated display date.');

$merger = new WordPressProgramMerger();
$timezone = new DateTimeZone('Europe/Bucharest');
$today = new DateTimeImmutable('2030-01-10 00:00:00', $timezone);

$validDates = ['10.01.2030', '1.2.2030', '10-12.01.2030'];

foreach ($validDates as $value) {
    $assertSame($value, $merger->validateFileDates([$value])[0], "Valid date must preserve exact text: {$value}");
}

foreach (['31.02.2030', '12-10.01.2030', '30.01-02.02.2030', '10–12.01.2030', '10—12.01.2030', '2030-01-10', 'nan', '12345'] as $value) {
    $assertThrows(InvalidArgumentException::class, fn () => $merger->validateFileDates([$value]), "Unsupported or invalid date must be rejected: {$value}");
}

$filtered = $merger->filterExistingDates([
    ['data' => '09.01.2030'],
    ['data' => '10.01.2030'],
    ['data' => '11.01.2030'],
    ['data' => '11.01.2030'],
    ['data' => 'invalid'],
    ['wrong' => '12.01.2030'],
    null,
], $today);
$assertSame(['10.01.2030', '11.01.2030'], $filtered, 'Expired and malformed rows must be removed; today/future values and stable order must remain.');
$assertSame([], $merger->filterExistingDates(false, $today), 'A false ACF program must be treated as empty.');
$assertSame([], $merger->filterExistingDates(null, $today), 'A null ACF program must be treated as empty.');

$merge = $merger->merge([['data' => '10.01.2030']], ['10.01.2030', '12.01.2030', '12.01.2030'], $today);
$assertSame(['10.01.2030', '12.01.2030'], $merge['finalDates'], 'Existing/file duplicates must preserve stable exact-text ordering.');
$assertSame(true, $merge['changed'], 'A newly merged date must mark the result changed.');
$assertSame(false, $merger->merge([['data' => '10.01.2030']], ['10.01.2030'], $today)['changed'], 'An unchanged ordered program must not trigger a write.');
$assertSame(false, $merger->merge(false, [], $today)['payload']['acf']['program'], 'An empty final program must use the ACF false payload.');

if ($failures !== []) {
    fwrite(STDERR, "Offline parser/merge boundaries: " . count($failures) . " failure(s) across {$checks} checks.\n" . implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "Offline parser/merge boundaries: {$checks} checks passed; no network used.\n");
