<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use DocumentBuilder\Tests\Support\FixtureLoader;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\Settings;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CapabilityRegistry;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutValidator;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Node\NodeRegistry;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\RichTextSanitizer;

require dirname(__DIR__) . '/bootstrap.php';
$root = dirname(__DIR__, 2);
$module = "$root/files/custom/Espo/Modules/DocumentBuilder";
$layoutRoot = "$module/Tools/DocumentBuilder/Layout";
require "$module/Tools/DocumentBuilder/Config/Settings.php";
foreach (['SchemaVersion.php', 'StableId.php', 'Unit.php', 'Measurement.php', 'Capability.php',
    'CapabilityStatus.php', 'CapabilityUnavailable.php', 'CapabilityNotPublishable.php',
    'CapabilityRegistry.php', 'Node/NodeKind.php', 'Node/NodeDefinition.php',
    'Node/UnknownNodeType.php', 'Node/NodeRegistry.php', 'ValidationError.php',
    'ValidationResult.php', 'LayoutValidator.php', 'RichTextSanitizer.php'] as $file) {
    require "$layoutRoot/$file";
}

$schema = json_decode(
    file_get_contents("$module/Resources/jsonSchema/document-builder-layout-v1.json"),
    true,
    flags: JSON_THROW_ON_ERROR,
);
Assert::same(
    '#/$defs/variableElement',
    $schema['$defs']['flowElement']['oneOf'][4]['$ref'] ?? null,
    'The standalone variable is not part of the canonical flow union.',
);
Assert::same(
    '#/$defs/paragraphContent',
    $schema['$defs']['paragraph']['properties']['content']['$ref'] ?? null,
    'Paragraphs do not use the additive structured-list content contract.',
);

$settings = new Settings([
    'enabledSourceEntityTypeList' => [], 'disabledSourceEntityTypeList' => [],
    'maxRelationshipDepth' => 2, 'maxElements' => 500, 'maxNestingDepth' => 8,
    'maxSections' => 100, 'allowedFontList' => ['DejaVu Sans'], 'customPageSizeList' => [],
]);
$validator = new LayoutValidator($settings, NodeRegistry::phase19(), CapabilityRegistry::phase19());
$layout = (new FixtureLoader("$root/tests/fixtures"))->json('layout/phase-08-default.json');
$box = array_fill_keys(['top', 'right', 'bottom', 'left'], ['value' => 0, 'unit' => 'mm']);
$presentation = [
    'format' => [
        'type' => 'auto', 'decimals' => 2, 'dateStyle' => 'medium', 'timeStyle' => 'short',
        'currency' => null, 'trueLabel' => null, 'falseLabel' => null, 'separator' => ', ',
        'trim' => true, 'case' => 'none', 'prefix' => '', 'suffix' => '', 'fallback' => null,
    ],
    'missing' => 'empty',
];
$identity = ['source' => 'system', 'type' => 'system', 'path' => ['currentDate']];
$layout['capabilities'] = ['layout.flow'];
$layout['sections'] = [[
    'id' => 'section', 'type' => 'flow-section', 'margin' => $box, 'padding' => $box,
    'minHeight' => ['value' => 20, 'unit' => 'mm'], 'keepTogether' => false,
    'startNewPage' => false, 'children' => [
        ['id' => 'variable', 'type' => 'variable', 'label' => "Current\r\nDate",
            'identity' => $identity, 'presentation' => $presentation],
        ['id' => 'paragraph', 'type' => 'paragraph', 'alignment' => 'start', 'content' => [[
            'type' => 'list', 'style' => 'bulleted', 'items' => [
                [['type' => 'text', 'text' => "First\r\nitem", 'marks' => ['underline', 'bold']]],
                [['type' => 'variable', 'tokenId' => 'token_date', 'label' => 'Date',
                    'identity' => $identity, 'presentation' => $presentation]],
            ],
        ]]],
    ],
]];
Assert::isTrue($validator->validate($layout)->isValid(), 'The additive recovery layout was rejected.');

$normalized = (new RichTextSanitizer())->normalizeLayout($layout);
Assert::same("Current\nDate", $normalized['sections'][0]['children'][0]['label'],
    'Standalone variable label normalization failed.');
Assert::same("First\nitem", $normalized['sections'][0]['children'][1]['content'][0]['items'][0][0]['text'],
    'List-item newline normalization failed.');
Assert::same(['bold', 'underline'],
    $normalized['sections'][0]['children'][1]['content'][0]['items'][0][0]['marks'],
    'List-item mark canonicalization failed.');
Assert::isTrue($validator->validate($normalized)->isValid(), 'The normalized recovery layout was rejected.');

$bad = $layout;
$bad['sections'][0]['children'][0]['identity'] = [
    'source' => 'entity', 'type' => 'collection', 'entityType' => 'Contact', 'path' => ['courses'],
];
$codes = array_map(static fn ($error): string => $error->code(), $validator->validate($bad)->errors());
Assert::isTrue(in_array('variable.identity', $codes, true), 'A collection standalone variable was accepted.');

$bad = $layout;
$bad['sections'][0]['children'][1]['content'][0]['items'][0] = [[
    'type' => 'list', 'style' => 'numbered', 'items' => [[['type' => 'text', 'text' => 'Nested', 'marks' => []]]],
]];
$codes = array_map(static fn ($error): string => $error->code(), $validator->validate($bad)->errors());
Assert::isTrue(in_array('content.type', $codes, true), 'A nested rich-text list was accepted.');

echo "Editor recovery 01 canonical schema and sanitizer tests passed.\n";
