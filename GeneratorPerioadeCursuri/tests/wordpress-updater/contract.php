<?php

declare(strict_types=1);

const SOURCE_COMMIT = 'ce7caf4196c107625ba8d553523bca79c9f13f8a';

$root = __DIR__;
$failures = [];
$checks = 0;

$assert = static function (bool $condition, string $message) use (&$failures, &$checks): void {
    $checks++;

    if (!$condition) {
        $failures[] = $message;
    }
};

$readJson = static function (string $path): array {
    $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($decoded)) {
        throw new RuntimeException("Expected a JSON object in {$path}");
    }

    return $decoded;
};

try {
    $preview = $readJson($root . '/fixtures/expected/preview.json');
    $merge = $readJson($root . '/fixtures/expected/merge.json');
    $http = $readJson($root . '/fixtures/http/scenarios.json');
    $upstream = $readJson($root . '/upstream-assertions.json');
} catch (Throwable $exception) {
    fwrite(STDERR, "Contract fixture JSON is invalid: {$exception->getMessage()}\n");
    exit(1);
}

$assert($preview['sourceCommit'] === SOURCE_COMMIT, 'Preview expectations must pin the source commit.');
$assert($http['sourceCommit'] === SOURCE_COMMIT, 'HTTP scenarios must pin the source commit.');
$assert($upstream['sourceCommit'] === SOURCE_COMMIT, 'Upstream assertion map must pin the source commit.');
$assert($http['liveNetworkAllowed'] === false, 'HTTP scenarios must explicitly disable live networking.');

$assertionRows = $upstream['assertions'] ?? [];
$assert(count($assertionRows) === ($upstream['activeTestCount'] ?? null), 'Every active upstream test must have one mapped assertion row.');
$assert(count(array_unique(array_column($assertionRows, 'upstream'))) === count($assertionRows), 'Mapped upstream test names must be unique.');
$assert(count(array_unique(array_column($assertionRows, 'plannedPhp'))) === count($assertionRows), 'Planned PHP assertion names must be unique.');

foreach ($assertionRows as $row) {
    $assert(!empty($row['upstream']) && !empty($row['plannedPhp']), 'Every upstream row must name its planned PHP assertion.');

    foreach ($row['fixtures'] ?? [] as $fixture) {
        $assert(is_file($root . '/fixtures/' . $fixture), "Mapped fixture does not exist: {$fixture}");
    }
}

$scheduleDirectory = $root . '/fixtures/schedules';
$previewFixtures = array_column($preview['cases'] ?? [], 'fixture');
$requiredSchedules = [
    'romanian-nume-curs.csv',
    'django-title-english.csv',
    'bilingual-precedence.csv',
    'semicolon-bom.csv',
    'edge-rows.csv',
    'typed-date.xlsx',
];

foreach ($requiredSchedules as $fixture) {
    $assert(is_file($scheduleDirectory . '/' . $fixture), "Required schedule fixture is missing: {$fixture}");
    $assert(in_array($fixture, $previewFixtures, true), "Preview expectations are missing: {$fixture}");
}

$bomCsv = (string) file_get_contents($scheduleDirectory . '/semicolon-bom.csv');
$assert(str_starts_with($bomCsv, "\xEF\xBB\xBF"), 'Semicolon CSV must begin with a UTF-8 BOM.');
$assert(str_contains(strtok($bomCsv, "\n"), ';'), 'BOM fixture must use a semicolon delimiter.');

$zip = new ZipArchive();
$xlsxOpened = $zip->open($scheduleDirectory . '/typed-date.xlsx') === true;
$assert($xlsxOpened, 'Typed-date fixture must be a valid ZIP/XLSX file.');

if ($xlsxOpened) {
    $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $styles = (string) $zip->getFromName('xl/styles.xml');
    $assert(str_contains($sheet, '<c r="C2" s="1" t="n"><v>46032</v></c>'), 'XLSX fixture must store the date as a numeric styled cell.');
    $assert(str_contains($styles, 'formatCode="dd.mm.yyyy"'), 'XLSX fixture must apply a date number format.');
    $zip->close();
}

$edgeCase = null;

foreach ($preview['cases'] as $case) {
    if ($case['fixture'] === 'edge-rows.csv') {
        $edgeCase = $case;
        break;
    }
}

$assert($edgeCase !== null && count($edgeCase['rows']) === 5, 'Empty source rows must be skipped while five non-empty edge rows remain.');
$assert(($edgeCase['rows'][0]['title'] ?? null) === 'Curs fără titlu', 'A non-empty row with no title must use the browser fallback title.');
$assert(($edgeCase['rows'][1]['error'] ?? null) === 'invalidPermalink', 'A blank permalink must have a row-level error.');
$assert(($edgeCase['rows'][2]['error'] ?? null) === 'invalidPermalink', 'A permalink without a path slug must have a row-level error.');
$assert(($edgeCase['rows'][4]['excelDates'] ?? null) === [], 'Textual nan must contribute no date.');

$dateValues = array_column($merge['dateParsing'] ?? [], null, 'value');
$assert(($dateValues['31.02.2026']['valid'] ?? null) === false, 'Invalid calendar dates must be rejected.');
$assert(($dateValues['30.01-02.02.2026']['valid'] ?? null) === false, 'Cross-month ranges must be rejected.');
$assert(($dateValues['05-07.01.2026']['effectiveDate'] ?? null) === '2026-01-07', 'A range must use its end date.');

$mergeCases = array_column($merge['mergeCases'] ?? [], null, 'name');
$assert(($mergeCases['today-expired-future-range-invalid-and-duplicates']['existingValidDates'] ?? null) === ['10.01.2026', '11-12.01.2026'], 'Merge fixture must retain today/range values in stable exact-text order.');
$assert(($mergeCases['ordered-equal-no-change']['changed'] ?? null) === false, 'Ordered-equal values must lock the no-change result.');
$assert(($mergeCases['empty-program-uses-false']['payload']['acf']['program'] ?? null) === false, 'An empty payload must retain the upstream false representation.');

$scenarioNames = array_column($http['scenarios'] ?? [], 'name');
$requiredScenarios = [
    'private-ipv4',
    'private-ipv6',
    'metadata-host',
    'mixed-public-private-dns',
    'redirect-private-ip',
    'authenticated-cross-host-redirect',
    'too-many-redirects',
    'oversized-body',
    'cloudflare-challenge',
    'rate-limited-retries',
    'invalid-json',
    'get-timeout',
    'post-timeout-delivery-unknown',
];

foreach ($requiredScenarios as $scenario) {
    $assert(in_array($scenario, $scenarioNames, true), "Required mocked HTTP scenario is missing: {$scenario}");
}

$fixtureFiles = array_merge(
    glob($root . '/fixtures/schedules/*') ?: [],
    glob($root . '/fixtures/expected/*') ?: [],
    glob($root . '/fixtures/http/*') ?: []
);

foreach ($fixtureFiles as $fixtureFile) {
    $contents = (string) file_get_contents($fixtureFile);
    $assert(!preg_match('/Authorization\s*:/i', $contents), basename($fixtureFile) . ' must not contain an Authorization header.');
    $assert(!preg_match('/wp_app_password|wpAppPassword/i', $contents), basename($fixtureFile) . ' must not contain an application-password field.');
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }

    fwrite(STDERR, sprintf("%d of %d contract checks failed.\n", count($failures), $checks));
    exit(1);
}

fwrite(STDOUT, "Phase 0 WordPress updater contract: {$checks} checks passed; no network used.\n");
