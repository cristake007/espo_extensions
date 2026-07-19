<?php

declare(strict_types=1);

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php package-inventory.test.php /path/to/package.zip\n");
    exit(2);
}

$packagePath = $argv[1];
$archive = new ZipArchive();

if ($archive->open($packagePath) !== true) {
    throw new RuntimeException("Could not open package: $packagePath");
}

$entryList = [];
$rootList = [];

for ($index = 0; $index < $archive->numFiles; $index++) {
    $name = $archive->getNameIndex($index);

    if ($name === false) {
        throw new RuntimeException("Could not read package entry at index $index.");
    }

    $entryList[] = $name;
    $rootList[] = explode('/', rtrim($name, '/'), 2)[0];

    foreach (['Documentbuilder', '/docs/', '/tests/', '.git', '__MACOSX', '.DS_Store'] as $forbiddenText) {
        if (str_contains($name, $forbiddenText)) {
            throw new RuntimeException("Forbidden package entry: $name");
        }
    }
}

$archive->close();

$rootList = array_values(array_unique($rootList));
sort($rootList);

if ($rootList !== ['README.md', 'files', 'manifest.json', 'scripts']) {
    throw new RuntimeException('Unexpected package roots: ' . implode(', ', $rootList));
}

$requiredEntries = [
    'manifest.json',
    'README.md',
    'scripts/AfterInstall.php',
    'scripts/BeforeUninstall.php',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/module.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/i18n/en_US/Global.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/i18n/ro_RO/Global.json',
    'files/client/custom/modules/document-builder/',
];

foreach ($requiredEntries as $requiredEntry) {
    if (!in_array($requiredEntry, $entryList, true)) {
        throw new RuntimeException("Required package entry is missing: $requiredEntry");
    }
}

$scriptEntries = array_values(array_filter(
    $entryList,
    static fn (string $name): bool => str_starts_with($name, 'scripts/') && str_ends_with($name, '.php'),
));
sort($scriptEntries);

if ($scriptEntries !== ['scripts/AfterInstall.php', 'scripts/BeforeUninstall.php']) {
    throw new RuntimeException('Unexpected lifecycle script inventory.');
}

echo "Phase 02 package inventory tests passed.\n";
