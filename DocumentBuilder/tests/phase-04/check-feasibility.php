<?php

declare(strict_types=1);

const ESPO_COMMIT = '2cde9d980f84cfc3caa1adf3275a0817e1e49bfa';
const DOMPDF_COMMIT = 'f11ead23a8a76d0ff9bbc6c7c8fd7e05ca328496';
const PRODUCTION_PATH = '/opt/crm.cursurituv.ro';

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php check-feasibility.php /path/to/espocrm-10.0.0-source /path/to/dompdf-3.1.5-source\n");
    exit(2);
}

/** @return array<string, mixed> */
function readJsonObject(string $path): array
{
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException("Could not read JSON file: $path");
    }

    $value = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

    if (!is_array($value)) {
        throw new RuntimeException("Expected a JSON object: $path");
    }

    return $value;
}

function readText(string $path): string
{
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException("Could not read file: $path");
    }

    return $contents;
}

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . sprintf(
            "\nExpected: %s\nActual: %s",
            var_export($expected, true),
            var_export($actual, true),
        ));
    }
}

function assertContainsText(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message . "\nMissing text: $needle");
    }
}

function assertSourcePath(string $path, string $label): string
{
    $resolved = realpath($path);

    if ($resolved === false || !is_dir($resolved)) {
        throw new RuntimeException("$label source path does not exist.");
    }

    if ($resolved === PRODUCTION_PATH || str_starts_with($resolved, PRODUCTION_PATH . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Phase 04 source checks must never inspect the production instance.');
    }

    return $resolved;
}

function readGitHead(string $sourceRoot): string
{
    $pipes = [];
    $process = proc_open(
        ['git', '-C', $sourceRoot, 'rev-parse', 'HEAD'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Could not start git to verify pinned source.');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);

    if ($status !== 0 || $stdout === false) {
        throw new RuntimeException('Could not verify source commit: ' . trim((string) $stderr));
    }

    return trim($stdout);
}

$espoRoot = assertSourcePath($argv[1], 'EspoCRM');
$dompdfRoot = assertSourcePath($argv[2], 'Dompdf');

assertSameValue(ESPO_COMMIT, readGitHead($espoRoot), 'EspoCRM source must match the pinned 10.0.0 commit.');
assertSameValue(DOMPDF_COMMIT, readGitHead($dompdfRoot), 'Dompdf source must match the locked v3.1.5 commit.');

$espoPackage = readJsonObject($espoRoot . '/package.json');
assertSameValue('10.0.0', $espoPackage['version'] ?? null, 'Unexpected EspoCRM source version.');

$composerLock = readJsonObject($espoRoot . '/composer.lock');
$dompdfPackage = null;

foreach ($composerLock['packages'] ?? [] as $package) {
    if (($package['name'] ?? null) === 'dompdf/dompdf') {
        $dompdfPackage = $package;
        break;
    }
}

if (!is_array($dompdfPackage)) {
    throw new RuntimeException('EspoCRM composer.lock does not contain Dompdf.');
}

assertSameValue('v3.1.5', $dompdfPackage['version'] ?? null, 'Unexpected locked Dompdf version.');
assertSameValue(DOMPDF_COMMIT, $dompdfPackage['source']['reference'] ?? null, 'Unexpected locked Dompdf source commit.');

$initializerSource = readText($espoRoot . '/application/Espo/Tools/Pdf/Dompdf/DompdfInitializer.php');

foreach ([
    'initialize(Template $template, Params $params): Dompdf',
    '->setDefaultFont($this->getFontFace($template))',
    '->setIsJavascriptEnabled(false)',
    '$options->setChroot($dirs);',
    '$pdf = new Dompdf($options);',
    '$pdf->setPaper($size, $orientation);',
] as $requiredInitializerText) {
    assertContainsText($requiredInitializerText, $initializerSource, 'Espo Dompdf initializer contract changed.');
}

$templateSource = readText($espoRoot . '/application/Espo/Tools/Pdf/Template.php');
assertContainsText('interface Template', $templateSource, 'Espo PDF Template must remain an interface.');
assertContainsText('public function getPageWidth(): float;', $templateSource, 'Espo PDF Template must expose custom page width.');
assertContainsText('public function getPageHeight(): float;', $templateSource, 'Espo PDF Template must expose custom page height.');

$htmlComposerSource = readText($espoRoot . '/application/Espo/Tools/Pdf/Dompdf/HtmlComposer.php');
$imageProviderSource = readText($espoRoot . '/application/Espo/Tools/Pdf/Dompdf/ImageSourceProvider.php');

foreach ([
    'position: fixed;',
    'content: counter(page);',
    'QROutputInterface::MARKUP_SVG',
    '(new QRCode($options))->render($value)',
    'data:image/svg+xml;base64,',
] as $requiredComposerText) {
    assertContainsText($requiredComposerText, $htmlComposerSource, 'Espo HTML composer feasibility contract changed.');
}

assertContainsText("return 'data:' . \$type . ';base64,'", $imageProviderSource, 'Espo image provider must expose local bytes as a data URI.');

$readmeSource = readText($dompdfRoot . '/README.md');

foreach ([
    'Table cells are not pageable',
    'Embedding "raw" SVG',
    'Does not support CSS flexbox',
    'Does not support CSS Grid',
    'A single Dompdf instance should not be used to render more than one HTML document',
] as $knownLimitation) {
    assertContainsText($knownLimitation, $readmeSource, 'A documented Dompdf limitation changed.');
}

$optionsSource = readText($dompdfRoot . '/src/Options.php');
assertContainsText('private $isPhpEnabled = false;', $optionsSource, 'Dompdf PHP execution must be disabled by default.');
assertContainsText('private $isRemoteEnabled = false;', $optionsSource, 'Dompdf remote loading must be disabled by default.');

$styleSource = readText($dompdfRoot . '/src/Css/Style.php');
assertContainsText('elseif ($unit === "mm")', $styleSource, 'Dompdf must retain millimetre unit conversion.');
assertContainsText('case "rotate":', $styleSource, 'Dompdf must retain the rotation transform parser.');

$absoluteSource = readText($dompdfRoot . '/src/Positioner/Absolute.php');
assertContainsText('$frame->set_position($cbx + $left, $cby + $top);', $absoluteSource, 'Dompdf absolute positioning contract changed.');

$pageReflowerSource = readText($dompdfRoot . '/src/FrameReflower/Page.php');
assertContainsText('position === "fixed"', $pageReflowerSource, 'Dompdf fixed-position page cloning contract changed.');
assertContainsText('$fixed_children[] = $onechild->deep_copy();', $pageReflowerSource, 'Dompdf must copy fixed children across pages.');

$canvasSource = readText($dompdfRoot . '/src/Adapter/CPDF.php');
assertContainsText('public function page_text(', $canvasSource, 'CPDF page text API is missing.');
assertContainsText('["{PAGE_NUM}", "{PAGE_COUNT}"]', $canvasSource, 'CPDF total-page substitution contract changed.');
assertContainsText('case "webp":', $canvasSource, 'CPDF WebP handling is missing.');
assertContainsText('case "svg":', $canvasSource, 'CPDF SVG handling is missing.');

$imageCacheSource = readText($dompdfRoot . '/src/Image/Cache.php');
assertContainsText('["gif", "png", "jpeg", "bmp", "svg","webp"]', $imageCacheSource, 'Dompdf image allowlist changed.');
assertContainsText('references a restricted resource', $imageCacheSource, 'Dompdf SVG reference restriction changed.');

$pageDecoratorSource = readText($dompdfRoot . '/src/FrameDecorator/Page.php');
assertContainsText('check_forced_page_break', $pageDecoratorSource, 'Dompdf forced page-break handling is missing.');
assertContainsText('page_break_inside', $pageDecoratorSource, 'Dompdf keep-together handling is missing.');

$fontMetricsSource = readText($dompdfRoot . '/lib/fonts/DejaVuSans.ufm');

foreach ([226, 238, 258, 259, 536, 537, 538, 539] as $romanianCodePoint) {
    assertContainsText("U $romanianCodePoint ;", $fontMetricsSource, "DejaVu Sans is missing Romanian code point $romanianCodePoint.");
}

$phaseRoot = __DIR__;
$extensionRoot = dirname(__DIR__, 2);
$manifest = readJsonObject($phaseRoot . '/fixture-manifest.json');
assertSameValue(1, $manifest['schemaVersion'] ?? null, 'Unexpected Phase 04 fixture schema version.');
assertSameValue(ESPO_COMMIT, $manifest['espoCommit'] ?? null, 'Fixture manifest Espo commit drifted.');
assertSameValue(DOMPDF_COMMIT, $manifest['dompdfCommit'] ?? null, 'Fixture manifest Dompdf commit drifted.');
assertSameValue('pending', $manifest['runtimeStatus'] ?? null, 'Runtime status must remain pending until measured.');

$expectedFixtureFiles = [
    'freeform-mm.html',
    'header-footer-counters.html',
    'media-data-uri.html',
    'page-breaks.html',
    'romanian-flow-pagination.html',
    'table-grid-long-row.html',
];
$actualFixtureFiles = array_map('basename', glob($phaseRoot . '/fixtures/*.html') ?: []);
sort($actualFixtureFiles);
assertSameValue($expectedFixtureFiles, $actualFixtureFiles, 'Unexpected Phase 04 HTML fixture inventory.');

$manifestFixtureFiles = [];
$coveredRisks = [];

foreach ($manifest['fixtures'] ?? [] as $fixture) {
    if (!is_array($fixture)) {
        throw new RuntimeException('Fixture manifest entries must be objects.');
    }

    $file = $fixture['file'] ?? null;

    if (!is_string($file)) {
        throw new RuntimeException('Fixture manifest entry has no file.');
    }

    $manifestFixtureFiles[] = $file;
    $coveredRisks = array_merge($coveredRisks, $fixture['risks'] ?? []);

    $html = readText($phaseRoot . '/fixtures/' . $file);
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $loaded = $dom->loadHTML($html, LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
        throw new RuntimeException("Could not parse HTML fixture: $file");
    }

    foreach (['http://', 'https://', '<script', '<iframe', '@import', 'display: flex', 'display:flex', 'display: grid', 'display:grid'] as $forbiddenText) {
        if (stripos($html, $forbiddenText) !== false) {
            throw new RuntimeException("Unsafe or unsupported fixture content in $file: $forbiddenText");
        }
    }
}

sort($manifestFixtureFiles);
assertSameValue($expectedFixtureFiles, $manifestFixtureFiles, 'Fixture manifest and file inventory differ.');

foreach ([
    'romanian-fonts',
    'flow-pagination',
    'table-grid',
    'oversized-table-row',
    'absolute-positioning',
    'millimetre-units',
    'fixed-header-footer',
    'total-page-count',
    'png',
    'jpeg',
    'webp',
    'sanitized-svg',
    'qr',
    'forced-page-break',
] as $requiredRisk) {
    if (!in_array($requiredRisk, $coveredRisks, true)) {
        throw new RuntimeException("Required renderer risk is not covered: $requiredRisk");
    }
}

$compatibilityMatrix = readText($extensionRoot . '/docs/phase-04-renderer-feasibility.md');

foreach ([
    'runtime pending',
    'within 1 mm',
    'within 1 degree',
    'Table translation selected',
    'Known limitation',
    'Canvas overlay selected',
    'Data-URI candidate selected',
    'Raw inline SVG is not supported',
] as $requiredMatrixText) {
    assertContainsText($requiredMatrixText, $compatibilityMatrix, 'Renderer feasibility matrix is incomplete.');
}

$runtimeHarness = readText($phaseRoot . '/render-fixtures.php');
assertContainsText(PRODUCTION_PATH, $runtimeHarness, 'Runtime harness must contain the production-path prohibition.');
assertContainsText("'isRemoteEnabled' => false", $runtimeHarness, 'Runtime harness must disable remote resources.');
assertContainsText("'isPhpEnabled' => false", $runtimeHarness, 'Runtime harness must disable inline PHP.');
assertContainsText("'isJavascriptEnabled' => false", $runtimeHarness, 'Runtime harness must disable JavaScript.');

$pdfFiles = glob($phaseRoot . '/*.pdf') ?: [];
$fixturePdfFiles = glob($phaseRoot . '/fixtures/*.pdf') ?: [];

if ($pdfFiles !== [] || $fixturePdfFiles !== []) {
    throw new RuntimeException('Generated PDFs must not be committed as source fixtures.');
}

foreach (['composer.json', 'composer.lock', 'package.json', 'package-lock.json'] as $dependencyManifest) {
    if (file_exists($extensionRoot . '/' . $dependencyManifest)) {
        throw new RuntimeException("Phase 04 must not add an extension dependency manifest: $dependencyManifest");
    }
}

echo "Phase 04 source feasibility checks passed; runtime measurements remain pending.\n";
