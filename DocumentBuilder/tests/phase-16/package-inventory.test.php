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
    'files/client/custom/modules/document-builder/src/editor/state/json.js',
    'files/client/custom/modules/document-builder/src/editor/state/node-tree.js',
    'files/client/custom/modules/document-builder/src/editor/state/stable-id-factory.js',
    'files/client/custom/modules/document-builder/src/editor/state/editor-state.js',
    'files/client/custom/modules/document-builder/src/editor/commands/command.js',
    'files/client/custom/modules/document-builder/src/editor/commands/add-node.js',
    'files/client/custom/modules/document-builder/src/editor/commands/remove-node.js',
    'files/client/custom/modules/document-builder/src/editor/commands/move-node.js',
    'files/client/custom/modules/document-builder/src/editor/commands/update-node.js',
    'files/client/custom/modules/document-builder/src/editor/commands/duplicate-node.js',
];

foreach ($requiredEntries as $entry) {
    if ($archive->locateName($entry) === false) {
        throw new RuntimeException("Phase 16 package entry is missing: $entry");
    }
}

$archive->close();

echo "Phase 16 package inventory tests passed.\n";
