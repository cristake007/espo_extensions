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
    'files/custom/Espo/Modules/DocumentBuilder/Resources/metadata/app/client.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/metadata/app/clientRoutes.json',
    'files/client/custom/modules/document-builder/src/controllers/document-builder-template.js',
    'files/client/custom/modules/document-builder/src/views/editor/shell.js',
    'files/client/custom/modules/document-builder/res/templates/editor/shell.tpl',
    'files/client/custom/modules/document-builder/res/css/editor.css',
];

foreach ($requiredEntries as $entry) {
    if ($archive->locateName($entry) === false) {
        throw new RuntimeException("Phase 15 package entry is missing: $entry");
    }
}

$routes = $archive->getFromName(
    'files/custom/Espo/Modules/DocumentBuilder/Resources/metadata/app/clientRoutes.json',
);
$archive->close();

if ($routes === false) {
    throw new RuntimeException('Could not read packaged Phase 15 client routes.');
}

$routeDefs = json_decode($routes, true, flags: JSON_THROW_ON_ERROR);

if (($routeDefs['DocumentBuilderTemplate/editor/:id']['params']['action'] ?? null) !== 'editor') {
    throw new RuntimeException('The packaged editor route is missing.');
}

echo "Phase 15 package inventory tests passed.\n";
