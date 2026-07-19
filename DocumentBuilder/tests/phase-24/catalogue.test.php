<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityCatalogueAccess;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityCatalogueMetadata;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityCatalogueService;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityLabelProvider;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntitySourcePolicy;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntitySourcePolicyRules;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\PermissionDenied;

require dirname(__DIR__) . '/bootstrap.php';

$root = dirname(__DIR__, 2);
$source = "$root/files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/DataSource/EntityCatalogue";

foreach ([
    'EntityCatalogueMetadata.php',
    'EntityCatalogueAccess.php',
    'EntitySourceEligibility.php',
    'EntitySourcePolicy.php',
    'EntitySourcePolicyRules.php',
    'EntityLabelProvider.php',
    'EntityCatalogueItem.php',
    'EntityCatalogueService.php',
] as $file) {
    require "$source/$file";
}
require "$root/files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Security/PermissionDenied.php";

final class Phase24Metadata implements EntityCatalogueMetadata
{
    /** @var list<string> */
    public array $definitionChecks = [];

    public function scopeDefinitions(): array
    {
        return [
            'Contact' => ['entity' => true, 'object' => true, 'acl' => true],
            'Account' => ['entity' => true, 'object' => true, 'acl' => true],
            'BadLabelRecord' => ['entity' => true, 'object' => true, 'acl' => true],
            'CustomRecord' => ['entity' => true, 'object' => true, 'acl' => true, 'isCustom' => true],
            'DisabledRecord' => ['entity' => true, 'object' => true, 'acl' => true, 'disabled' => true],
            'InternalRecord' => ['entity' => true, 'object' => true, 'acl' => true],
            'NoReadRecord' => ['entity' => true, 'object' => true, 'acl' => true],
            'NotAnObject' => ['entity' => true, 'object' => false, 'acl' => true],
            '../Contact' => ['entity' => true, 'object' => true, 'acl' => true],
            'MissingDefinition' => ['entity' => true, 'object' => true, 'acl' => true],
        ];
    }

    public function hasEntityDefinition(string $entityType): bool
    {
        $this->definitionChecks[] = $entityType;

        return $entityType !== 'MissingDefinition';
    }

    public function isCustom(string $entityType): bool
    {
        return $entityType === 'CustomRecord';
    }

    public function entityDefinition(string $entityType): array
    {
        return $this->hasEntityDefinition($entityType) ? ['fields' => [], 'links' => []] : [];
    }
}

final class Phase24Access implements EntityCatalogueAccess
{
    public int $required = 0;
    /** @param list<string> $readable */
    public function __construct(private array $readable) {}
    public function requireCatalogueAccess(): void { $this->required++; }
    public function canRead(string $entityType): bool { return in_array($entityType, $this->readable, true); }
    public function canReadField(string $entityType, string $field): bool { return $this->canRead($entityType); }
    public function canReadLink(string $entityType, string $link): bool { return $this->canRead($entityType); }
}

final class Phase24Policy implements EntitySourcePolicy
{
    public function allows(string $entityType): bool
    {
        return preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,99}\z/D', $entityType) === 1 &&
            $entityType !== 'InternalRecord';
    }
}

final class Phase24Labels implements EntityLabelProvider
{
    public function label(string $entityType): string
    {
        return [
            'Account' => 'Cont',
            'BadLabelRecord' => "Bad\nLabel",
            'Contact' => 'Persoană',
            'CustomRecord' => 'Înregistrare proprie',
        ][$entityType] ?? $entityType;
    }
    public function fieldLabel(string $entityType, string $field): string { return $field; }
    public function linkLabel(string $entityType, string $link): string { return $link; }
}

$metadata = new Phase24Metadata();
$configuredRules = new EntitySourcePolicyRules(['Account', 'Contact'], ['Contact']);
Assert::isTrue($configuredRules->allows('Account'), 'The explicit source allowlist must permit a configured entity.');
Assert::isFalse($configuredRules->allows('Contact'), 'The source denylist must win over the allowlist.');
Assert::isFalse($configuredRules->allows('CustomRecord'), 'A non-empty allowlist must exclude unlisted custom entities.');
Assert::isFalse($configuredRules->allows('User'), 'Internal entities must remain forbidden.');
Assert::isFalse($configuredRules->allows('../Contact'), 'Malformed entity names must remain forbidden.');
Assert::isTrue((new EntitySourcePolicyRules([], []))->allows('CustomRecord'), 'An empty allowlist must permit eligible custom entities dynamically.');
$access = new Phase24Access(['Account', 'BadLabelRecord', 'Contact', 'CustomRecord', 'InternalRecord']);
$service = new EntityCatalogueService($metadata, $access, new Phase24Policy(), new Phase24Labels());
$items = array_map(static fn ($item): array => $item->toArray(), $service->get());

Assert::same(['BadLabelRecord', 'Account', 'Contact', 'CustomRecord'], array_column($items, 'entityType'), 'Eligible entities must be label-sorted.');
Assert::same(['BadLabelRecord', 'Cont', 'Persoană', 'Înregistrare proprie'], array_column($items, 'label'), 'Translated labels must be bounded and safe.');
Assert::same([false, false, false, true], array_column($items, 'custom'), 'Custom entity metadata changed.');
Assert::same(1, $access->required, 'Catalogue access must be checked.');
Assert::isFalse(in_array('../Contact', $metadata->definitionChecks, true), 'Malformed scope names must be rejected before entity-definition lookup.');
$service->requireEligible('Contact');
Assert::throws(
    fn () => $service->requireEligible('NoReadRecord'),
    PermissionDenied::class,
    'Server-side source selection must reject an unreadable entity.',
);
Assert::same(3, $access->required, 'Every server-side eligibility check must re-check catalogue access.');

$service->get();
Assert::same(4, $access->required, 'Access must still be checked before a request-scoped cache hit.');

$restricted = new EntityCatalogueService(
    $metadata,
    new Phase24Access(['Contact']),
    new Phase24Policy(),
    new Phase24Labels(),
);
Assert::same(
    ['Contact'],
    array_map(static fn ($item): string => $item->entityType, $restricted->get()),
    'A catalogue cache must not cross ACL contexts.',
);

echo "Phase 24 metadata, policy, ACL, translation, custom-entity, and cache-isolation tests passed.\n";
