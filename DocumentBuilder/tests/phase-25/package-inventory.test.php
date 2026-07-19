<?php

declare(strict_types=1);

if ($argc !== 2) { fwrite(STDERR, "Usage: php package-inventory.test.php /path/to/package.zip\n"); exit(2); }
$zip = new ZipArchive();
if ($zip->open($argv[1]) !== true) throw new RuntimeException('Could not open package.');
foreach ([
    'files/custom/Espo/Modules/DocumentBuilder/Api/GetEntityMetadataTree.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/DataSource/EntityCatalogue/EntityMetadataTreeService.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/DataSource/EntityCatalogue/EntityFieldPolicy.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/DataSource/EntityCatalogue/EntityFieldItem.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/DataSource/EntityCatalogue/EntityRelationshipItem.php',
    'files/client/custom/modules/document-builder/src/services/entity-metadata-api.js',
    'files/client/custom/modules/document-builder/src/editor/variables/metadata-browser.js',
] as $entry) {
    if ($zip->locateName($entry) === false) throw new RuntimeException("Phase 25 package entry missing: $entry");
}
$zip->close();
echo "Phase 25 package inventory tests passed.\n";
