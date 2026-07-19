<?php

declare(strict_types=1);

namespace Espo\ORM {
    interface Entity
    {
        public function getEntityType(): string;
        public function get(string $name): mixed;
    }
}

namespace {
    use DocumentBuilder\Tests\Support\Assert;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityCatalogueMetadata;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityFieldPolicy;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\DirectEntityQueryPlanner;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\DirectEntityResolver;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\DirectVariableCollector;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\EntityRecordReader;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\EntityResolutionAccess;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\EntityValueMapper;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableIdentity;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValueState;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\PermissionDenied;
    use Espo\ORM\Entity;

    require dirname(__DIR__) . '/bootstrap.php';
    $root = dirname(__DIR__, 2);
    $module = "$root/files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder";

    foreach ([
        'Security/PermissionDenied.php',
        'DataSource/Variable/VariableSource.php',
        'DataSource/Variable/VariableType.php',
        'DataSource/Variable/VariableUsage.php',
        'DataSource/Variable/VariablePath.php',
        'DataSource/Variable/VariableIdentity.php',
        'DataSource/Variable/VariableValueState.php',
        'DataSource/Variable/VariableValueType.php',
        'DataSource/Variable/VariableValue.php',
        'DataSource/EntityCatalogue/EntityCatalogueMetadata.php',
        'DataSource/EntityCatalogue/EntityFieldPolicy.php',
        'DataSource/EntityResolver/EntityResolver.php',
        'DataSource/EntityResolver/EntityResolutionAccess.php',
        'DataSource/EntityResolver/EntityRecordReader.php',
        'DataSource/EntityResolver/DirectVariableReference.php',
        'DataSource/EntityResolver/DirectVariableCollector.php',
        'DataSource/EntityResolver/DirectFieldResolution.php',
        'DataSource/EntityResolver/DirectEntityQueryPlan.php',
        'DataSource/EntityResolver/DirectEntityQueryPlanner.php',
        'DataSource/EntityResolver/SourceProvenance.php',
        'DataSource/EntityResolver/ResolvedEntityValue.php',
        'DataSource/EntityResolver/EntityResolutionResult.php',
        'DataSource/EntityResolver/EntityValueMapper.php',
        'DataSource/EntityResolver/DirectEntityResolver.php',
    ] as $file) {
        require "$module/$file";
    }

    final readonly class TestEntity implements Entity
    {
        /** @param array<string, mixed> $values */
        public function __construct(private string $type, private array $values)
        {}

        public function getEntityType(): string
        {
            return $this->type;
        }

        public function get(string $name): mixed
        {
            return $this->values[$name] ?? null;
        }
    }

    final class TestAccess implements EntityResolutionAccess
    {
        public bool $scope = true;
        public bool $record = true;
        /** @var array<string, bool> */
        public array $fields = [];

        public function canReadScope(string $entityType): bool
        {
            return $this->scope;
        }

        public function canReadRecord(Entity $record): bool
        {
            return $this->record;
        }

        public function canReadField(string $entityType, string $field): bool
        {
            return $this->fields[$field] ?? true;
        }

        public function canReadLink(string $entityType, string $link): bool
        {
            return true;
        }
    }

    final class TestReader implements EntityRecordReader
    {
        public ?Entity $record = null;
        public int $calls = 0;
        /** @var list<string> */
        public array $selected = [];

        public function find(string $entityType, string $recordId, array $fields): ?Entity
        {
            $this->calls++;
            $this->selected = $fields;

            return $this->record;
        }
    }

    final readonly class TestMetadata implements EntityCatalogueMetadata
    {
        /** @param array<string, array<string, mixed>> $entities */
        public function __construct(private array $entities)
        {}

        public function scopeDefinitions(): array
        {
            return [];
        }

        public function hasEntityDefinition(string $entityType): bool
        {
            return isset($this->entities[$entityType]);
        }

        public function isCustom(string $entityType): bool
        {
            return str_starts_with($entityType, 'C');
        }

        public function entityDefinition(string $entityType): array
        {
            return $this->entities[$entityType] ?? [];
        }
    }

    $metadata = new TestMetadata([
        'Contact' => ['fields' => [
            'name' => ['type' => 'varchar'],
            'customCode' => ['type' => 'varchar', 'custom' => true],
            'budget' => ['type' => 'currency'],
            'privateNote' => ['type' => 'text'],
            'unusedField' => ['type' => 'varchar'],
        ]],
        'Account' => ['fields' => ['name' => ['type' => 'varchar']]],
        'CourseSession' => ['fields' => ['customLabel' => ['type' => 'varchar', 'custom' => true]]],
    ]);
    $access = new TestAccess();
    $access->fields['privateNote'] = false;
    $reader = new TestReader();
    $reader->record = new TestEntity('Contact', [
        'name' => 'Ana Popescu',
        'customCode' => 'C-42',
        'budget' => '1250.50',
        'budgetCurrency' => 'RON',
        'privateNote' => 'must never leave the resolver',
        'unusedField' => 'must never be selected',
    ]);
    $resolver = new DirectEntityResolver(
        new DirectVariableCollector(),
        new DirectEntityQueryPlanner($metadata, new EntityFieldPolicy(), $access),
        $reader,
        $access,
        new EntityValueMapper(),
    );
    $identity = static fn (string $field, string $entity = 'Contact'): array => [
        'source' => 'entity', 'type' => 'direct', 'entityType' => $entity, 'path' => [$field],
    ];
    $token = static fn (string $field): array => [
        'type' => 'variable', 'identity' => $identity($field),
    ];
    $layout = [
        'dataSource' => ['type' => 'entity', 'entityType' => 'Contact'],
        'sections' => [[
            'content' => [$token('name'), $token('customCode'), $token('budget'), $token('privateNote'), $token('name')],
        ]],
    ];
    $result = $resolver->resolve($layout, 'contact_1');

    Assert::same(1, $reader->calls, 'Direct fields must use one bounded record read.');
    Assert::same(
        ['id', 'name', 'customCode', 'budget', 'budgetCurrency'],
        $reader->selected,
        'Unused, duplicate, or field-denied attributes were selected.',
    );
    Assert::same(4, count($result->values), 'Duplicate direct identities were not coalesced.');
    Assert::same('Ana Popescu', $result->find(VariableIdentity::fromArray($identity('name')))?->value->value, 'Contact text did not resolve.');
    Assert::same('C-42', $result->find(VariableIdentity::fromArray($identity('customCode')))?->value->value, 'A custom field did not resolve.');
    Assert::same(
        ['amount' => 1250.5, 'currency' => 'RON'],
        $result->find(VariableIdentity::fromArray($identity('budget')))?->value->value,
        'Currency provenance fields did not resolve together.',
    );
    $restricted = $result->find(VariableIdentity::fromArray($identity('privateNote')));
    Assert::same(VariableValueState::Forbidden, $restricted?->value->state, 'Field ACL denial must return a forbidden marker.');
    Assert::same(null, $restricted?->value->value, 'A forbidden marker leaked its field value.');
    Assert::same(
        ['source' => 'entity', 'entityType' => 'Contact', 'recordId' => 'contact_1', 'field' => 'name'],
        $result->find(VariableIdentity::fromArray($identity('name')))?->provenance->toArray(),
        'Direct source provenance changed.',
    );

    $access->record = false;
    $denied = $resolver->resolve($layout, 'contact_1');
    foreach ($denied->values as $value) {
        Assert::same(VariableValueState::Forbidden, $value->value->state, 'Record ACL denial leaked a distinguishable value.');
        Assert::same(null, $value->value->value, 'Record ACL denial leaked raw data.');
    }

    $access->record = true;
    $reader->record = null;
    $missing = $resolver->resolve($layout, 'deleted_contact');
    Assert::same(VariableValueState::Missing, $missing->find(VariableIdentity::fromArray($identity('name')))?->value->state, 'A missing or deleted record must resolve as missing.');

    $callsBeforeScopeDenial = $reader->calls;
    $access->scope = false;
    Assert::throws(fn () => $resolver->resolve($layout, 'contact_1'), PermissionDenied::class, 'Scope denial was not enforced before querying.');
    Assert::same($callsBeforeScopeDenial, $reader->calls, 'A scope-denied source record was queried.');
    $access->scope = true;

    $callsBeforeEmptyLayout = $reader->calls;
    $emptyResult = $resolver->resolve([
        'dataSource' => ['type' => 'entity', 'entityType' => 'Contact'],
        'sections' => [],
    ], 'contact_1');
    Assert::same([], $emptyResult->values, 'A layout without direct variables returned entity data.');
    Assert::same($callsBeforeEmptyLayout, $reader->calls, 'A layout without direct variables queried the source record.');

    $invalid = $layout;
    $invalid['sections'][0]['content'][0]['identity']['path'] = ['../secret'];
    Assert::throws(fn () => $resolver->resolve($invalid, 'contact_1'), InvalidArgumentException::class, 'A crafted client path was accepted.');

    foreach ([
        ['Account', 'name', 'Account SRL'],
        ['CourseSession', 'customLabel', 'Grupa iulie'],
    ] as [$entityType, $field, $expected]) {
        $reader->record = new TestEntity($entityType, [$field => $expected]);
        $otherLayout = [
            'dataSource' => ['type' => 'entity', 'entityType' => $entityType],
            'sections' => [['content' => [[
                'type' => 'variable', 'identity' => $identity($field, $entityType),
            ]]]],
        ];
        $otherResult = $resolver->resolve($otherLayout, 'record_2');
        Assert::same($expected, $otherResult->values[0]->value->value, "$entityType direct resolution failed.");
    }

    echo "Phase 28 bounded direct entity resolution and ACL tests passed.\n";
}
