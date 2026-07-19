<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use DocumentBuilder\Tests\Support\FixtureLoader;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Capability;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CapabilityNotPublishable;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CapabilityRegistry;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CapabilityStatus;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutDefaults;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Measurement;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\NestingDepth;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Node\NodeDefinition;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Node\NodeKind;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Node\NodeRegistry;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Node\UnknownNodeType;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\SchemaVersion;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Source\EntitySourceDescriptor;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Source\NoSourceDescriptor;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Source\SpreadsheetFormat;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Source\SpreadsheetSourceDescriptor;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\StableId;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Unit;
require dirname(__DIR__) . '/bootstrap.php';

$extensionRoot = dirname(__DIR__, 2);
$moduleRoot = $extensionRoot . '/files/custom/Espo/Modules/DocumentBuilder';
$layoutRoot = $moduleRoot . '/Tools/DocumentBuilder/Layout';
$fixtureLoader = new FixtureLoader($extensionRoot . '/tests/fixtures');
$resourceLoader = new FixtureLoader($moduleRoot . '/Resources');

foreach ([
    'SchemaVersion.php',
    'StableId.php',
    'Unit.php',
    'Measurement.php',
    'NestingDepth.php',
    'Capability.php',
    'CapabilityStatus.php',
    'CapabilityNotPublishable.php',
    'CapabilityRegistry.php',
    'Source/SourceType.php',
    'Source/SourceDescriptor.php',
    'Source/NoSourceDescriptor.php',
    'Source/EntitySourceDescriptor.php',
    'Source/SpreadsheetFormat.php',
    'Source/SpreadsheetSourceDescriptor.php',
    'Node/NodeKind.php',
    'Node/NodeDefinition.php',
    'Node/UnknownNodeType.php',
    'Node/NodeRegistry.php',
    'LayoutDefaults.php',
] as $relativePath) {
    require "$layoutRoot/$relativePath";
}

$cases = $fixtureLoader->json('layout/phase-08-cases.json');

foreach ($cases['stableIds']['valid'] as $value) {
    Assert::same($value, (new StableId($value))->value(), "Valid stable ID was rejected: $value.");
}

foreach ($cases['stableIds']['invalid'] as $value) {
    Assert::throws(
        fn () => new StableId($value),
        InvalidArgumentException::class,
        "Invalid stable ID was accepted: $value.",
    );
}

foreach ($cases['measurements']['valid'] as $value) {
    $unit = Unit::from($value['unit']);
    Assert::same($value, (new Measurement($value['value'], $unit))->toArray(), 'Valid measurement changed.');
}

foreach ($cases['measurements']['invalid'] as $value) {
    Assert::throws(
        static function () use ($value): void {
            $unit = Unit::from($value['unit']);
            new Measurement($value['value'], $unit);
        },
        Throwable::class,
        'An invalid or non-canonical measurement was accepted.',
    );
}

foreach ($cases['depths']['valid'] as $value) {
    Assert::same($value, (new NestingDepth($value))->value(), 'Valid nesting depth changed.');
}

foreach ($cases['depths']['invalid'] as $value) {
    Assert::throws(
        fn () => new NestingDepth($value),
        InvalidArgumentException::class,
        'A depth outside the hard limit was accepted.',
    );
}

foreach ($cases['versions']['valid'] as $version) {
    Assert::same(SchemaVersion::V1, SchemaVersion::tryFrom($version), 'Supported schema version changed.');
}

foreach ($cases['versions']['invalid'] as $version) {
    Assert::same(null, SchemaVersion::tryFrom($version), 'An unsupported schema version was accepted.');
}

$nodeRegistry = new NodeRegistry(new NodeDefinition(
    NodeKind::Section,
    'fixture-flow',
    [Capability::FlowLayout],
));
Assert::same(
    'fixture-flow',
    $nodeRegistry->require(NodeKind::Section, 'fixture-flow')->type(),
    'A registered node type must resolve deterministically.',
);

foreach ($cases['nodeTypes']['unknown'] as $type) {
    Assert::throws(
        fn () => $nodeRegistry->require(NodeKind::Section, $type),
        UnknownNodeType::class,
        "Unknown node type was accepted: $type.",
    );
}

Assert::throws(
    fn () => new NodeRegistry(
        new NodeDefinition(NodeKind::Section, 'fixture-flow', [Capability::FlowLayout]),
        new NodeDefinition(NodeKind::Section, 'fixture-flow', [Capability::FlowLayout]),
    ),
    InvalidArgumentException::class,
    'Duplicate node definitions must be rejected.',
);

$capabilities = CapabilityRegistry::phase08();

foreach (Capability::cases() as $capability) {
    Assert::same(
        CapabilityStatus::SchemaOnly,
        $capabilities->status($capability),
        "Phase 08 must not silently enable capability: {$capability->value}.",
    );
    Assert::throws(
        fn () => $capabilities->requirePublishable([$capability]),
        CapabilityNotPublishable::class,
        "Unavailable capability became publishable: {$capability->value}.",
    );
}

$noSource = new NoSourceDescriptor();
Assert::same(['type' => 'none'], $noSource->toArray(), 'The neutral source descriptor changed.');
Assert::same(null, $noSource->requiredCapability(), 'The neutral source must not require a capability.');
Assert::same(
    ['type' => 'entity', 'entityType' => 'Contact', 'relationshipDepth' => 2],
    (new EntitySourceDescriptor('Contact'))->toArray(),
    'The entity source descriptor changed.',
);
Assert::same(
    ['type' => 'spreadsheet', 'format' => 'xlsx', 'worksheet' => 'Participants'],
    (new SpreadsheetSourceDescriptor(SpreadsheetFormat::Xlsx, 'Participants'))->toArray(),
    'The spreadsheet source descriptor changed.',
);
Assert::throws(
    fn () => new SpreadsheetSourceDescriptor(SpreadsheetFormat::Csv, 'Sheet1'),
    InvalidArgumentException::class,
    'A CSV source must not carry an XLSX worksheet.',
);

$defaultFixture = $fixtureLoader->json('layout/phase-08-default.json');
Assert::same($defaultFixture, LayoutDefaults::create(), 'Canonical defaulting must be deterministic.');
Assert::same($defaultFixture, LayoutDefaults::create(), 'Repeated canonical defaulting must be idempotent.');

$schema = $resourceLoader->json('jsonSchema/document-builder-layout-v1.json');
Assert::same(1, $schema['properties']['schemaVersion']['const'] ?? null, 'Schema v1 must reject future versions.');
Assert::same(0, $schema['properties']['capabilities']['maxItems'] ?? null, 'Phase 08 capabilities must be closed.');
Assert::same(
    '#/$defs/noSourceDescriptor',
    $schema['properties']['dataSource']['$ref'] ?? null,
    'Unsupported source descriptors must not be accepted by the root schema.',
);

foreach (['header', 'sections', 'footer'] as $region) {
    Assert::same(
        '#/$defs/emptyNodeSequence',
        $schema['properties'][$region]['$ref'] ?? null,
        "Phase 08 must reject unimplemented nodes in $region.",
    );
}

foreach ([
    'stableId',
    'measurement',
    'commonStyle',
    'node',
    'entitySourceDescriptor',
    'spreadsheetSourceDescriptor',
    'sourceDescriptor',
] as $definition) {
    Assert::isTrue(isset($schema['$defs'][$definition]), "Schema extension point is missing: $definition.");
}

Assert::isFalse(
    str_contains(json_encode($schema, JSON_THROW_ON_ERROR), '"const":"px"'),
    'Pixels must never be a canonical layout unit.',
);

echo "Phase 08 schema primitive tests passed.\n";
