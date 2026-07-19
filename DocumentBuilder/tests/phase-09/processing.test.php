<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use DocumentBuilder\Tests\Support\FixtureLoader;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\Settings;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CanonicalSerializer;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Capability;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CapabilityNotPublishable;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CapabilityRegistry;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CapabilityStatus;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CapabilityUnavailable;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Error\InvalidLayout;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Error\LayoutTooLarge;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Error\MalformedLayout;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Error\UnsupportedSchemaVersion;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutMigrator;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutNormalizer;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutParser;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutProcessor;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutValidator;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Migration\LayoutMigration;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Node\NodeRegistry;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\ValidationError;

require dirname(__DIR__) . '/bootstrap.php';

$extensionRoot = dirname(__DIR__, 2);
$moduleRoot = $extensionRoot . '/files/custom/Espo/Modules/DocumentBuilder';
$layoutRoot = $moduleRoot . '/Tools/DocumentBuilder/Layout';
$fixtureLoader = new FixtureLoader($extensionRoot . '/tests/fixtures');

require "$moduleRoot/Tools/DocumentBuilder/Config/Settings.php";

foreach ([
    'SchemaVersion.php',
    'StableId.php',
    'Unit.php',
    'Measurement.php',
    'NestingDepth.php',
    'Capability.php',
    'CapabilityStatus.php',
    'CapabilityUnavailable.php',
    'CapabilityNotPublishable.php',
    'CapabilityRegistry.php',
    'EmptyJsonObject.php',
    'Source/SourceType.php',
    'Source/SourceDescriptor.php',
    'Source/NoSourceDescriptor.php',
    'Node/NodeKind.php',
    'Node/NodeDefinition.php',
    'Node/UnknownNodeType.php',
    'Node/NodeRegistry.php',
    'LayoutDefaults.php',
    'ValidationError.php',
    'ValidationResult.php',
    'Error/LayoutProcessingException.php',
    'Error/LayoutTooLarge.php',
    'Error/MalformedLayout.php',
    'Error/UnsupportedSchemaVersion.php',
    'Error/InvalidLayout.php',
    'Migration/LayoutMigration.php',
    'LayoutParser.php',
    'LayoutMigrator.php',
    'LayoutNormalizer.php',
    'LayoutValidator.php',
    'CanonicalSerializer.php',
    'ProcessedLayout.php',
    'LayoutProcessor.php',
] as $relativePath) {
    require "$layoutRoot/$relativePath";
}

/** @param array<string, mixed> $overrides */
function phase09Settings(array $overrides = []): Settings
{
    return new Settings(array_replace([
        'maxLayoutBytes' => 1048576,
        'maxRelationshipDepth' => 2,
        'enabledSourceEntityTypeList' => [],
        'disabledSourceEntityTypeList' => [],
        'maxElements' => 500,
        'maxNestingDepth' => 8,
        'maxSections' => 100,
        'allowedFontList' => ['DejaVu Sans'],
        'defaultFont' => 'DejaVu Sans',
        'defaultLocale' => 'en_US',
        'defaultPageSize' => 'A4',
    ], $overrides));
}

function phase09Processor(?Settings $settings = null): LayoutProcessor
{
    $settings ??= phase09Settings();

    return new LayoutProcessor(
        new LayoutParser($settings),
        new LayoutMigrator(),
        new LayoutNormalizer($settings),
        new LayoutValidator($settings, new NodeRegistry(), CapabilityRegistry::phase08()),
        new CanonicalSerializer(),
    );
}

/** @return list<ValidationError> */
function phase09InvalidErrors(callable $callback): array
{
    try {
        $callback();
    } catch (InvalidLayout $exception) {
        return $exception->result()->errors();
    }

    throw new RuntimeException('Expected layout validation to fail.');
}

/** @param list<ValidationError> $errors */
function phase09FindError(array $errors, string $code): ValidationError
{
    foreach ($errors as $error) {
        if ($error->code() === $code) {
            return $error;
        }
    }

    throw new RuntimeException("Expected validation error was not found: $code.");
}

$partial = $fixtureLoader->json('layout/phase-09-partial.json');
$default = $fixtureLoader->json('layout/phase-08-default.json');
$normalizer = new LayoutNormalizer(phase09Settings());
$normalized = $normalizer->normalize($partial);
Assert::same($default, $normalized, 'Missing optional layout values must receive canonical defaults.');
Assert::same($normalized, $normalizer->normalize($normalized), 'Layout normalization must be idempotent.');

$processed = phase09Processor()->process(json_encode($partial, JSON_THROW_ON_ERROR));
Assert::same($default, $processed->layout(), 'The processor must return the normalized layout.');
Assert::same(
    hash('sha256', $processed->canonicalJson()),
    $processed->checksum(),
    'The checksum must use the canonical JSON bytes.',
);

$reordered = [
    'sections' => [],
    'schemaVersion' => 1,
    'document' => $default['document'],
    'footer' => [],
    'header' => [],
    'dataSource' => ['type' => 'none'],
    'capabilities' => [],
];
$processedReordered = phase09Processor()->process(json_encode($reordered, JSON_THROW_ON_ERROR));
Assert::same(
    $processed->canonicalJson(),
    $processedReordered->canonicalJson(),
    'Object key order must not change canonical serialization.',
);
Assert::same(
    $processed->canonicalJson(),
    (new CanonicalSerializer())->hashInput($processed->layout()),
    'Hash input and persisted canonical serialization must be identical.',
);

foreach (['{', 'null', '[]', '"layout"'] as $malformed) {
    Assert::throws(
        fn () => phase09Processor()->process($malformed),
        MalformedLayout::class,
        'Malformed JSON or a non-object root must be rejected.',
    );
}

Assert::same(
    $default,
    phase09Processor()->process('{"schemaVersion":1,"document":{}}')->layout(),
    'An empty JSON object at a defaultable object boundary must normalize safely.',
);
Assert::throws(
    fn () => phase09Processor()->process('{"schemaVersion":1,"document":[]}'),
    InvalidLayout::class,
    'An empty JSON array must not be confused with an empty object.',
);

$wholeFloat = $default;
$wholeFloat['document']['defaults']['fontSize']['value'] = 10.0;
Assert::same(
    $processed->canonicalJson(),
    phase09Processor()->process(json_encode($wholeFloat, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR))->canonicalJson(),
    'Equivalent integer and whole-float values must have the same canonical hash input.',
);

Assert::throws(
    fn () => phase09Processor(phase09Settings(['maxLayoutBytes' => 10]))->process('{"schemaVersion":1}'),
    LayoutTooLarge::class,
    'The byte limit must be enforced before parsing.',
);

foreach ([0, 2, 999, '1', null] as $version) {
    Assert::throws(
        fn () => phase09Processor()->process(json_encode(['schemaVersion' => $version], JSON_THROW_ON_ERROR)),
        UnsupportedSchemaVersion::class,
        'Missing, malformed, old-without-migration, and future schema versions must be rejected.',
    );
}

$migrator = new LayoutMigrator();
Assert::same(
    ['marker' => 'unchanged', 'schemaVersion' => 1],
    $migrator->migrate(['marker' => 'unchanged', 'schemaVersion' => 1]),
    'The current-version migration path must be a deterministic no-op.',
);
Assert::throws(
    fn () => new LayoutMigrator(new class implements LayoutMigration {
        public function fromVersion(): int { return 1; }
        public function toVersion(): int { return 3; }
        public function migrate(array $layout): array { return $layout; }
    }),
    InvalidArgumentException::class,
    'Migration registration must reject non-consecutive steps.',
);

$draftStatuses = array_fill_keys(
    array_map(static fn (Capability $capability): string => $capability->value, Capability::cases()),
    CapabilityStatus::SchemaOnly,
);
$draftStatuses[Capability::FlowLayout->value] = CapabilityStatus::Draft;
$draftCapabilities = new CapabilityRegistry($draftStatuses);
$draftCapabilities->requireUsable([Capability::FlowLayout]);
Assert::throws(
    fn () => $draftCapabilities->requirePublishable([Capability::FlowLayout]),
    CapabilityNotPublishable::class,
    'Draft-ready node schemas must remain blocked from publication.',
);
Assert::throws(
    fn () => CapabilityRegistry::phase08()->requireUsable([Capability::FlowLayout]),
    CapabilityUnavailable::class,
    'Schema-only capability markers must remain unavailable to generic processing.',
);

$invalidLocality = file_get_contents($extensionRoot . '/tests/fixtures/layout/phase-09-invalid-locality.json');

if ($invalidLocality === false) {
    throw new RuntimeException('Could not load the invalid locality fixture.');
}

$localityErrors = phase09InvalidErrors(fn () => phase09Processor()->process($invalidLocality));
$sectionTypeError = phase09FindError($localityErrors, 'node.unknownType');
Assert::same('/sections[id=sectionOne]/type', $sectionTypeError->path(), 'Section errors must use a stable ID path.');
Assert::same('sectionOne', $sectionTypeError->elementId(), 'Section errors must expose the stable element ID.');

$rawHtmlError = null;

foreach ($localityErrors as $error) {
    if ($error->elementId() === 'elementOne' && $error->code() === 'node.unknownType') {
        $rawHtmlError = $error;
        break;
    }
}

Assert::isTrue($rawHtmlError instanceof ValidationError, 'Nested unknown content must be rejected at its own ID.');
Assert::same(
    '/sections[id=sectionOne]/children[id=elementOne]/type',
    $rawHtmlError->path(),
    'Nested error locality changed.',
);

$invalidBounds = $default;
$invalidBounds['document']['page']['margins']['left']['value'] = 2001;
$boundErrors = phase09InvalidErrors(
    fn () => phase09Processor()->process(json_encode($invalidBounds, JSON_THROW_ON_ERROR)),
);
Assert::same(
    '/document/page/margins/left/value',
    phase09FindError($boundErrors, 'value.bounds')->path(),
    'Typed measurement bounds must identify the invalid value.',
);

$entitySource = $partial;
$entitySource['dataSource'] = ['type' => 'entity', 'entityType' => 'Contact', 'relationshipDepth' => 2];
Assert::same(
    $entitySource['dataSource'],
    phase09Processor()->process(json_encode($entitySource, JSON_THROW_ON_ERROR))->layout()['dataSource'],
    'Phase 12 entity-source drafts must retain their validated descriptor.',
);
$invalidSource = $partial;
$invalidSource['dataSource'] = ['type' => 'entity', 'entityType' => '../Contact', 'relationshipDepth' => 99];
$sourceErrors = phase09InvalidErrors(fn () => phase09Processor()->process(json_encode($invalidSource, JSON_THROW_ON_ERROR)));
Assert::same('/dataSource/entityType', phase09FindError($sourceErrors, 'source.entityType')->path(), 'Entity-source errors changed.');
Assert::same('/dataSource/relationshipDepth', phase09FindError($sourceErrors, 'source.relationshipDepth')->path(), 'Source-depth errors changed.');
$disabledSourceErrors = phase09InvalidErrors(
    fn () => phase09Processor(phase09Settings(['disabledSourceEntityTypeList' => ['Contact']]))
        ->process(json_encode($entitySource, JSON_THROW_ON_ERROR)),
);
Assert::same(
    '/dataSource/entityType',
    phase09FindError($disabledSourceErrors, 'source.entityTypeDisabled')->path(),
    'Configured entity-source restrictions must be authoritative.',
);

$duplicateIds = $partial;
$duplicateIds['sections'] = [
    ['id' => 'duplicateId', 'type' => 'flow'],
    ['id' => 'duplicateId', 'type' => 'flow'],
];
$duplicateErrors = phase09InvalidErrors(
    fn () => phase09Processor()->process(json_encode($duplicateIds, JSON_THROW_ON_ERROR)),
);
Assert::same(
    '/sections[id=duplicateId]/id',
    phase09FindError($duplicateErrors, 'node.idDuplicate')->path(),
    'Duplicate stable IDs must be rejected at the repeated node.',
);

$deepLayout = $partial;
$deepLayout['sections'] = [[
    'id' => 'depthOne',
    'type' => 'flow',
    'children' => [[
        'id' => 'depthTwo',
        'type' => 'text',
        'children' => [[
            'id' => 'depthThree',
            'type' => 'text',
        ]],
    ]],
]];
$depthErrors = phase09InvalidErrors(fn () => phase09Processor(phase09Settings([
    'maxNestingDepth' => 2,
]))->process(json_encode($deepLayout, JSON_THROW_ON_ERROR)));
$depthError = phase09FindError($depthErrors, 'node.depth');
Assert::same('depthThree', $depthError->elementId(), 'Depth errors must identify the deepest element.');

$limitedLayout = $partial;
$limitedLayout['sections'] = [
    ['id' => 'one', 'type' => 'flow', 'children' => [
        ['id' => 'childOne', 'type' => 'text'],
        ['id' => 'childTwo', 'type' => 'text'],
    ]],
    ['id' => 'two', 'type' => 'flow'],
];
$limitErrors = phase09InvalidErrors(fn () => phase09Processor(phase09Settings([
    'maxElements' => 1,
    'maxSections' => 1,
]))->process(json_encode($limitedLayout, JSON_THROW_ON_ERROR)));
phase09FindError($limitErrors, 'elements.limit');
phase09FindError($limitErrors, 'sections.limit');

$unknownProperty = $partial;
$unknownProperty['rawHtml'] = '<script>never evaluated</script>';
$unknownErrors = phase09InvalidErrors(
    fn () => phase09Processor()->process(json_encode($unknownProperty, JSON_THROW_ON_ERROR)),
);
Assert::same('/rawHtml', phase09FindError($unknownErrors, 'property.unknown')->path(), 'Unknown JSON must fail closed.');

echo "Phase 09 layout processing tests passed.\n";
