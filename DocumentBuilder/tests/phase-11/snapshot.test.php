<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use DocumentBuilder\Tests\Support\FixtureLoader;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CanonicalSerializer;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\ProcessedLayout;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\TemplateVersion\TemplateVersionSnapshotFactory;

require dirname(__DIR__) . '/bootstrap.php';

$extensionRoot = dirname(__DIR__, 2);
$moduleRoot = $extensionRoot . '/files/custom/Espo/Modules/DocumentBuilder';

foreach ([
    'Layout/CanonicalSerializer.php',
    'Layout/ProcessedLayout.php',
    'TemplateVersion/TemplateVersionSnapshot.php',
    'TemplateVersion/TemplateVersionSnapshotFactory.php',
] as $relativePath) {
    require "$moduleRoot/Tools/DocumentBuilder/$relativePath";
}

$layout = (new FixtureLoader($extensionRoot . '/tests/fixtures'))->json('layout/phase-08-default.json');
$serializer = new CanonicalSerializer();
$processed = new ProcessedLayout($layout, $serializer->serialize($layout));
$factory = new TemplateVersionSnapshotFactory($serializer);
$publishedAt = new DateTimeImmutable('2026-07-19 12:34:56+03:00');

$snapshot = $factory->create(
    'template-1',
    'Invoice',
    3,
    $processed,
    ['type' => 'none'],
    'publisher-1',
    $publishedAt,
    'owner-1',
    ['team-b', 'team-a', 'team-a'],
    '  Approved release  ',
);
$attributes = $snapshot->attributes();

Assert::same('Invoice v3', $attributes['name'] ?? null, 'Version display name must be deterministic.');
Assert::same(3, $attributes['versionNumber'] ?? null, 'Version number changed.');
Assert::same(1, $attributes['schemaVersion'] ?? null, 'Schema version must come from the processed layout.');
Assert::same($layout, $attributes['layoutSnapshot'] ?? null, 'The exact normalized layout must be persisted.');
Assert::same(['type' => 'none'], $attributes['sourceSnapshot'] ?? null, 'The normalized source snapshot changed.');
Assert::same('2026-07-19 09:34:56', $attributes['publishedAt'] ?? null, 'Publication time must be stored in UTC.');
Assert::same('Approved release', $attributes['changeNote'] ?? null, 'Change notes must be normalized.');
Assert::same(['team-a', 'team-b'], $attributes['teamsIds'] ?? null, 'Team ACL projection must be stable and unique.');
Assert::same(true, $attributes['isCurrent'] ?? null, 'New publication snapshots must start current.');
Assert::same(64, strlen($snapshot->checksum()), 'Snapshot checksum must be SHA-256.');
Assert::same(
    hash('sha256', $serializer->serialize([
        'schemaVersion' => 1,
        'layoutSnapshot' => $layout,
        'sourceSnapshot' => ['type' => 'none'],
    ])),
    $snapshot->checksum(),
    'Snapshot checksum must hash the complete canonical published content.',
);

$reorderedLayout = [
    'sections' => [],
    'schemaVersion' => 1,
    'document' => $layout['document'],
    'footer' => [],
    'header' => [],
    'dataSource' => ['type' => 'none'],
    'capabilities' => [],
];
$reordered = new ProcessedLayout($reorderedLayout, $serializer->serialize($reorderedLayout));
$sameContent = $factory->create(
    'different-template',
    'Renamed',
    99,
    $reordered,
    ['type' => 'none'],
    'different-publisher',
    new DateTimeImmutable('2030-01-01T00:00:00Z'),
    'different-owner',
);
Assert::same($snapshot->checksum(), $sameContent->checksum(), 'Snapshot checksum must depend only on canonical published content.');

$changedSource = $factory->create(
    'template-1',
    'Invoice',
    4,
    new ProcessedLayout(
        array_replace($layout, ['dataSource' => ['type' => 'entity', 'entityType' => 'Account', 'relationshipDepth' => 2]]),
        'unused',
    ),
    ['relationshipDepth' => 2, 'entityType' => 'Account', 'type' => 'entity'],
    'publisher-1',
    $publishedAt,
    'owner-1',
);
Assert::isFalse($snapshot->checksum() === $changedSource->checksum(), 'Source changes must alter the snapshot checksum.');
Assert::same(
    ['type' => 'entity', 'entityType' => 'Account', 'relationshipDepth' => 2],
    $changedSource->attributes()['sourceSnapshot'] ?? null,
    'The persisted source snapshot must use normalized layout ordering and values.',
);

Assert::throws(
    fn () => $factory->create('template-1', 'Invoice', 0, $processed, ['type' => 'none'], 'publisher-1', $publishedAt, 'owner-1'),
    InvalidArgumentException::class,
    'Non-positive version numbers must be rejected.',
);
Assert::throws(
    fn () => $factory->create('template-1', 'Invoice', 1, $processed, ['type' => 'entity'], 'publisher-1', $publishedAt, 'owner-1'),
    InvalidArgumentException::class,
    'Source snapshots must exactly match the normalized layout source.',
);

echo "Phase 11 template-version snapshot tests passed.\n";
