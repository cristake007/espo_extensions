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
    'files/custom/Espo/Modules/DocumentBuilder/Api/PostTemplateDuplicate.php',
    'files/custom/Espo/Modules/DocumentBuilder/Api/PostTemplateArchive.php',
    'files/custom/Espo/Modules/DocumentBuilder/Api/PostTemplateDraftFromVersion.php',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/layouts/DocumentBuilderTemplate/relationships.json',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Lifecycle/TemplateLifecycleAccess.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Lifecycle/AclTemplateLifecycleAccess.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Lifecycle/TemplateLifecycleConflict.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Lifecycle/TemplateLifecycleRequest.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Lifecycle/DuplicateTemplateRequest.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Lifecycle/DraftFromVersionRequest.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Lifecycle/TemplateDuplicateData.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Lifecycle/TemplateLifecycleResult.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Lifecycle/TemplateLifecycleStore.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Lifecycle/OrmTemplateLifecycleStore.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Lifecycle/TemplateLifecycleService.php',
    'files/client/custom/modules/document-builder/src/handlers/template-lifecycle.js',
    'files/client/custom/modules/document-builder/src/handlers/version-lifecycle.js',
];

foreach ($requiredEntries as $entry) {
    if ($archive->locateName($entry) === false) {
        throw new RuntimeException("Phase 14 package entry is missing: $entry");
    }
}

$routes = $archive->getFromName('files/custom/Espo/Modules/DocumentBuilder/Resources/routes.json');
$archive->close();

if ($routes === false) {
    throw new RuntimeException('Could not read packaged Phase 14 routes.');
}

$routeDefs = json_decode($routes, true, flags: JSON_THROW_ON_ERROR);

if (($routeDefs[4]['route'] ?? null) !== '/DocumentBuilder/template/:id/draft-from-version') {
    throw new RuntimeException('The packaged draft-from-version route is missing.');
}

echo "Phase 14 package inventory tests passed.\n";
