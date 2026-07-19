<?php

declare(strict_types=1);

if ($argc !== 2) { fwrite(STDERR, "Usage: php package-inventory.test.php /path/to/package.zip\n"); exit(2); }
$zip = new ZipArchive();
if ($zip->open($argv[1]) !== true) throw new RuntimeException('Could not open package.');
foreach ([
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/DataSource/EntityResolver/EntityResolver.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/DataSource/EntityResolver/DirectEntityResolver.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/DataSource/EntityResolver/DirectEntityQueryPlanner.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/DataSource/EntityResolver/AclEntityResolutionAccess.php',
    'files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/DataSource/EntityResolver/OrmEntityRecordReader.php',
] as $entry) {
    if ($zip->locateName($entry) === false) throw new RuntimeException("Phase 28 package entry missing: $entry");
}
$zip->close();
echo "Phase 28 package inventory tests passed.\n";
