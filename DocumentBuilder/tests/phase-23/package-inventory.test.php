<?php
declare(strict_types=1);
if ($argc !== 2) { fwrite(STDERR, "Usage: php package-inventory.test.php /path/to/package.zip\n"); exit(2); }
$zip = new ZipArchive();
if ($zip->open($argv[1]) !== true) throw new RuntimeException('Could not open package.');
foreach ([
    'files/client/custom/modules/document-builder/src/editor/renderer/browser-renderer.js',
    'files/client/custom/modules/document-builder/src/editor/validation/editor-validator.js',
    'files/client/custom/modules/document-builder/src/views/editor/shell.js',
    'files/client/custom/modules/document-builder/res/templates/editor/shell.tpl',
] as $entry) {
    if ($zip->locateName($entry) === false) throw new RuntimeException("Phase 23 package entry missing: $entry");
}
$zip->close();
echo "Phase 23 package inventory tests passed.\n";
