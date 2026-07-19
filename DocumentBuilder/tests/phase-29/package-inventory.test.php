<?php
declare(strict_types=1);
if($argc!==2){fwrite(STDERR,"Usage: php package-inventory.test.php ZIP\n");exit(2);}
$zip=new ZipArchive();if($zip->open($argv[1])!==true)throw new RuntimeException('Could not open package.');
foreach(['RelatedEntityResolver.php','RelatedEntityQueryPlanner.php','RelatedVariableCollector.php','OrmRelatedRecordReader.php','EntityPathResolver.php']as$file){$entry="files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/DataSource/EntityResolver/$file";if($zip->locateName($entry)===false)throw new RuntimeException("Phase 29 package entry missing: $entry");}
$zip->close();echo"Phase 29 package inventory tests passed.\n";
