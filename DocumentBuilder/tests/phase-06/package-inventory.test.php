<?php

declare(strict_types=1);

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php package-inventory.test.php /path/to/package.zip\n");
    exit(2);
}

$archive = new ZipArchive();
$packagePath = $argv[1];

if ($archive->open($packagePath) !== true) {
    throw new RuntimeException("Could not open package: $packagePath");
}

$requiredEntries = [
    'files/custom/Espo/Modules/DocumentBuilder/Resources/i18n/en_US/DocumentBuilder.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/i18n/ro_RO/DocumentBuilder.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/metadata/app/config.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/metadata/app/documentBuilder.json',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Config/ConfigProvider.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Config/InvalidConfiguration.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Config/Settings.php',
];

foreach ($requiredEntries as $entry) {
    if ($archive->locateName($entry) === false) {
        throw new RuntimeException("Phase 06 package entry is missing: $entry");
    }
}

$definitionContents = $archive->getFromName(
    'files/custom/Espo/Modules/DocumentBuilder/Resources/metadata/app/documentBuilder.json',
);
$archive->close();

if ($definitionContents === false) {
    throw new RuntimeException('Could not read the packaged settings definition.');
}

$definition = json_decode($definitionContents, true, flags: JSON_THROW_ON_ERROR);

if (($definition['lockedValues']['allowRemoteResources'] ?? null) !== false) {
    throw new RuntimeException('The packaged settings must lock remote resources off.');
}

echo "Phase 06 package inventory tests passed.\n";
