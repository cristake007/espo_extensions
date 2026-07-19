<?php

declare(strict_types=1);

const ESPO_COMMIT = '2cde9d980f84cfc3caa1adf3275a0817e1e49bfa';
const PRODUCTION_PATH = '/opt/crm.cursurituv.ro';

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php check-capabilities.php /path/to/espocrm-10.0.0-source\n");
    exit(2);
}

$sourceRoot = realpath($argv[1]);

if ($sourceRoot === false || !is_dir($sourceRoot)) {
    throw new RuntimeException('EspoCRM source path does not exist.');
}

if ($sourceRoot === PRODUCTION_PATH || str_starts_with($sourceRoot, PRODUCTION_PATH . DIRECTORY_SEPARATOR)) {
    throw new RuntimeException('Phase 03 checks must never inspect the production instance.');
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

/** @param array<int, array<string, mixed>> $packageList */
function findPackage(array $packageList, string $name): array
{
    foreach ($packageList as $package) {
        if (($package['name'] ?? null) === $name) {
            return $package;
        }
    }

    throw new RuntimeException("Locked package is missing: $name");
}

/** @param array<int, array<string, mixed>> $libraryList */
function findBuiltLibrary(array $libraryList, string $amdId): array
{
    foreach ($libraryList as $library) {
        if (($library['amdId'] ?? null) === $amdId) {
            return $library;
        }
    }

    throw new RuntimeException("Frontend library build entry is missing: $amdId");
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
        throw new RuntimeException('Could not start git to verify the pinned source.');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);

    if ($status !== 0 || $stdout === false) {
        throw new RuntimeException('Could not verify EspoCRM source commit: ' . trim((string) $stderr));
    }

    return trim($stdout);
}

assertSameValue(ESPO_COMMIT, readGitHead($sourceRoot), 'EspoCRM source must match the pinned 10.0.0 commit.');

$rootPackage = readJsonObject($sourceRoot . '/package.json');
assertSameValue('10.0.0', $rootPackage['version'] ?? null, 'Unexpected EspoCRM package version.');

$composer = readJsonObject($sourceRoot . '/composer.json');
$composerLock = readJsonObject($sourceRoot . '/composer.lock');
$lockedComposerPackages = $composerLock['packages'] ?? null;

if (!is_array($lockedComposerPackages)) {
    throw new RuntimeException('composer.lock packages must be an array.');
}

$serverPackages = [
    'dompdf/dompdf' => [
        'constraint' => '^3.1',
        'version' => 'v3.1.5',
        'licenses' => ['LGPL-2.1'],
        'namespace' => 'Dompdf\\',
        'path' => 'src/',
    ],
    'phpoffice/phpspreadsheet' => [
        'constraint' => '^5.7',
        'version' => '5.7.0',
        'licenses' => ['MIT'],
        'namespace' => 'PhpOffice\\PhpSpreadsheet\\',
        'path' => 'src/PhpSpreadsheet',
    ],
    'chillerlan/php-qrcode' => [
        'constraint' => '^5.0',
        'version' => '5.0.5',
        'licenses' => ['MIT', 'Apache-2.0'],
        'namespace' => 'chillerlan\\QRCode\\',
        'path' => 'src',
    ],
    'picqer/php-barcode-generator' => [
        'constraint' => '^3.2',
        'version' => 'v3.2.4',
        'licenses' => ['LGPL-3.0-or-later'],
        'namespace' => 'Picqer\\Barcode\\',
        'path' => 'src',
    ],
];

foreach ($serverPackages as $name => $expected) {
    assertSameValue($expected['constraint'], $composer['require'][$name] ?? null, "Unexpected Composer constraint for $name.");

    $package = findPackage($lockedComposerPackages, $name);
    assertSameValue($expected['version'], $package['version'] ?? null, "Unexpected locked version for $name.");
    assertSameValue($expected['licenses'], $package['license'] ?? null, "Unexpected license inventory for $name.");
    assertSameValue(
        $expected['path'],
        $package['autoload']['psr-4'][$expected['namespace']] ?? null,
        "Unexpected Composer namespace mapping for $name.",
    );
}

$packageLock = readJsonObject($sourceRoot . '/package-lock.json');
$lockedClientPackages = $packageLock['packages'] ?? null;

if (!is_array($lockedClientPackages)) {
    throw new RuntimeException('package-lock.json packages must be an object.');
}

$clientPackages = [
    '@shopify/draggable' => ['version' => '1.1.4', 'declared' => '^1.1.4'],
    'dompurify' => ['version' => '3.4.11', 'declared' => '^3.3.1'],
    'gridstack' => ['version' => '5.1.1', 'declared' => 'github:yurikuzn/gridstack.js#5.1.1.e3'],
    'jquery-ui-espo' => ['version' => '0.2.3', 'declared' => 'github:yurikuzn/jquery-ui-espo#0.2.3'],
    'jquery-ui' => ['version' => '1.13.2', 'declared' => null],
    'jsbarcode' => ['version' => '3.11.4', 'declared' => '^3.11.4'],
    'qrcodejs' => ['version' => '1.0.0', 'declared' => '^1.0.0'],
    'summernote' => ['version' => '0.9.1', 'declared' => '^0.9.1'],
];

foreach ($clientPackages as $name => $expected) {
    $package = $lockedClientPackages['node_modules/' . $name] ?? null;

    if (!is_array($package)) {
        throw new RuntimeException("Locked client package is missing: $name");
    }

    assertSameValue($expected['version'], $package['version'] ?? null, "Unexpected locked client version for $name.");

    if ($expected['declared'] !== null) {
        assertSameValue($expected['declared'], $rootPackage['dependencies'][$name] ?? null, "Unexpected client constraint for $name.");
    }
}

assertContainsText(
    'f833a4d4bde2eff9efc66c5265714d3b6f514097',
    (string) ($lockedClientPackages['node_modules/gridstack']['resolved'] ?? ''),
    'GridStack must stay pinned to the verified Espo fork commit.',
);
assertContainsText(
    '9529ed989d7c7da0f5cc49d7d7ab91a8e04850e1',
    (string) ($lockedClientPackages['node_modules/jquery-ui-espo']['resolved'] ?? ''),
    'jQuery UI Espo must stay pinned to the verified build commit.',
);

$jsLibs = readJsonObject($sourceRoot . '/application/Espo/Resources/metadata/app/jsLibs.json');
$expectedJsLibs = [
    'dompurify' => ['exposeAs' => 'DOMPurify'],
    'summernote' => ['path' => 'client/lib/summernote.js', 'exportsTo' => '$.fn', 'exportsAs' => 'summernote'],
    'jquery-ui' => ['exportsTo' => '$', 'exportsAs' => 'ui'],
    '@shopify/draggable' => ['devPath' => 'client/lib/original/shopify-draggable.js'],
    'gridstack' => ['exportsTo' => 'window', 'exportsAs' => 'GridStack'],
    'jsbarcode' => ['path' => 'client/lib/JsBarcode.all.js', 'exportsAs' => 'JsBarcode'],
    'qrcodejs' => ['path' => 'client/lib/qrcode.js', 'exportsAs' => 'QRCode'],
];

foreach ($expectedJsLibs as $name => $expectedFields) {
    $actual = $jsLibs[$name] ?? null;

    if (!is_array($actual)) {
        throw new RuntimeException("Espo client loader registration is missing: $name");
    }

    foreach ($expectedFields as $field => $value) {
        assertSameValue($value, $actual[$field] ?? null, "Unexpected jsLibs field $name.$field.");
    }
}

$builtLibraries = readJsonObject($sourceRoot . '/frontend/libs.json');
$expectedBuildSources = [
    'dompurify' => 'node_modules/dompurify/dist/purify.js',
    'jquery-ui' => 'node_modules/jquery-ui-espo/dist/jquery-ui.js',
    'gridstack' => 'node_modules/gridstack/dist/gridstack-jq.js',
    '@shopify/draggable' => 'client/lib/original/shopify-draggable.js',
    'summernote' => 'node_modules/summernote/dist/summernote.js',
    'jsbarcode' => 'node_modules/jsbarcode/dist/JsBarcode.all.js',
    'qrcodejs' => 'node_modules/qrcodejs/qrcode.js',
];

foreach ($expectedBuildSources as $amdId => $expectedSource) {
    $builtLibrary = findBuiltLibrary($builtLibraries, $amdId);
    assertSameValue($expectedSource, $builtLibrary['src'] ?? null, "Unexpected frontend build source for $amdId.");
}

$loaderSource = readText($sourceRoot . '/client/src/loader.js');
$loaderParamsSource = readText($sourceRoot . '/application/Espo/Core/Utils/Client/LoaderParamsProvider.php');
assertContainsText("id.indexOf('lib!') === 0", $loaderSource, 'Client loader must implement lib! resolution.');
assertContainsText('realName in this._libsConfig', $loaderSource, 'Client loader must consult its registered library configuration.');
assertContainsText("\$map->\$name = 'lib!' . \$name;", $loaderParamsSource, 'Loader alias map must expose registered library names.');

$sourceContracts = [
    'client/src/ui.ts' => "import DOMPurify from 'dompurify';",
    'client/src/helpers/list/misc/list-tree-draggable.js' => "import {Draggable} from '@shopify/draggable';",
    'client/src/views/dashboard.js' => "import GridStack from 'gridstack';",
    'client/src/views/fields/wysiwyg.ts' => "Espo.loader.requirePromise('lib!summernote')",
    'client/src/views/fields/barcode.js' => "Espo.loader.requirePromise('lib!jsbarcode')",
];

foreach ($sourceContracts as $relativePath => $expectedText) {
    assertContainsText(
        $expectedText,
        readText($sourceRoot . '/' . $relativePath),
        "Espo source no longer proves the expected load form in $relativePath.",
    );
}

$barcodeSource = readText($sourceRoot . '/client/src/views/fields/barcode.js');
assertContainsText("Espo.loader.requirePromise('lib!qrcodejs')", $barcodeSource, 'Espo source must prove the QRCode.js load form.');

$wysiwygSource = readText($sourceRoot . '/client/src/views/fields/wysiwyg.ts');
assertContainsText('this.params.toolbar || this.toolbar', $wysiwygSource, 'Espo WYSIWYG must support a custom toolbar.');
assertContainsText('this.getHelper().sanitizeHtml(value)', $wysiwygSource, 'Espo WYSIWYG must retain client sanitization.');

$extensionRoot = dirname(__DIR__, 2);

foreach (['composer.json', 'composer.lock', 'package.json', 'package-lock.json'] as $dependencyManifest) {
    if (file_exists($extensionRoot . '/' . $dependencyManifest)) {
        throw new RuntimeException("Phase 03 must not add an extension dependency manifest: $dependencyManifest");
    }
}

$capabilityContract = readText($extensionRoot . '/docs/phase-03-capabilities.md');

foreach ([
    'dompdf/dompdf` `v3.1.5',
    'phpoffice/phpspreadsheet` `5.7.0',
    'chillerlan/php-qrcode` `5.0.5',
    'picqer/php-barcode-generator` `v3.2.4',
    'dompurify` `3.4.11',
    'summernote` `0.9.1',
    '@shopify/draggable` `1.1.4',
    'jquery-ui-espo` `0.2.3',
    'gridstack` `5.1.1',
    'qrcodejs` `1.0.0',
    'jsbarcode` `3.11.4',
    'Not selected.',
] as $requiredContractText) {
    assertContainsText($requiredContractText, $capabilityContract, 'Capability contract is incomplete.');
}

echo "Phase 03 capability checks passed.\n";
