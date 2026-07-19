<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use DocumentBuilder\Tests\Support\FixtureLoader;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityCatalogueAccess;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityCatalogueMetadata;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityCatalogueService;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityFieldPolicy;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityLabelProvider;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityMetadataTreeService;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntitySourcePolicyRules;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\RelationshipDepthLimit;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\PermissionDenied;

require dirname(__DIR__) . '/bootstrap.php';

$root = dirname(__DIR__, 2);
$source = "$root/files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/DataSource/EntityCatalogue";

foreach ([
    'EntityCatalogueMetadata.php', 'EntityCatalogueAccess.php', 'EntitySourcePolicy.php',
    'EntitySourceEligibility.php', 'EntityLabelProvider.php', 'RelationshipDepthLimit.php',
    'EntityCatalogueItem.php', 'EntityFieldItem.php', 'EntityRelationshipItem.php',
    'EntityMetadataTree.php', 'EntityFieldPolicy.php', 'EntitySourcePolicyRules.php',
    'EntityCatalogueService.php', 'EntityMetadataTreeService.php',
] as $file) {
    require "$source/$file";
}
require "$root/files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Security/PermissionDenied.php";

$fixtures = new FixtureLoader("$root/tests/fixtures/metadata/entityDefs");
$definitions = [
    'Account' => $fixtures->json('Account.json'),
    'Contact' => $fixtures->json('Contact.json'),
    'CourseEnrollment' => $fixtures->json('CourseEnrollment.json'),
    'ForbiddenEntity' => ['fields' => ['name' => ['type' => 'varchar']], 'links' => []],
    'User' => ['fields' => ['name' => ['type' => 'varchar']], 'links' => []],
];
$definitions['Contact']['fields']['displayCode'] = [
    'type' => 'varchar', 'calculated' => true, 'readOnly' => true, 'custom' => true,
];
$definitions['Contact']['fields']['apiToken'] = ['type' => 'varchar'];
$definitions['Contact']['fields']['documentFile'] = ['type' => 'file'];
$definitions['Contact']['links']['privateRelation'] = ['type' => 'belongsTo', 'entity' => 'Account'];
$definitions['Contact']['links']['forbiddenRelation'] = ['type' => 'belongsTo', 'entity' => 'ForbiddenEntity'];

final class Phase25Metadata implements EntityCatalogueMetadata
{
    /** @param array<string, array<string, mixed>> $definitions */
    public function __construct(private array $definitions) {}
    public function scopeDefinitions(): array
    {
        return array_map(static fn (): array => ['entity' => true, 'object' => true, 'acl' => true], $this->definitions);
    }
    public function hasEntityDefinition(string $entityType): bool { return isset($this->definitions[$entityType]); }
    public function isCustom(string $entityType): bool { return $entityType === 'CourseEnrollment'; }
    public function entityDefinition(string $entityType): array { return $this->definitions[$entityType] ?? []; }
}

final class Phase25Access implements EntityCatalogueAccess
{
    /** @param list<string> $readable @param list<string> $deniedFields @param list<string> $deniedLinks */
    public function __construct(
        private array $readable,
        private array $deniedFields = [],
        private array $deniedLinks = [],
    ) {}
    public function requireCatalogueAccess(): void {}
    public function canRead(string $entityType): bool { return in_array($entityType, $this->readable, true); }
    public function canReadField(string $entityType, string $field): bool
    {
        return !in_array("$entityType.$field", $this->deniedFields, true);
    }
    public function canReadLink(string $entityType, string $link): bool
    {
        return !in_array("$entityType.$link", $this->deniedLinks, true);
    }
}

final class Phase25Labels implements EntityLabelProvider
{
    public function label(string $entityType): string { return $entityType; }
    public function fieldLabel(string $entityType, string $field): string
    {
        return ['firstName' => 'Prenume', 'displayCode' => 'Cod afișat'][$field] ?? $field;
    }
    public function linkLabel(string $entityType, string $link): string
    {
        return ['account' => 'Cont', 'attendedCourses' => 'Cursuri urmate'][$link] ?? $link;
    }
}

final class Phase25Depth implements RelationshipDepthLimit
{
    public function get(): int { return 2; }
}

$metadata = new Phase25Metadata($definitions);
$access = new Phase25Access(
    ['Account', 'Contact', 'CourseEnrollment'],
    ['Contact.certificateNumber'],
    ['Contact.privateRelation'],
);
$catalogue = new EntityCatalogueService(
    $metadata,
    $access,
    new EntitySourcePolicyRules([], []),
    new Phase25Labels(),
);
$treeService = new EntityMetadataTreeService(
    $catalogue,
    $metadata,
    $access,
    new Phase25Labels(),
    new Phase25Depth(),
    new EntityFieldPolicy(),
);
$rootTree = $treeService->get('Contact')->toArray();
$fieldMap = array_column($rootTree['fields'], null, 'name');
$linkMap = array_column($rootTree['relationships'], null, 'name');

Assert::same('Prenume', $fieldMap['firstName']['label'] ?? null, 'Labels must not replace stable field identifiers.');
Assert::same(true, $fieldMap['lastName']['required'] ?? null, 'Required display metadata changed.');
Assert::same(true, $fieldMap['displayCode']['calculated'] ?? null, 'Calculated-field classification changed.');
Assert::same(true, $fieldMap['displayCode']['readOnly'] ?? null, 'Read-only display metadata changed.');
Assert::same(true, $fieldMap['displayCode']['custom'] ?? null, 'Custom-field classification changed.');
Assert::isFalse(isset($fieldMap['certificateNumber']), 'Field ACL must remove denied fields.');
foreach (['internalToken', 'apiToken', 'documentFile', 'account', 'attendedCourses'] as $excluded) {
    Assert::isFalse(isset($fieldMap[$excluded]), "Secret, unsupported, or relationship field leaked: $excluded.");
}
Assert::same(true, $linkMap['account']['single'] ?? null, 'Belongs-to relationships must be single.');
Assert::same(true, $linkMap['attendedCourses']['collection'] ?? null, 'Has-many relationships must be collections.');
Assert::isFalse(isset($linkMap['privateRelation']), 'Link ACL must remove denied relationships.');
Assert::isFalse(isset($linkMap['forbiddenRelation']), 'Unreadable target scopes must not appear.');

$courseTree = $treeService->get('Contact', ['attendedCourses'])->toArray();
$courseLinks = array_column($courseTree['relationships'], null, 'name');
Assert::same(true, $courseLinks['contact']['circular'] ?? null, 'Ancestor cycles must be marked non-expandable.');
Assert::same(false, $courseLinks['contact']['expandable'] ?? null, 'Circular relationships must not expand.');
Assert::throws(
    fn () => $treeService->get('Contact', ['attendedCourses', 'contact']),
    PermissionDenied::class,
    'A crafted circular path must be rejected server-side.',
);
$accountTree = $treeService->get('Contact', ['attendedCourses', 'account'])->toArray();
foreach ($accountTree['relationships'] as $relationship) {
    Assert::same(true, $relationship['depthLimited'], 'Maximum-depth relationships must be marked.');
    Assert::same(false, $relationship['expandable'], 'Maximum-depth relationships must not expand.');
}

$restrictedAccess = new Phase25Access(['Account', 'Contact', 'CourseEnrollment'], ['Contact.lastName']);
$restrictedTree = new EntityMetadataTreeService(
    new EntityCatalogueService($metadata, $restrictedAccess, new EntitySourcePolicyRules([], []), new Phase25Labels()),
    $metadata,
    $restrictedAccess,
    new Phase25Labels(),
    new Phase25Depth(),
    new EntityFieldPolicy(),
);
Assert::isFalse(
    in_array('lastName', array_column($restrictedTree->get('Contact')->toArray()['fields'], 'name'), true),
    'Metadata-tree caches must not cross field-ACL contexts.',
);

echo "Phase 25 field/link classification, ACL, exclusions, labels, cycles, depth, and cache tests passed.\n";
