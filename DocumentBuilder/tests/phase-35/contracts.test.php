<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;

require dirname(__DIR__) . '/bootstrap.php';
$root = dirname(__DIR__, 2);
$module = "$root/files/custom/Espo/Modules/DocumentBuilder";
$schema = json_decode(file_get_contents("$module/Resources/jsonSchema/document-builder-layout-v1.json"), true, 512, JSON_THROW_ON_ERROR);
$publication = file_get_contents("$module/Tools/DocumentBuilder/Publication/CompiledVariablePublicationValidator.php");
$engine = file_get_contents("$module/Tools/DocumentBuilder/Rendering/Pdf/DompdfEngine.php");
$pdfPreview = file_get_contents("$module/Tools/DocumentBuilder/Preview/PdfPreviewService.php");
$shell = file_get_contents("$root/files/client/custom/modules/document-builder/res/templates/editor/shell.tpl");

Assert::same(['page', 'defaults', 'chrome', 'titlePattern', 'filenamePattern'], $schema['$defs']['document']['required'], 'Document chrome is not canonical.');
Assert::same(50, $schema['$defs']['pageChromeNodeSequence']['maxItems'], 'Header/footer bound changed.');
Assert::same('#/$defs/pageChromeNodeSequence', $schema['properties']['footer']['$ref'], 'Footer content contract changed.');
Assert::isFalse(str_contains($publication, 'renderer.pageCountUnsupported'), 'Supported total-page count is still blocked at publication.');
Assert::contains('page_script', $engine, 'First-page chrome suppression is not applied at PDF render time.');
Assert::contains('$this->systemValues($layout)', $pdfPreview, 'PDF Proof does not resolve system values.');
foreach (['data-chrome-setting="enabled"', 'data-chrome-setting="height"', 'data-chrome-setting="pageNumber"', 'data-chrome-setting="showOnFirstPage"'] as $control) {
    Assert::contains($control, $shell, "Page chrome editor control is missing: $control");
}

echo "Phase 35 schema, compatibility, PDF, and editor contracts passed.\n";
