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
    'files/custom/Espo/Modules/DocumentBuilder/Classes/Acl/DocumentBuilderTemplateAccessChecker.php',
    'files/custom/Espo/Modules/DocumentBuilder/Classes/Record/Hooks/DocumentBuilderTemplate/BeforeCreate.php',
    'files/custom/Espo/Modules/DocumentBuilder/Classes/Record/Hooks/DocumentBuilderTemplate/BeforeDelete.php',
    'files/custom/Espo/Modules/DocumentBuilder/Controllers/DocumentBuilderTemplate.php',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/metadata/entityDefs/DocumentBuilderTemplate.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/metadata/scopes/DocumentBuilderTemplate.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/metadata/aclDefs/DocumentBuilderTemplate.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/metadata/entityAcl/DocumentBuilderTemplate.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/metadata/recordDefs/DocumentBuilderTemplate.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/metadata/clientDefs/DocumentBuilderTemplate.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/layouts/DocumentBuilderTemplate/list.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/layouts/DocumentBuilderTemplate/detail.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/layouts/DocumentBuilderTemplate/edit.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/layouts/DocumentBuilderTemplate/search.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/layouts/DocumentBuilderTemplate/filters.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/i18n/en_US/DocumentBuilderTemplate.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/i18n/ro_RO/DocumentBuilderTemplate.json',
];

foreach ($requiredEntries as $entry) {
    if ($archive->locateName($entry) === false) {
        throw new RuntimeException("Phase 10 package entry is missing: $entry");
    }
}

$entityContents = $archive->getFromName(
    'files/custom/Espo/Modules/DocumentBuilder/Resources/metadata/entityDefs/DocumentBuilderTemplate.json',
);
$archive->close();

if ($entityContents === false) {
    throw new RuntimeException('Could not read the packaged template entity definition.');
}

$entityDefs = json_decode($entityContents, true, flags: JSON_THROW_ON_ERROR);

if (($entityDefs['fields']['status']['default'] ?? null) !== 'Draft') {
    throw new RuntimeException('The packaged template entity no longer creates drafts by default.');
}

echo "Phase 10 package inventory tests passed.\n";
