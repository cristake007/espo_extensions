<?php

declare(strict_types=1);

$extensionRoot = dirname(__DIR__, 2);
$repositoryRoot = dirname($extensionRoot);
$manifestPath = $extensionRoot . '/manifest.json';
$manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
$version = $manifest['version'] ?? null;
$archivePath = $argv[1] ?? $repositoryRoot . '/dist/generator-perioade-cursuri-' . $version . '.zip';
$checks = 0;

$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;

    if (!$condition) {
        throw new RuntimeException('Phase 6 package contract failed: ' . $message);
    }
};

$assert($version === '2.6.0', 'manifest version must be 2.6.0');
$assert(($manifest['releaseDate'] ?? null) === '2026-07-18', 'manifest release date must match this release');
$assert(extension_loaded('zip'), 'PHP ZIP support must be available');
$assert(extension_loaded('curl'), 'PHP cURL support must be available in the build environment');
$assert(is_file($archivePath), 'the 2.6.0 installable ZIP must exist');
$assert(filesize($archivePath) > 0, 'the installable ZIP must not be empty');

$zip = new ZipArchive();
$assert($zip->open($archivePath) === true, 'the installable ZIP must open cleanly');
$archiveFiles = [];

for ($index = 0; $index < $zip->numFiles; $index++) {
    $name = $zip->getNameIndex($index);

    if (!is_string($name) || str_ends_with($name, '/')) {
        continue;
    }

    $archiveFiles[] = $name;
    $assert(!str_starts_with($name, '/'), "archive entry {$name} must be relative");
    $assert(!str_contains($name, '..'), "archive entry {$name} must not traverse directories");
    $assert(!str_contains($name, '\\'), "archive entry {$name} must use portable separators");
    $assert(
        preg_match('#^(manifest\.json|README\.md|files/|scripts/)#', $name) === 1,
        "archive entry {$name} must use the supported package roots"
    );
}

$archiveManifest = json_decode((string) $zip->getFromName('manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$assert($archiveManifest === $manifest, 'archive manifest must exactly match the source manifest');

$requiredFiles = [
    'scripts/AfterInstall.php',
    'scripts/BeforeUninstall.php',
    'files/custom/Espo/Modules/GeneratorPerioadeCursuri/Controllers/GeneratorPerioadeCursuriWordPressUpdater.php',
    'files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/WordPressScheduleParser.php',
    'files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/WordPressProgramMerger.php',
    'files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/WordPressUrlGuard.php',
    'files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/WordPressHttpTransport.php',
    'files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/WordPressCourseClient.php',
    'files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/WordPressUpdaterService.php',
    'files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/routes.json',
    'files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/scopes/GeneratorPerioadeCursuriWordPressUpdater.json',
    'files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/entityDefs/GeneratorPerioadeCursuriWordPressUpdater.json',
    'files/client/custom/modules/generator-perioade-cursuri/src/views/generator-perioade-cursuri-wordpress-updater/record/detail.js',
    'files/client/custom/modules/generator-perioade-cursuri/src/views/generator-perioade-cursuri-wordpress-updater/record/edit.js',
    'files/client/custom/modules/generator-perioade-cursuri/src/views/shared/record-ui.js',
    'files/client/custom/modules/generator-perioade-cursuri/src/views/fields/holidays.js',
    'files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/i18n/en_US/GeneratorPerioadeCursuri.json',
    'files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/i18n/ro_RO/GeneratorPerioadeCursuri.json',
    'files/client/custom/modules/generator-perioade-cursuri/css/generator-perioade-cursuri.css',
];

foreach ($requiredFiles as $requiredFile) {
    $assert(in_array($requiredFile, $archiveFiles, true), "archive must contain {$requiredFile}");
}

$sourceFiles = ['manifest.json', 'README.md'];

foreach (['files', 'scripts'] as $directory) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($extensionRoot . '/' . $directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $sourceFiles[] = substr($file->getPathname(), strlen($extensionRoot) + 1);
        }
    }
}

sort($sourceFiles);
sort($archiveFiles);
$assert($archiveFiles === $sourceFiles, 'archive file inventory must exactly match manifest, README, files, and scripts');

foreach ($archiveFiles as $name) {
    $assert(!preg_match('#(^|/)(tests?|docs?|\.env|vendor|node_modules)(/|$)#i', $name), "{$name} must not ship development-only content");
    $assert(!preg_match('/\.(pem|key|p12|pfx)$/i', $name), "{$name} must not ship credential files");
    $assert(!preg_match('#(^|/)(composer|package)(-lock)?\.(json|lock)$#i', $name), "{$name} must not add dependency manifests");
}

$zip->close();

echo "Phase 6 WordPress updater release package: {$checks} checks passed; archive integrity verified.\n";
