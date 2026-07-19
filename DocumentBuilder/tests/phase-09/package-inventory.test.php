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
    'CanonicalSerializer.php',
    'CapabilityUnavailable.php',
    'EmptyJsonObject.php',
    'LayoutMigrator.php',
    'LayoutNormalizer.php',
    'LayoutParser.php',
    'LayoutProcessor.php',
    'LayoutValidator.php',
    'ProcessedLayout.php',
    'ValidationError.php',
    'ValidationResult.php',
    'Error/InvalidLayout.php',
    'Error/LayoutProcessingException.php',
    'Error/LayoutTooLarge.php',
    'Error/MalformedLayout.php',
    'Error/UnsupportedSchemaVersion.php',
    'Migration/LayoutMigration.php',
];

foreach ($requiredEntries as $relativePath) {
    $entry = 'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Layout/' . $relativePath;

    if ($archive->locateName($entry) === false) {
        throw new RuntimeException("Phase 09 package entry is missing: $entry");
    }
}

$archive->close();

echo "Phase 09 package inventory tests passed.\n";
