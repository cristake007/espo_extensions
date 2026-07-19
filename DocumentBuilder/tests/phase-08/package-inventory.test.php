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
    'files/custom/Espo/Modules/DocumentBuilder/Resources/jsonSchema/document-builder-layout-v1.json',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Layout/SchemaVersion.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Layout/StableId.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Layout/Measurement.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Layout/CapabilityRegistry.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Layout/LayoutDefaults.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Layout/Node/NodeRegistry.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Layout/Source/SourceDescriptor.php',
];

foreach ($requiredEntries as $entry) {
    if ($archive->locateName($entry) === false) {
        throw new RuntimeException("Phase 08 package entry is missing: $entry");
    }
}

$schemaContents = $archive->getFromName(
    'files/custom/Espo/Modules/DocumentBuilder/Resources/jsonSchema/document-builder-layout-v1.json',
);
$archive->close();

if ($schemaContents === false) {
    throw new RuntimeException('Could not read the packaged layout schema.');
}

$schema = json_decode($schemaContents, true, flags: JSON_THROW_ON_ERROR);

if (($schema['properties']['schemaVersion']['const'] ?? null) !== 1) {
    throw new RuntimeException('The packaged canonical schema version changed.');
}

echo "Phase 08 package inventory tests passed.\n";
