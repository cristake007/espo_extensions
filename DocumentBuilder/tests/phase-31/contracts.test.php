<?php
declare(strict_types=1);
use DocumentBuilder\Tests\Support\Assert;
require dirname(__DIR__).'/bootstrap.php';$r=dirname(__DIR__,2);$m="$r/files/custom/Espo/Modules/DocumentBuilder";
$binding=file_get_contents("$m/Binding.php");$layout=file_get_contents("$m/Tools/DocumentBuilder/Layout/LayoutValidator.php");$references=file_get_contents("$m/Tools/DocumentBuilder/DataSource/Variable/CompiledVariableReferenceValidator.php");$schema=file_get_contents("$m/Resources/jsonSchema/document-builder-layout-v1.json");$shell=file_get_contents("$r/files/client/custom/modules/document-builder/src/views/editor/shell.js");$coordinator=file_get_contents("$r/files/client/custom/modules/document-builder/src/editor/save/draft-save-coordinator.js");
Assert::contains('CompiledSourceReferenceImpactAnalyzer::class',$binding,'Complete source-switch analysis is not bound.');
Assert::contains('CompiledVariablePublicationValidator::class',$binding,'Publication still uses a no-op variable validator.');
Assert::contains('VisibilityCondition::fromArray',$layout,'Server layout validation does not parse conditions.');
Assert::contains('VisibilityCondition::fromArray',$references,'Condition identities are not compiled against source metadata and ACL.');
foreach(['greaterThan','multiValue','"maxItems": 25','"condition"'] as $needle)Assert::contains($needle,$schema,"Condition schema contract is missing $needle.");
Assert::contains('UpdateConditionCommand',$shell,'The condition editor is not wired to undoable state.');
Assert::contains("source.type !== 'entity'",$shell,'Condition editor does not prevent source-free entity references.');
Assert::contains('showSourceChangeImpact',$shell,'The authoritative source impact is not shown before confirmation.');
Assert::contains("status: 'source-change'",$coordinator,'Source-change reports are not distinguished from revision conflicts.');
echo "Phase 31 publication, source-change, schema, and editor contracts passed.\n";
