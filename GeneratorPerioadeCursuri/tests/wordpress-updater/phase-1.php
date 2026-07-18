<?php

declare(strict_types=1);

use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\WordPressProgramMerger;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\WordPressScheduleParser;

$root = __DIR__;
$sourceRoot = dirname(__DIR__, 2) . '/files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri';

if (!class_exists(PhpOffice\PhpSpreadsheet\IOFactory::class)) {
    require $root . '/phpspreadsheet-test-double.php';
}

require $sourceRoot . '/CourseTitleHeaderResolver.php';
require $sourceRoot . '/WordPressScheduleParser.php';
require $sourceRoot . '/WordPressProgramMerger.php';

$checks = 0;
$failures = [];

$assertSame = static function (mixed $expected, mixed $actual, string $message) use (&$checks, &$failures): void {
    $checks++;

    if ($expected !== $actual) {
        $failures[] = $message . '\n  expected: ' . var_export($expected, true) . '\n  actual:   ' . var_export($actual, true);
    }
};

$assertThrows = static function (string $exceptionClass, callable $callback, string $message) use (&$checks, &$failures): void {
    $checks++;

    try {
        $callback();
        $failures[] = $message . "\n  expected exception: {$exceptionClass}";
    } catch (Throwable $exception) {
        if (!$exception instanceof $exceptionClass) {
            $failures[] = $message . '\n  actual exception: ' . $exception::class . ': ' . $exception->getMessage();
        }
    }
};

$readJson = static fn (string $path): array => json_decode(
    (string) file_get_contents($path),
    true,
    512,
    JSON_THROW_ON_ERROR
);

$parser = new WordPressScheduleParser();
$preview = $readJson($root . '/fixtures/expected/preview.json');

foreach ($preview['cases'] as $case) {
    $fixturePath = $root . '/fixtures/schedules/' . $case['fixture'];
    $actual = $parser->parse((string) file_get_contents($fixturePath), $case['fixture']);
    $expected = array_map(
        static fn (array $row): array => array_intersect_key(
            $row,
            array_flip(['sourceRow', 'title', 'permalink', 'slug', 'excelDates'])
        ),
        $case['rows']
    );
    $assertSame($expected, $actual, 'Schedule fixture must match preview expectations: ' . $case['fixture']);
}

$canonicalRows = $parser->parse(
    (string) file_get_contents($root . '/fixtures/schedules/generated-title.csv'),
    'generated-title.csv'
);
$legacyRows = $parser->parse(
    (string) file_get_contents($root . '/fixtures/schedules/romanian-nume-curs.csv'),
    'romanian-nume-curs.csv'
);
$assertSame($canonicalRows, $legacyRows, 'Generated title and legacy nume curs fixtures must parse identically.');

foreach ([',', ';', '|', "\t", '@'] as $delimiter) {
    $csv = implode($delimiter, [' Title ', ' Permalink ', ' January ']) . "\n" .
        implode($delimiter, ['Course', 'https://wp.example.test/cursuri/delimiter/', '10.01.2026']) . "\n";
    $rows = $parser->parse($csv, 'delimiter.csv');
    $assertSame(['10.01.2026'], $rows[0]['excelDates'], 'CSV delimiter must be supported: ' . json_encode($delimiter));
}

$assertSame(
    'test-course',
    $parser->extractSlug(' https://wp.example.test/cursuri/test-course/?query=ignored '),
    'Slug extraction must use the final non-empty path segment.'
);
$assertSame('', $parser->extractSlug('https://wp.example.test///'), 'A root-only permalink must produce an empty slug.');
$assertThrows(InvalidArgumentException::class, fn () => $parser->parse("\xFF", 'invalid.csv'), 'CSV must reject non-UTF-8 bytes.');
$assertThrows(InvalidArgumentException::class, fn () => $parser->parse('not a zip', 'invalid.xlsx'), 'XLSX must reject a non-ZIP body.');
$assertThrows(InvalidArgumentException::class, fn () => $parser->parse("Title\nCourse\n", 'missing.csv'), 'Required headers must be enforced.');

$wideRow = 'Course,https://wp.example.test/cursuri/wide/,' . implode(',', array_fill(0, 49, 'value'));
$assertThrows(
    LengthException::class,
    fn () => $parser->parse("Title,Permalink\n{$wideRow}\n", 'wide.csv'),
    'Data rows wider than 50 columns must be rejected.'
);

$tooManyRows = "Title,Permalink,January\n";

for ($row = 1; $row <= 5001; $row++) {
    $tooManyRows .= "Course {$row},https://wp.example.test/cursuri/course-{$row}/,10.01.2026\n";
}

$assertThrows(
    LengthException::class,
    fn () => $parser->parse($tooManyRows, 'too-many.csv'),
    'More than 5000 non-empty rows must be rejected.'
);

$merger = new WordPressProgramMerger();
$mergeFixture = $readJson($root . '/fixtures/expected/merge.json');
$today = new DateTimeImmutable(
    $mergeFixture['today'],
    new DateTimeZone($mergeFixture['timezone'])
);

foreach ($mergeFixture['dateParsing'] as $dateCase) {
    $parsed = $merger->parseEffectiveEndDate($dateCase['value']);
    $assertSame(
        $dateCase['effectiveDate'],
        $parsed?->format('Y-m-d'),
        'Effective date parsing must match for: ' . $dateCase['value']
    );

    if ($dateCase['valid']) {
        $assertSame(
            'Europe/Bucharest',
            $parsed?->getTimezone()->getName(),
            'Parsed dates must use the Bucharest timezone.'
        );
    } else {
        $assertThrows(
            InvalidArgumentException::class,
            fn () => $merger->validateFileDates([$dateCase['value']]),
            'Invalid schedule dates must fail row validation: ' . $dateCase['value']
        );
    }
}

$assertSame(null, $merger->parseEffectiveEndDate('16-14.04.2026'), 'A reversed range must be invalid.');
$assertSame(null, $merger->parseEffectiveEndDate('32-32.01.2026'), 'An invalid range start date must be invalid.');

foreach ($mergeFixture['mergeCases'] as $case) {
    $actual = $merger->merge($case['existing'], $case['fileDates'], $today);
    $expected = [
        'existingValidDates' => $case['existingValidDates'],
        'finalDates' => $case['finalDates'],
        'payload' => $case['payload'],
        'changed' => $case['changed'],
    ];
    $assertSame($expected, $actual, 'Merge policy must match fixture: ' . $case['name']);
}

$assertSame(
    ['10.01.2026', '11.01.2026'],
    $merger->validateFileDates([' 10.01.2026 ', '10.01.2026', '11.01.2026']),
    'File dates must be trimmed and deduplicated in first-occurrence order.'
);
$assertSame(
    [
        'existingValidDates' => [],
        'finalDates' => [],
        'payload' => ['acf' => ['program' => false]],
        'changed' => false,
    ],
    $merger->merge(false, [], $today),
    'A false current ACF program must be treated as an unchanged empty program.'
);
$assertSame(
    ['10.01.2026'],
    $merger->filterExistingDates(
        [['data' => '10.01.2026']],
        new DateTimeImmutable('2026-01-10 18:00:00', new DateTimeZone('Europe/Bucharest'))
    ),
    'The injected local date must compare by Bucharest calendar day, not time of day.'
);

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }

    fwrite(STDERR, sprintf("%d of %d Phase 1 checks failed.\n", count($failures), $checks));
    exit(1);
}

fwrite(STDOUT, "Phase 1 WordPress updater parsing and merge policy: {$checks} checks passed.\n");
