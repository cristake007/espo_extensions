<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use DocumentBuilder\Tests\Support\FixtureLoader;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\Settings;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Capability;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CapabilityNotPublishable;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CapabilityRegistry;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CapabilityStatus;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutValidator;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Node\NodeRegistry;

require dirname(__DIR__) . '/bootstrap.php';

$extensionRoot = dirname(__DIR__, 2);
$moduleRoot = $extensionRoot . '/files/custom/Espo/Modules/DocumentBuilder';
$layoutRoot = "$moduleRoot/Tools/DocumentBuilder/Layout";

require "$moduleRoot/Tools/DocumentBuilder/Config/Settings.php";
foreach ([
    'SchemaVersion.php', 'StableId.php', 'Unit.php', 'Measurement.php',
    'Capability.php', 'CapabilityStatus.php', 'CapabilityUnavailable.php',
    'CapabilityNotPublishable.php', 'CapabilityRegistry.php',
    'Node/NodeKind.php', 'Node/NodeDefinition.php', 'Node/UnknownNodeType.php',
    'Node/NodeRegistry.php', 'ValidationError.php', 'ValidationResult.php', 'LayoutValidator.php',
] as $file) {
    require "$layoutRoot/$file";
}

$settings = new Settings([
    'maxRelationshipDepth' => 2,
    'enabledSourceEntityTypeList' => [],
    'disabledSourceEntityTypeList' => [],
    'maxElements' => 5,
    'maxNestingDepth' => 4,
    'maxSections' => 3,
    'allowedFontList' => ['DejaVu Sans'],
    'customPageSizeList' => [],
]);
$capabilities = CapabilityRegistry::phase19();
$validator = new LayoutValidator($settings, NodeRegistry::phase19(), $capabilities);
$layout = (new FixtureLoader($extensionRoot . '/tests/fixtures'))->json('layout/phase-08-default.json');
$box = static fn (int $value): array => array_fill_keys(
    ['top', 'right', 'bottom', 'left'],
    ['value' => $value, 'unit' => 'mm'],
);
$container = static fn (string $id, array $children = []): array => [
    'id' => $id,
    'type' => 'flow-container',
    'children' => $children,
    'margin' => $box(0),
    'padding' => $box(0),
    'minHeight' => ['value' => 10, 'unit' => 'mm'],
    'keepTogether' => false,
];
$section = static fn (string $id, array $children = []): array => [
    'id' => $id,
    'type' => 'flow-section',
    'children' => $children,
    'margin' => $box(0),
    'padding' => $box(0),
    'minHeight' => ['value' => 20, 'unit' => 'mm'],
    'keepTogether' => true,
    'startNewPage' => false,
];
$codes = static fn ($result): array => array_map(
    static fn ($error): string => $error->code(),
    $result->errors(),
);

$layout['capabilities'] = [Capability::FlowLayout->value];
$layout['sections'] = [$section('sectionOne', [
    $container('containerOne', [$container('containerNested')]),
    $container('containerTwo'),
])];
Assert::isTrue($validator->validate($layout)->isValid(), 'A canonical flow hierarchy was rejected.');
Assert::same(CapabilityStatus::Draft, $capabilities->status(Capability::FlowLayout), 'Flow must be draft-capable.');
Assert::throws(
    fn () => $capabilities->requirePublishable([Capability::FlowLayout]),
    CapabilityNotPublishable::class,
    'Phase 19 must not make flow layouts publishable prematurely.',
);

$missingCapability = $layout;
$missingCapability['capabilities'] = [];
Assert::isTrue(
    in_array('capability.missing', $codes($validator->validate($missingCapability)), true),
    'Flow nodes without an explicit capability marker were accepted.',
);
$unusedCapability = (new FixtureLoader($extensionRoot . '/tests/fixtures'))->json('layout/phase-08-default.json');
$unusedCapability['capabilities'] = [Capability::FlowLayout->value];
Assert::isTrue(
    in_array('capability.unused', $codes($validator->validate($unusedCapability)), true),
    'An unused flow capability marker was accepted.',
);
$invalidParent = $layout;
$invalidParent['sections'] = [$container('containerAtRoot')];
$invalidCodes = $codes($validator->validate($invalidParent));
Assert::isTrue(in_array('flow.parent', $invalidCodes, true), 'A container was accepted as a root section.');
$invalidStyle = $layout;
$invalidStyle['sections'][0]['padding']['left'] = ['value' => -1, 'unit' => 'mm'];
Assert::isTrue(
    in_array('value.bounds', $codes($validator->validate($invalidStyle)), true),
    'Invalid flow spacing was accepted.',
);
$tooDeep = $layout;
$tooDeep['sections'][0]['children'][0]['children'][0]['children'] = [
    $container('depthFour', [$container('depthFive')]),
];
Assert::isTrue(
    in_array('node.depth', $codes($validator->validate($tooDeep)), true),
    'The configured nesting limit was not enforced.',
);
$tooManyElements = $layout;
$tooManyElements['sections'][0]['children'] = [
    $container('elementOne'), $container('elementTwo'), $container('elementThree'),
    $container('elementFour'), $container('elementFive'), $container('elementSix'),
];
Assert::isTrue(
    in_array('elements.limit', $codes($validator->validate($tooManyElements)), true),
    'The configured element limit was not enforced.',
);
$tooManySections = $layout;
$tooManySections['sections'] = [
    $section('sectionOne'), $section('sectionTwo'),
    $section('sectionThree'), $section('sectionFour'),
];
Assert::isTrue(
    in_array('sections.limit', $codes($validator->validate($tooManySections)), true),
    'The configured section limit was not enforced.',
);

echo "Phase 19 server flow registry, capability, structure, style, and limit tests passed.\n";
