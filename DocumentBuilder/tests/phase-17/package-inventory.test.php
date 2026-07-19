<?php

declare(strict_types=1);

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php package-inventory.test.php /path/to/package.zip\n");
    exit(2);
}

$archive = new ZipArchive();

if ($archive->open($argv[1]) !== true) {
    throw new RuntimeException("Could not open package: {$argv[1]}");
}

$requiredEntries = [
    'files/client/custom/modules/document-builder/src/services/draft-api.js',
    'files/client/custom/modules/document-builder/src/editor/validation/layout-precheck.js',
    'files/client/custom/modules/document-builder/src/editor/save/draft-save-coordinator.js',
    'files/client/custom/modules/document-builder/src/editor/save/keyboard.js',
    'files/client/custom/modules/document-builder/src/editor/save/dirty-guard.js',
    'files/client/custom/modules/document-builder/src/views/editor/modals/revision-conflict.js',
    'files/client/custom/modules/document-builder/res/templates/editor/modals/revision-conflict.tpl',
];

foreach ($requiredEntries as $entry) {
    if ($archive->locateName($entry) === false) {
        throw new RuntimeException("Phase 17 package entry is missing: $entry");
    }
}

$archive->close();

echo "Phase 17 package inventory tests passed.\n";
