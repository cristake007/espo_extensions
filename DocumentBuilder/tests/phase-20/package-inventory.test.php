<?php
declare(strict_types=1);
if ($argc !== 2) { fwrite(STDERR, "Usage: php package-inventory.test.php /path/to/package.zip\n"); exit(2); }
$archive = new ZipArchive();
if ($archive->open($argv[1]) !== true) throw new RuntimeException('Could not open package.');
foreach ([
    'files/client/custom/modules/document-builder/src/editor/content/rich-text.js',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Layout/RichTextSanitizer.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Layout/LayoutValidator.php',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/jsonSchema/document-builder-layout-v1.json',
] as $entry) if ($archive->locateName($entry) === false) throw new RuntimeException("Phase 20 package entry is missing: $entry");
$archive->close(); echo "Phase 20 package inventory tests passed.\n";
