<?php

declare(strict_types=1);

if ($argc !== 2) { fwrite(STDERR, "Usage: php package-inventory.test.php /path/to/package.zip\n"); exit(2); }
$zip = new ZipArchive();
if ($zip->open($argv[1]) !== true) throw new RuntimeException('Could not open package.');
foreach ([
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/DataSource/Variable/VariableIdentity.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/DataSource/Variable/VariablePathCompiler.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/DataSource/Variable/CompiledVariableReferenceValidator.php',
    'files/client/custom/modules/document-builder/src/editor/variables/variable-identity.js',
] as $entry) {
    if ($zip->locateName($entry) === false) throw new RuntimeException("Phase 26 package entry missing: $entry");
}
$zip->close();
echo "Phase 26 package inventory tests passed.\n";
