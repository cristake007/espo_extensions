<?php

declare(strict_types=1);

if ($argc !== 2) exit(2);
$zip = new ZipArchive();
if ($zip->open($argv[1]) !== true) throw new RuntimeException('Could not open package.');
foreach ([
    'files/custom/Espo/Modules/DocumentBuilder/Resources/metadata/entityDefs/DocumentBuilderDocument.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/metadata/scopes/DocumentBuilderDocument.json',
    'files/custom/Espo/Modules/DocumentBuilder/Resources/layouts/DocumentBuilderDocument/detail.json',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/GenerationHistory/DocumentHistoryPolicy.php',
    'files/custom/Espo/Modules/DocumentBuilder/Classes/Acl/DocumentBuilderDocumentAccessChecker.php',
    'files/custom/Espo/Modules/DocumentBuilder/Classes/Record/OutputFilters/DocumentBuilderDocument/Snapshot.php',
] as $entry) {
    if ($zip->locateName($entry) === false) throw new RuntimeException("Phase 36 package entry missing: $entry");
}
$zip->close();
echo "Phase 36 package inventory tests passed.\n";
