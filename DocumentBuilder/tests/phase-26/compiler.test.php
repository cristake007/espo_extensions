<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityCatalogueAccess;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityCatalogueMetadata;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityCatalogueService;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityFieldPolicy;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityLabelProvider;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityMetadataTreeService;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntitySourcePolicyRules;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\RelationshipDepthLimit;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\SystemVariableRegistry;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\CompiledVariableReferenceValidator;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariablePathCompiler;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableUsage;

require dirname(__DIR__) . '/bootstrap.php';

$root = dirname(__DIR__, 2);
$module = "$root/files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder";
$catalogueRoot = "$module/DataSource/EntityCatalogue";
$variableRoot = "$module/DataSource/Variable";

foreach ([
    'EntityCatalogueMetadata.php', 'EntityCatalogueAccess.php', 'EntitySourcePolicy.php',
    'EntitySourceEligibility.php', 'EntityLabelProvider.php', 'RelationshipDepthLimit.php',
    'EntityCatalogueItem.php', 'EntityFieldItem.php', 'EntityRelationshipItem.php',
    'EntityMetadataTree.php', 'EntityFieldPolicy.php', 'EntitySourcePolicyRules.php',
    'EntityCatalogueService.php', 'EntityMetadataTreeService.php',
] as $file) {
    require "$catalogueRoot/$file";
}
foreach ([
    'VariableSource.php', 'VariableType.php', 'VariableUsage.php', 'VariablePath.php',
    'VariableIdentity.php', 'SystemVariableRegistry.php', 'VariablePathCompiler.php',
    'VariableReferenceValidator.php', 'CompiledVariableReferenceValidator.php',
] as $file) {
    require "$variableRoot/$file";
}
require "$module/Security/PermissionDenied.php";

final class Phase26Metadata implements EntityCatalogueMetadata
{
    /** @param array<string, array<string, mixed>> $definitions */
    public function __construct(private array $definitions) {}
    public function scopeDefinitions(): array
    {
        return array_map(static fn (): array => ['entity' => true, 'object' => true, 'acl' => true], $this->definitions);
    }
    public function hasEntityDefinition(string $entityType): bool { return isset($this->definitions[$entityType]); }
    public function isCustom(string $entityType): bool { return false; }
    public function entityDefinition(string $entityType): array { return $this->definitions[$entityType] ?? []; }
}

final class Phase26Access implements EntityCatalogueAccess
{
    public function requireCatalogueAccess(): void {}
    public function canRead(string $entityType): bool { return true; }
    public function canReadField(string $entityType, string $field): bool { return $field !== 'privateCode'; }
    public function canReadLink(string $entityType, string $link): bool { return true; }
}

final class Phase26Labels implements EntityLabelProvider
{
    public function __construct(private string $nameLabel = 'Name') {}
    public function label(string $entityType): string { return $entityType; }
    public function fieldLabel(string $entityType, string $field): string
    {
        return $field === 'name' ? $this->nameLabel : $field;
    }
    public function linkLabel(string $entityType, string $link): string { return $link; }
}

final class Phase26Depth implements RelationshipDepthLimit
{
    public function get(): int { return 2; }
}

$definitions = [
    'Contact' => [
        'fields' => [
            'firstName' => ['type' => 'varchar'],
            'privateCode' => ['type' => 'varchar'],
        ],
        'links' => [
            'account' => ['type' => 'belongsTo', 'entity' => 'Account'],
            'courses' => ['type' => 'hasMany', 'entity' => 'Course'],
        ],
    ],
    'Account' => [
        'fields' => ['name' => ['type' => 'varchar']],
        'links' => ['primaryContact' => ['type' => 'belongsTo', 'entity' => 'Contact']],
    ],
    'Course' => [
        'fields' => ['name' => ['type' => 'varchar']],
        'links' => [],
    ],
];

$makeCompiler = static function (EntityLabelProvider $labels) use ($definitions): VariablePathCompiler {
    $metadata = new Phase26Metadata($definitions);
    $access = new Phase26Access();
    $catalogue = new EntityCatalogueService(
        $metadata,
        $access,
        new EntitySourcePolicyRules([], []),
        $labels,
    );
    $tree = new EntityMetadataTreeService(
        $catalogue,
        $metadata,
        $access,
        $labels,
        new Phase26Depth(),
        new EntityFieldPolicy(),
    );

    return new VariablePathCompiler($tree, new SystemVariableRegistry());
};

$compiler = $makeCompiler(new Phase26Labels('Name'));
$entitySource = ['type' => 'entity', 'entityType' => 'Contact', 'relationshipDepth' => 2];
$direct = ['source' => 'entity', 'type' => 'direct', 'entityType' => 'Contact', 'path' => ['firstName']];
$related = ['source' => 'entity', 'type' => 'related', 'entityType' => 'Contact', 'path' => ['account', 'name']];
$collection = ['source' => 'entity', 'type' => 'collection', 'entityType' => 'Contact', 'path' => ['courses']];

Assert::same($direct, $compiler->compile($direct, $entitySource, VariableUsage::Scalar)->toArray(), 'Direct identity changed.');
Assert::same($related, $compiler->compile($related, $entitySource, VariableUsage::Scalar)->toArray(), 'Related identity changed.');
Assert::same($collection, $compiler->compile($collection, $entitySource, VariableUsage::Collection)->toArray(), 'Collection identity changed.');
Assert::same(
    $related,
    $makeCompiler(new Phase26Labels('Renamed account'))->compile(
        $related,
        $entitySource,
        VariableUsage::Scalar,
    )->toArray(),
    'Display-label changes must not alter identity.',
);

foreach ([
    [$collection, $entitySource, VariableUsage::Scalar, []],
    [['source'=>'entity','type'=>'direct','entityType'=>'Contact','path'=>['account','name']], $entitySource, VariableUsage::Scalar, []],
    [['source'=>'entity','type'=>'direct','entityType'=>'Contact','path'=>['privateCode']], $entitySource, VariableUsage::Scalar, []],
    [['source'=>'entity','type'=>'related','entityType'=>'Contact','path'=>['account','primaryContact','firstName']], $entitySource, VariableUsage::Scalar, []],
    [$direct, ['type'=>'entity','entityType'=>'Account','relationshipDepth'=>2], VariableUsage::Scalar, []],
    [['source'=>'entity','type'=>'related','entityType'=>'Contact','path'=>['account','missing']], $entitySource, VariableUsage::Scalar, []],
] as [$reference, $source, $usage, $columns]) {
    Assert::throws(
        fn () => $compiler->compile($reference, $source, $usage, $columns),
        Throwable::class,
        'An incompatible, forbidden, circular, or arbitrary entity path was accepted.',
    );
}

$system = ['source'=>'system','type'=>'system','path'=>['currentDate']];
Assert::same($system, $compiler->compile($system, ['type'=>'none'], VariableUsage::Scalar)->toArray(), 'System identity changed.');
Assert::throws(
    fn () => $compiler->compile(
        ['source'=>'system','type'=>'system','path'=>['arbitraryExpression']],
        ['type'=>'none'],
        VariableUsage::Scalar,
    ),
    InvalidArgumentException::class,
    'An arbitrary system variable was accepted.',
);
$spreadsheet = ['source'=>'spreadsheet','type'=>'spreadsheet','path'=>['email']];
Assert::same(
    $spreadsheet,
    $compiler->compile($spreadsheet, ['type'=>'spreadsheet','format'=>'csv'], VariableUsage::Scalar, ['email'])->toArray(),
    'Spreadsheet identity changed.',
);
Assert::throws(
    fn () => $compiler->compile($spreadsheet, ['type'=>'spreadsheet','format'=>'csv'], VariableUsage::Scalar, ['name']),
    InvalidArgumentException::class,
    'A column outside the approved spreadsheet schema was accepted.',
);
Assert::same(
    json_encode($related, JSON_THROW_ON_ERROR),
    json_encode($compiler->compile($related, $entitySource, VariableUsage::Scalar)->toArray(), JSON_THROW_ON_ERROR),
    'Variable serialization must be deterministic.',
);
$layoutValidator = new CompiledVariableReferenceValidator($compiler);
$layoutValidator->validate([
    'dataSource' => $entitySource,
    'sections' => [['content' => [[
        'type' => 'variable',
        'tokenId' => 'variable_1',
        'label' => 'Display label',
        'identity' => $related,
    ]]]],
], new stdClass());
Assert::throws(
    fn () => $layoutValidator->validate([
        'dataSource' => $entitySource,
        'sections' => [['content' => [[
            'type' => 'variable',
            'tokenId' => 'variable_2',
            'label' => 'Private',
            'identity' => ['source'=>'entity','type'=>'direct','entityType'=>'Contact','path'=>['privateCode']],
        ]]]],
    ], new stdClass()),
    Throwable::class,
    'The draft storage boundary accepted an unreadable reference.',
);

echo "Phase 26 canonical identity, path compilation, compatibility, ACL, cycle, and usage tests passed.\n";
