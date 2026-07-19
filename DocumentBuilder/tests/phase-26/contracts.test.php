<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;

require dirname(__DIR__) . '/bootstrap.php';
$root = dirname(__DIR__, 2);
$module = "$root/files/custom/Espo/Modules/DocumentBuilder";
$client = "$root/files/client/custom/modules/document-builder";
$schema = json_decode(file_get_contents("$module/Resources/jsonSchema/document-builder-layout-v1.json"), true, flags: JSON_THROW_ON_ERROR);
$validator = file_get_contents("$module/Tools/DocumentBuilder/Layout/LayoutValidator.php");
$compiler = file_get_contents("$module/Tools/DocumentBuilder/DataSource/Variable/VariablePathCompiler.php");
$binding = file_get_contents("$module/Binding.php");
$template = file_get_contents("$client/res/templates/editor/shell.tpl");

Assert::same(
    '#/$defs/scalarVariableIdentity',
    $schema['$defs']['inlineVariable']['properties']['identity']['$ref'] ?? null,
    'Inline variables must store a typed scalar identity.',
);
Assert::contains('isScalarVariableIdentity', $validator, 'Server layout validation must reject malformed identities.');
Assert::contains('metadataTree->get', $compiler, 'Entity paths must compile through readable metadata.');
Assert::contains('VariableUsage::Scalar', file_get_contents("$module/Tools/DocumentBuilder/DataSource/Variable/CompiledVariableReferenceValidator.php"), 'Draft validation must enforce scalar inline use.');
Assert::contains('CompiledVariableReferenceValidator::class', $binding, 'Draft saves must compile variable references.');
Assert::contains('variableReferenceValidator->validate', file_get_contents("$module/Tools/DocumentBuilder/Draft/DraftSaveService.php"), 'The authoritative draft boundary must validate references before storage.');
Assert::contains('insertMetadataVariable', $template, 'Readable metadata fields must insert stable variable tokens.');
Assert::isFalse(str_contains($template, 'addInlineVariable'), 'The arbitrary placeholder-variable action must be removed.');

echo "Phase 26 identity schema, compiler boundary, draft storage, and insertion contracts passed.\n";
