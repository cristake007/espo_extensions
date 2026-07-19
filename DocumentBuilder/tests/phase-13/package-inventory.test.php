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
    'files/custom/Espo/Modules/DocumentBuilder/Api/PostTemplatePublish.php',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/routes.json',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Publication/PublicationRequest.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Publication/PublicationResult.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Publication/PublicationConflict.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Publication/PublicationActor.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Publication/CurrentUserPublicationActor.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Publication/PublicationRecordAccess.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Publication/AclPublicationRecordAccess.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Publication/PublicationStore.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Publication/OrmPublicationStore.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Publication/PublicationBlockerCategory.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Publication/PublicationValidationException.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Publication/PublicationValidationContext.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Publication/DataSourcePublicationValidator.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Publication/MediaPublicationValidator.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Publication/VariablePublicationValidator.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Publication/Phase13DataSourcePublicationValidator.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Publication/NoopMediaPublicationValidator.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Publication/NoopVariablePublicationValidator.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Publication/PublicationValidationService.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Publication/PublicationService.php',
];

foreach ($requiredEntries as $entry) {
    if ($archive->locateName($entry) === false) {
        throw new RuntimeException("Phase 13 package entry is missing: $entry");
    }
}

$routes = $archive->getFromName('files/custom/Espo/Modules/DocumentBuilder/Resources/routes.json');
$archive->close();

if ($routes === false) {
    throw new RuntimeException('Could not read packaged Phase 13 routes.');
}

$routeDefs = json_decode($routes, true, flags: JSON_THROW_ON_ERROR);

if (($routeDefs[1]['method'] ?? null) !== 'post') {
    throw new RuntimeException('The packaged publication route is missing.');
}

echo "Phase 13 package inventory tests passed.\n";
