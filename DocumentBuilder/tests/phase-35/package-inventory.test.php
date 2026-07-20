<?php

declare(strict_types=1);

if ($argc !== 2) exit(2);
$zip = new ZipArchive();
if ($zip->open($argv[1]) !== true) throw new RuntimeException('Could not open package.');
foreach ([
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Rendering/PageCountUnavailable.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Rendering/PageCountPlaceholder.php',
    'files/client/custom/modules/document-builder/src/editor/commands/update-page-chrome.js',
    'files/client/custom/modules/document-builder/res/templates/editor/shell.tpl',
] as $entry) {
    if ($zip->locateName($entry) === false) throw new RuntimeException("Phase 35 package entry missing: $entry");
}
$zip->close();
echo "Phase 35 package inventory tests passed.\n";
