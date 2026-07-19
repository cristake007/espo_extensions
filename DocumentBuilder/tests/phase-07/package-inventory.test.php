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
    'files/custom/Espo/Modules/DocumentBuilder/Resources/metadata/app/acl.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/metadata/entityDefs/Role.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/i18n/en_US/Role.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/i18n/ro_RO/Role.json',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Audit/AuditContextSanitizer.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Audit/AuditEventCategory.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Error/ErrorCategory.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Error/PublicError.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Error/PublicWarning.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Error/WarningCode.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Security/ActionAccessPolicy.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Security/ActionPermission.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Security/FieldReadRequirement.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Security/LinkReadRequirement.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Security/PermissionDenied.php',
];

foreach ($requiredEntries as $entry) {
    if ($archive->locateName($entry) === false) {
        throw new RuntimeException("Phase 07 package entry is missing: $entry");
    }
}

$aclContents = $archive->getFromName(
    'files/custom/Espo/Modules/DocumentBuilder/Resources/metadata/app/acl.json',
);
$archive->close();

if ($aclContents === false) {
    throw new RuntimeException('Could not read the packaged ACL definition.');
}

$acl = json_decode($aclContents, true, flags: JSON_THROW_ON_ERROR);

if (($acl['valuePermissionList'][0] ?? null) !== '__APPEND__') {
    throw new RuntimeException('The packaged action permissions must append to the EspoCRM permission list.');
}

echo "Phase 07 package inventory tests passed.\n";
