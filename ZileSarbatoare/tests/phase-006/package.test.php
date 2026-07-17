<?php

declare(strict_types=1);

$extensionRoot = dirname(__DIR__, 2);
$repositoryRoot = dirname($extensionRoot);
$manifest = json_decode(
    (string) file_get_contents($extensionRoot . '/manifest.json'),
    true,
    32,
    JSON_THROW_ON_ERROR,
);
$version = $manifest['version'] ?? null;
$archivePath = $argv[1] ?? $repositoryRoot . '/dist/zile-sarbatoare-' . $version . '.zip';
$checks = 0;

$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;

    if (!$condition) {
        throw new RuntimeException('Phase 6 package contract failed: ' . $message);
    }
};

$assert($version === '0.7.2', 'manifest version must be 0.7.2');
$assert(($manifest['releaseDate'] ?? null) === '2026-07-17', 'release date must match this release');
$assert(extension_loaded('zip'), 'PHP ZIP support must be available');
$assert(is_file($archivePath), 'the installable ZIP must exist');
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
        "archive entry {$name} must use a supported package root",
    );
    $assert(
        !preg_match('#(^|/)(tests?|docs?|fixtures?|vendor|node_modules)(/|$)#i', $name),
        "archive entry {$name} must not contain development-only content",
    );
    $assert(
        !preg_match('/\.(pem|key|p12|pfx|env)$/i', $name),
        "archive entry {$name} must not contain credentials",
    );
}

$archiveManifest = json_decode(
    (string) $zip->getFromName('manifest.json'),
    true,
    32,
    JSON_THROW_ON_ERROR,
);
$assert($archiveManifest === $manifest, 'archive manifest must exactly match the source manifest');

$requiredFiles = [
    'manifest.json',
    'README.md',
    'scripts/AfterInstall.php',
    'scripts/BeforeUninstall.php',
    'files/custom/Espo/Modules/ZileSarbatoare/Binding.php',
    'files/custom/Espo/Modules/ZileSarbatoare/Entities/ZileLibere.php',
    'files/custom/Espo/Modules/ZileSarbatoare/Jobs/SyncZileSarbatoare.php',
    'files/custom/Espo/Modules/ZileSarbatoare/Tools/NagerDate/NagerDateClient.php',
    'files/custom/Espo/Modules/ZileSarbatoare/Tools/NagerDate/SyncManager.php',
    'files/custom/Espo/Modules/ZileSarbatoare/Tools/ZileLibere/ZileLibereCalendar.php',
    'files/custom/Espo/Modules/ZileSarbatoare/Resources/metadata/app/scheduledJobs.json',
    'files/client/custom/modules/zile-sarbatoare/src/views/admin/integrations/nager-date.js',
];

foreach ($requiredFiles as $requiredFile) {
    $assert(in_array($requiredFile, $archiveFiles, true), "archive must contain {$requiredFile}");
}

$sourceFiles = ['manifest.json', 'README.md'];

foreach (['files', 'scripts'] as $directory) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($extensionRoot . '/' . $directory, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $sourceFiles[] = substr($file->getPathname(), strlen($extensionRoot) + 1);
        }
    }
}

sort($sourceFiles);
sort($archiveFiles);
$assert(
    $archiveFiles === $sourceFiles,
    'archive inventory must exactly match manifest, README, files, and scripts',
);

$zip->close();

echo "PHASE-006 release package: {$checks} checks passed.\n";
