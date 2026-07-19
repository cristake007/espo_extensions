<?php
declare(strict_types=1);
use DocumentBuilder\Tests\Support\Assert;
require dirname(__DIR__) . '/bootstrap.php';
$root=dirname(__DIR__,2);$client="$root/files/client/custom/modules/document-builder";$module="$root/files/custom/Espo/Modules/DocumentBuilder";
$shell=file_get_contents("$client/src/views/editor/shell.js");$template=file_get_contents("$client/res/templates/editor/shell.tpl");$css=file_get_contents("$client/res/css/editor.css");$schema=file_get_contents("$module/Resources/jsonSchema/document-builder-layout-v1.json");
foreach(['divider','spacer','page-break'] as $type){Assert::contains("data-library-type=\"$type\"",$template,"Missing $type library control.");Assert::contains("\"const\": \"$type\"",$schema,"Missing $type schema.");}
Assert::contains('new UpdateNodeCommand',$shell,'Inspector changes must use command history.');
Assert::contains('millimetresToPixels',$shell,'Canonical dimensions must be converted only for browser display.');
Assert::contains("['solid', 'dashed', 'dotted', 'double'].includes",$shell,'Browser divider styles must be explicitly whitelisted.');
Assert::contains('Number.isFinite',$shell,'Browser dimensions must be bounded before CSS mapping.');
Assert::contains('Manual Page Break',$template,'Page-break editor marker is missing.');
Assert::contains('break-before: page',$css,'Browser page-flow marker is missing.');
Assert::isFalse(str_contains($schema,'Manual Page Break'),'Editor labels must not enter canonical layout data.');
echo "Phase 21 library, inspector, renderer-marker, and canonical-data contracts passed.\n";
