<?php

declare(strict_types=1);

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Dompdf\Dompdf;
use Espo\Core\Application;
use Espo\Tools\Pdf\Dompdf\DompdfInitializer;
use Espo\Tools\Pdf\Params;
use Espo\Tools\Pdf\Template;

const VERIFIED_ESPO_VERSION = '10.0.0';
const PRODUCTION_PATH = '/opt/crm.cursurituv.ro';

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php render-fixtures.php /path/to/non-production-espo /new/output/directory\n");
    exit(2);
}

function fail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

/** @return array<string, mixed> */
function readJsonObject(string $path): array
{
    $contents = file_get_contents($path);

    if ($contents === false) {
        fail("Could not read JSON file: $path");
    }

    $value = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

    if (!is_array($value)) {
        fail("Expected a JSON object: $path");
    }

    return $value;
}

function toImageDataUri(GdImage $image, string $type): string
{
    ob_start();

    $written = match ($type) {
        'png' => imagepng($image),
        'jpeg' => imagejpeg($image, null, 90),
        'webp' => imagewebp($image, null, 90),
        default => false,
    };

    $bytes = ob_get_clean();

    if (!$written || $bytes === false || $bytes === '') {
        fail("Could not generate $type fixture image.");
    }

    $mime = $type === 'jpeg' ? 'image/jpeg' : 'image/' . $type;

    return $mime . ';base64,' . base64_encode($bytes);
}

function buildMediaTokens(): array
{
    if (!extension_loaded('gd') || !function_exists('imagewebp')) {
        fail('The approved runtime must provide GD with WebP support.');
    }

    $image = imagecreatetruecolor(160, 100);
    $background = imagecolorallocate($image, 227, 238, 255);
    $accent = imagecolorallocate($image, 31, 78, 121);
    imagefilledrectangle($image, 0, 0, 159, 99, $background);
    imagefilledrectangle($image, 12, 12, 147, 87, $accent);

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 100">'
        . '<rect width="160" height="100" fill="#e3eeff"/>'
        . '<circle cx="80" cy="50" r="32" fill="#1f4e79"/>'
        . '<path d="M55 51l16 16 35-38" fill="none" stroke="#fff" stroke-width="8"/>'
        . '</svg>';

    $qrOptions = new QROptions([
        'outputType' => QROutputInterface::MARKUP_SVG,
        'outputBase64' => true,
        'eccLevel' => EccLevel::H,
    ]);
    $qrDataUri = (new QRCode($qrOptions))->render('https://example.invalid/verify/phase-04');

    if (!is_string($qrDataUri) || !str_starts_with($qrDataUri, 'data:image/svg+xml')) {
        fail('Could not generate the local QR data URI.');
    }

    $tokens = [
        '{{PNG_DATA_URI}}' => 'data:' . toImageDataUri($image, 'png'),
        '{{JPEG_DATA_URI}}' => 'data:' . toImageDataUri($image, 'jpeg'),
        '{{WEBP_DATA_URI}}' => 'data:' . toImageDataUri($image, 'webp'),
        '{{SVG_DATA_URI}}' => 'data:image/svg+xml;base64,' . base64_encode($svg),
        '{{QR_DATA_URI}}' => $qrDataUri,
    ];

    imagedestroy($image);

    return $tokens;
}

$espoRoot = realpath($argv[1]);

if ($espoRoot === false || !is_dir($espoRoot)) {
    fail('The supplied EspoCRM path does not exist.');
}

if ($espoRoot === PRODUCTION_PATH || str_starts_with($espoRoot, PRODUCTION_PATH . DIRECTORY_SEPARATOR)) {
    fail('Refusing to run Phase 04 fixtures on production.');
}

$espoPackage = readJsonObject($espoRoot . '/package.json');

if (($espoPackage['version'] ?? null) !== VERIFIED_ESPO_VERSION) {
    fail('The runtime must report the verified EspoCRM 10.0.0 baseline.');
}

if (!is_file($espoRoot . '/vendor/autoload.php')) {
    fail('The supplied EspoCRM runtime has no installed Composer dependencies.');
}

$requestedOutput = $argv[2];

if (!str_starts_with($requestedOutput, DIRECTORY_SEPARATOR)) {
    fail('The output directory must be an absolute path.');
}

$outputParent = realpath(dirname($requestedOutput));
$outputName = basename($requestedOutput);

if ($outputParent === false || $outputName === '' || $outputName === '.' || $outputName === '..') {
    fail('The output parent must already exist and the output name must be explicit.');
}

$outputRoot = $outputParent . DIRECTORY_SEPARATOR . $outputName;
$extensionRoot = realpath(dirname(__DIR__, 2));

if (file_exists($outputRoot)) {
    fail('The output directory already exists; use a new directory to preserve prior evidence.');
}

if ($outputRoot === PRODUCTION_PATH || str_starts_with($outputRoot, PRODUCTION_PATH . DIRECTORY_SEPARATOR)) {
    fail('The output directory must not be inside production.');
}

if ($extensionRoot !== false && ($outputRoot === $extensionRoot || str_starts_with($outputRoot, $extensionRoot . DIRECTORY_SEPARATOR))) {
    fail('The output directory must be outside the extension source tree.');
}

if (!mkdir($outputRoot, 0750)) {
    fail('Could not create the output directory.');
}

$phaseRoot = __DIR__;
$fixtureRoot = $phaseRoot . '/fixtures';
$fixtureManifest = readJsonObject($phaseRoot . '/fixture-manifest.json');

require $espoRoot . '/bootstrap.php';

$application = new Application();

if (!$application->isInstalled()) {
    fail('The supplied EspoCRM runtime is not installed.');
}

$initializer = $application->getInjectableFactory()->create(DompdfInitializer::class);
$mediaTokens = buildMediaTokens();
$results = [
    'status' => 'generated-unreviewed',
    'generatedAt' => gmdate(DATE_ATOM),
    'espoVersion' => VERIFIED_ESPO_VERSION,
    'phpVersion' => PHP_VERSION,
    'dompdfVersion' => 'v3.1.5',
    'outputDirectory' => $outputRoot,
    'fixtures' => [],
];

foreach ($fixtureManifest['fixtures'] ?? [] as $fixture) {
    if (!is_array($fixture)) {
        fail('Fixture manifest contains an invalid entry.');
    }

    $id = $fixture['id'] ?? null;
    $file = $fixture['file'] ?? null;

    if (!is_string($id) || !is_string($file)) {
        fail('Fixture manifest entry is missing an ID or file.');
    }

    $html = file_get_contents($fixtureRoot . '/' . $file);

    if ($html === false) {
        fail("Could not read fixture: $file");
    }

    $html = strtr($html, $mediaTokens);

    if (preg_match('/\{\{[A-Z0-9_]+\}\}/', $html) === 1) {
        fail("Fixture contains an unresolved token: $file");
    }

    $runResults = [];

    for ($run = 1; $run <= 2; $run++) {
        $template = new class($fixture) implements Template {
            /** @param array<string, mixed> $fixture */
            public function __construct(private array $fixture)
            {
            }

            public function getFontFace(): ?string { return 'DejaVu Sans'; }
            public function getBottomMargin(): float { return 12.0; }
            public function getTopMargin(): float { return 12.0; }
            public function getLeftMargin(): float { return 12.0; }
            public function getRightMargin(): float { return 12.0; }
            public function hasFooter(): bool { return false; }
            public function getFooter(): string { return ''; }
            public function getFooterPosition(): float { return 0.0; }
            public function hasHeader(): bool { return false; }
            public function getHeader(): string { return ''; }
            public function getHeaderPosition(): float { return 0.0; }
            public function getBody(): string { return ''; }
            public function getPageOrientation(): string { return (string) $this->fixture['orientation']; }
            public function getPageFormat(): string { return (string) $this->fixture['pageFormat']; }
            public function getPageWidth(): float { return 210.0; }
            public function getPageHeight(): float { return 297.0; }
            public function hasTitle(): bool { return false; }
            public function getTitle(): string { return ''; }
            public function getStyle(): ?string { return null; }
        };

        /** @var Dompdf $pdf */
        $pdf = $initializer->initialize($template, Params::create());
        $pdf->setOptions($pdf->getOptions()->set([
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
        ]));
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();

        $canvas = $pdf->getCanvas();
        $pageCount = $canvas->get_page_count();

        if (($fixture['addCanvasPageText'] ?? false) === true) {
            $font = $pdf->getFontMetrics()->getFont('DejaVu Sans');

            if ($font === null) {
                fail('Could not resolve DejaVu Sans for page labels.');
            }

            $canvas->page_text(
                $canvas->get_width() - 115,
                $canvas->get_height() - 18,
                'Page {PAGE_NUM} of {PAGE_COUNT}',
                $font,
                9,
            );
        }

        $pdfBytes = $pdf->output();
        $pdfFile = sprintf('%s/%s-run-%d.pdf', $outputRoot, $id, $run);

        if (file_put_contents($pdfFile, $pdfBytes, LOCK_EX) === false) {
            fail("Could not write rendered fixture: $pdfFile");
        }

        $expected = $fixture['expectedPageCount'] ?? [];
        $minimum = (int) ($expected['minimum'] ?? 0);
        $maximum = (int) ($expected['maximum'] ?? 0);

        $runResults[] = [
            'run' => $run,
            'file' => basename($pdfFile),
            'bytes' => strlen($pdfBytes),
            'sha256' => hash('sha256', $pdfBytes),
            'pageCount' => $pageCount,
            'withinExpectedPageCount' => $pageCount >= $minimum && $pageCount <= $maximum,
        ];
    }

    $results['fixtures'][] = [
        'id' => $id,
        'risks' => $fixture['risks'] ?? [],
        'samePageCount' => $runResults[0]['pageCount'] === $runResults[1]['pageCount'],
        'sameHash' => $runResults[0]['sha256'] === $runResults[1]['sha256'],
        'runs' => $runResults,
        'reviewStatus' => 'pending-manual-measurement',
    ];
}

$resultsPath = $outputRoot . '/runtime-results.json';
$json = json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

if (file_put_contents($resultsPath, $json . "\n", LOCK_EX) === false) {
    fail('Could not write runtime results.');
}

printf("Generated unreviewed Phase 04 evidence in %s\n", $outputRoot);
printf("Review every PDF and record measurements before changing the matrix status.\n");
