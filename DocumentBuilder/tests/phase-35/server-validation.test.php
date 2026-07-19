<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use DocumentBuilder\Tests\Support\FixtureLoader;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\Settings;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CapabilityRegistry;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutValidator;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Node\NodeRegistry;

require dirname(__DIR__) . '/bootstrap.php';
$root = dirname(__DIR__, 2);
$module = "$root/files/custom/Espo/Modules/DocumentBuilder";
$layoutRoot = "$module/Tools/DocumentBuilder/Layout";
require "$module/Tools/DocumentBuilder/Config/Settings.php";
foreach ([
    'SchemaVersion.php', 'StableId.php', 'Unit.php', 'Measurement.php', 'Capability.php',
    'CapabilityStatus.php', 'CapabilityUnavailable.php', 'CapabilityNotPublishable.php',
    'CapabilityRegistry.php', 'Node/NodeKind.php', 'Node/NodeDefinition.php',
    'Node/UnknownNodeType.php', 'Node/NodeRegistry.php', 'ValidationError.php',
    'ValidationResult.php', 'LayoutValidator.php',
] as $file) {
    require "$layoutRoot/$file";
}

$settings = new Settings([
    'enabledSourceEntityTypeList' => [],
    'disabledSourceEntityTypeList' => [],
    'maxRelationshipDepth' => 2,
    'maxElements' => 500,
    'maxNestingDepth' => 8,
    'maxSections' => 100,
    'allowedFontList' => ['DejaVu Sans'],
    'customPageSizeList' => [],
]);
$validator = new LayoutValidator($settings, NodeRegistry::phase19(), CapabilityRegistry::phase19());
$layout = (new FixtureLoader("$root/tests/fixtures"))->json('layout/phase-08-default.json');
$layout['capabilities'] = ['layout.flow'];
$layout['header'] = [[
    'id' => 'headerParagraph',
    'type' => 'paragraph',
    'content' => [['type' => 'text', 'text' => 'Invoice', 'marks' => []]],
    'alignment' => 'end',
]];
$layout['document']['chrome']['header']['height']['value'] = 10;

$result = $validator->validate($layout);
Assert::isTrue($result->isValid(), 'Canonical page chrome was rejected: ' . implode(',', array_map(
    fn ($error) => $error->code() . ':' . $error->path(),
    $result->errors(),
)));
$codes = fn (array $candidate): array => array_map(
    fn ($error) => $error->code(),
    $validator->validate($candidate)->errors(),
);

$bad = $layout;
$bad['document']['chrome']['header']['height']['value'] = 16;
Assert::isTrue(in_array('pageChrome.marginReserved', $codes($bad), true), 'Chrome exceeding its reserved margin was accepted.');
$bad = $layout;
$bad['header'] = [];
Assert::isTrue(in_array('pageChrome.enabledHeight', $codes($bad), true), 'Empty chrome with a non-zero height was accepted.');
$bad = $layout;
$bad['header'][0]['type'] = 'heading';
$bad['header'][0]['level'] = 1;
$bad['header'][0]['keepWithNext'] = false;
Assert::isTrue(in_array('pageChrome.element', $codes($bad), true), 'A heading was accepted in page chrome.');

echo "Phase 35 page chrome server validation tests passed.\n";
