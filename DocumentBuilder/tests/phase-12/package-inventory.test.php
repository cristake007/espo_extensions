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
    'files/custom/Espo/Modules/DocumentBuilder/Binding.php',
    'files/custom/Espo/Modules/DocumentBuilder/Api/PutTemplateDraft.php',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/routes.json',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Draft/DraftSaveRequest.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Draft/DraftSaveResult.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Draft/DraftSaveService.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Draft/RevisionConflict.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Draft/TemplateNotDraft.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Draft/DraftTemplateStore.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Draft/OrmDraftTemplateStore.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Draft/DraftRecordAccess.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Draft/AclDraftRecordAccess.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Draft/LayoutProcessorProvider.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Draft/ConfiguredLayoutProcessorProvider.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Draft/UnresolvedSourceReference.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Draft/SourceChangeImpactReport.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Draft/SourceChangeConfirmationRequired.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Draft/SourceReferenceImpactAnalyzer.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Draft/NoopSourceReferenceImpactAnalyzer.php',
];

foreach ($requiredEntries as $entry) {
    if ($archive->locateName($entry) === false) {
        throw new RuntimeException("Phase 12 package entry is missing: $entry");
    }
}

$routes = $archive->getFromName('files/custom/Espo/Modules/DocumentBuilder/Resources/routes.json');
$archive->close();

if ($routes === false) {
    throw new RuntimeException('Could not read packaged Phase 12 routes.');
}

$routeDefs = json_decode($routes, true, flags: JSON_THROW_ON_ERROR);

if (($routeDefs[0]['method'] ?? null) !== 'put') {
    throw new RuntimeException('The packaged draft save route is missing.');
}

echo "Phase 12 package inventory tests passed.\n";
