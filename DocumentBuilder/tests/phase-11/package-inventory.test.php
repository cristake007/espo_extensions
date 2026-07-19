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
    'files/custom/Espo/Modules/DocumentBuilder/Classes/Acl/DocumentBuilderTemplateVersionAccessChecker.php',
    'files/custom/Espo/Modules/DocumentBuilder/Classes/Record/Hooks/DocumentBuilderTemplateVersion/BeforeCreate.php',
    'files/custom/Espo/Modules/DocumentBuilder/Classes/Record/Hooks/DocumentBuilderTemplateVersion/BeforeUpdate.php',
    'files/custom/Espo/Modules/DocumentBuilder/Classes/Record/Hooks/DocumentBuilderTemplateVersion/BeforeDelete.php',
    'files/custom/Espo/Modules/DocumentBuilder/Controllers/DocumentBuilderTemplateVersion.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/TemplateVersion/TemplateVersionSnapshot.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/TemplateVersion/TemplateVersionSnapshotFactory.php',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/metadata/entityDefs/DocumentBuilderTemplateVersion.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/metadata/scopes/DocumentBuilderTemplateVersion.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/metadata/aclDefs/DocumentBuilderTemplateVersion.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/metadata/entityAcl/DocumentBuilderTemplateVersion.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/metadata/recordDefs/DocumentBuilderTemplateVersion.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/metadata/clientDefs/DocumentBuilderTemplateVersion.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/layouts/DocumentBuilderTemplateVersion/list.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/layouts/DocumentBuilderTemplateVersion/detail.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/layouts/DocumentBuilderTemplateVersion/search.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/layouts/DocumentBuilderTemplateVersion/filters.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/i18n/en_US/DocumentBuilderTemplateVersion.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/i18n/ro_RO/DocumentBuilderTemplateVersion.json',
];

foreach ($requiredEntries as $entry) {
    if ($archive->locateName($entry) === false) {
        throw new RuntimeException("Phase 11 package entry is missing: $entry");
    }
}

$metadata = $archive->getFromName('files/custom/Espo/Modules/DocumentBuilder/Resources/metadata/entityDefs/DocumentBuilderTemplateVersion.json');
$archive->close();

if ($metadata === false) {
    throw new RuntimeException('Could not read packaged template-version metadata.');
}

$entityDefs = json_decode($metadata, true, flags: JSON_THROW_ON_ERROR);

if (($entityDefs['indexes']['templateVersion']['type'] ?? null) !== 'unique') {
    throw new RuntimeException('The packaged template/version uniqueness constraint is missing.');
}

echo "Phase 11 package inventory tests passed.\n";
