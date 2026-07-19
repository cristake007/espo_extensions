<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use DocumentBuilder\Tests\Support\FixtureLoader;

require dirname(__DIR__) . '/bootstrap.php';

$extensionRoot = dirname(__DIR__, 2);
$moduleRoot = $extensionRoot . '/files/custom/Espo/Modules/DocumentBuilder';
$clientRoot = $extensionRoot . '/files/client/custom/modules/document-builder';
$loader = new FixtureLoader($moduleRoot . '/Resources');
$schema = $loader->json('jsonSchema/document-builder-layout-v1.json');
$settings = $loader->json('metadata/app/documentBuilder.json');
$shell = file_get_contents("$clientRoot/src/views/editor/shell.js");
$template = file_get_contents("$clientRoot/res/templates/editor/shell.tpl");
$geometry = file_get_contents("$clientRoot/src/editor/geometry/page-geometry.js");
$validator = file_get_contents("$moduleRoot/Tools/DocumentBuilder/Layout/LayoutValidator.php");

foreach (compact('shell', 'template', 'geometry', 'validator') as $label => $source) {
    Assert::isTrue(is_string($source), "Could not read Phase 18 $label source.");
}

Assert::isTrue(
    in_array('timezone', $schema['$defs']['documentDefaults']['required'] ?? [], true),
    'Canonical schema must require timezone settings.',
);
Assert::isTrue(
    in_array('filenamePattern', $schema['$defs']['document']['required'] ?? [], true),
    'Canonical schema must require an output filename pattern.',
);
Assert::same([], $settings['defaults']['customPageSizeList'] ?? null, 'Custom sizes must default closed.');
Assert::same(20, $settings['hardLimits']['customPageSizeList'] ?? null, 'Custom sizes need an admin limit.');
Assert::contains('MM_PER_INCH', $geometry, 'Geometry must explicitly convert canonical millimetres.');
Assert::contains('MIN_ZOOM = 25', $geometry, 'Minimum zoom changed.');
Assert::contains('MAX_ZOOM = 200', $geometry, 'Maximum zoom changed.');
Assert::contains('fitWidth(', $geometry, 'Fit-width geometry is missing.');
Assert::contains('fitPage(', $geometry, 'Fit-page geometry is missing.');
Assert::contains('new UpdateDocumentCommand(document)', $shell, 'Page changes must use command history.');
Assert::contains('this.zoom = normalized', $shell, 'Zoom must remain view state.');
Assert::contains('data-page-setting="filenamePattern"', $template, 'Filename settings are missing.');
Assert::contains('document-builder-editor__page', $template, 'The canonical page frame is missing.');
Assert::contains('page.printableWidth', $validator, 'Server printable-area validation is missing.');
Assert::contains('customPageSizeList()', $validator, 'Server custom-size authorization is missing.');

echo "Phase 18 schema, page settings, canvas geometry, zoom, and custom-size contracts passed.\n";
