<?php

declare(strict_types=1);

namespace Espo\ORM {
    interface Entity
    {
        public function get(string $field): mixed;
        /** @param array<string, mixed> $values */
        public function setMultiple(array $values): void;
    }
}

namespace {
    use DocumentBuilder\Tests\Support\Assert;
    use DocumentBuilder\Tests\Support\FixtureLoader;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\Settings;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\DraftRecordAccess;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\DraftSaveRequest;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\DraftSaveService;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\DraftTemplateStore;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\LayoutProcessorProvider;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\RevisionConflict;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\SourceChangeConfirmationRequired;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\SourceReferenceImpactAnalyzer;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\TemplateNotDraft;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\UnresolvedSourceReference;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CanonicalSerializer;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CapabilityRegistry;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Error\MalformedLayout;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutMigrator;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutNormalizer;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutParser;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutProcessor;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutValidator;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Node\NodeRegistry;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\PermissionDenied;
    use Espo\ORM\Entity;

    require dirname(__DIR__) . '/bootstrap.php';

    $extensionRoot = dirname(__DIR__, 2);
    $moduleRoot = $extensionRoot . '/files/custom/Espo/Modules/DocumentBuilder';
    $layoutRoot = "$moduleRoot/Tools/DocumentBuilder/Layout";

    require "$moduleRoot/Tools/DocumentBuilder/Config/Settings.php";

    foreach ([
        'SchemaVersion.php', 'StableId.php', 'Unit.php', 'Measurement.php', 'NestingDepth.php',
        'Capability.php', 'CapabilityStatus.php', 'CapabilityUnavailable.php',
        'CapabilityNotPublishable.php', 'CapabilityRegistry.php', 'EmptyJsonObject.php',
        'Source/SourceType.php', 'Source/SourceDescriptor.php', 'Source/NoSourceDescriptor.php',
        'Node/NodeKind.php', 'Node/NodeDefinition.php', 'Node/UnknownNodeType.php', 'Node/NodeRegistry.php',
        'LayoutDefaults.php', 'ValidationError.php', 'ValidationResult.php',
        'Error/LayoutProcessingException.php', 'Error/LayoutTooLarge.php', 'Error/MalformedLayout.php',
        'Error/UnsupportedSchemaVersion.php', 'Error/InvalidLayout.php', 'Migration/LayoutMigration.php',
        'LayoutParser.php', 'LayoutMigrator.php', 'LayoutNormalizer.php', 'LayoutValidator.php',
        'CanonicalSerializer.php', 'ProcessedLayout.php', 'LayoutProcessor.php',
    ] as $relativePath) {
        require "$layoutRoot/$relativePath";
    }

    foreach ([
        'DraftSaveRequest.php', 'DraftSaveResult.php', 'RevisionConflict.php', 'TemplateNotDraft.php',
        'UnresolvedSourceReference.php', 'SourceChangeImpactReport.php', 'SourceChangeConfirmationRequired.php',
        'DraftTemplateStore.php', 'DraftRecordAccess.php', 'SourceReferenceImpactAnalyzer.php',
        'LayoutProcessorProvider.php', 'DraftSaveService.php',
    ] as $relativePath) {
        require "$moduleRoot/Tools/DocumentBuilder/Draft/$relativePath";
    }
    require "$moduleRoot/Tools/DocumentBuilder/Security/PermissionDenied.php";

    $settings = new Settings([
        'maxRelationshipDepth' => 2,
        'enabledSourceEntityTypeList' => [],
        'disabledSourceEntityTypeList' => [],
        'maxLayoutBytes' => 1048576,
        'maxElements' => 500,
        'maxNestingDepth' => 8,
        'maxSections' => 100,
        'allowedFontList' => ['DejaVu Sans'],
        'defaultFont' => 'DejaVu Sans',
        'defaultLocale' => 'en_US',
        'defaultPageSize' => 'A4',
    ]);
    $processor = new LayoutProcessor(
        new LayoutParser($settings),
        new LayoutMigrator(),
        new LayoutNormalizer($settings),
        new LayoutValidator($settings, new NodeRegistry(), CapabilityRegistry::phase08()),
        new CanonicalSerializer(),
    );
    $provider = new class($processor) implements LayoutProcessorProvider {
        public function __construct(private LayoutProcessor $processor) {}
        public function get(): LayoutProcessor { return $this->processor; }
    };

    final class Phase12Entity implements Entity
    {
        /** @param array<string, mixed> $values */
        public function __construct(public array $values) {}
        public function get(string $field): mixed { return $this->values[$field] ?? null; }
        public function setMultiple(array $values): void { $this->values = array_replace($this->values, $values); }
    }

    final class Phase12Store implements DraftTemplateStore
    {
        public int $saveCount = 0;
        public function __construct(public Phase12Entity $entity) {}
        public function updateLocked(string $templateId, callable $updater): mixed
        {
            $before = $this->entity->values;

            try {
                $result = $updater($this->entity);
                ++$this->saveCount;
                return $result;
            } catch (Throwable $exception) {
                $this->entity->values = $before;
                throw $exception;
            }
        }
    }

    final class Phase12Access implements DraftRecordAccess
    {
        public function __construct(public bool $allowed = true, public bool $spreadsheetAllowed = true) {}
        public function requireEdit(Entity $template): void
        {
            if (!$this->allowed) {
                throw new PermissionDenied();
            }
        }
        public function requireSpreadsheetSource(): void
        {
            if (!$this->spreadsheetAllowed) {
                throw new PermissionDenied();
            }
        }
    }

    final class Phase12ImpactAnalyzer implements SourceReferenceImpactAnalyzer
    {
        /** @var list<UnresolvedSourceReference> */
        public array $references = [];
        public function analyze(array $currentLayout, array $nextLayout): array { return $this->references; }
    }

    $default = (new FixtureLoader($extensionRoot . '/tests/fixtures'))->json('layout/phase-08-default.json');
    $entity = new Phase12Entity([
        'status' => 'Draft',
        'revision' => 0,
        'currentDraftLayout' => $default,
        'spreadsheetSchema' => new stdClass(),
    ]);
    $store = new Phase12Store($entity);
    $access = new Phase12Access();
    $impactAnalyzer = new Phase12ImpactAnalyzer();
    $service = new DraftSaveService($store, $access, $provider, $impactAnalyzer, new CanonicalSerializer());

    $first = $service->save('template-1', new DraftSaveRequest('{"schemaVersion":1,"document":{}}', 0, changeNote: ' First save '));
    Assert::same(1, $first->revision, 'First draft save must increment revision to one.');
    Assert::same($default, $first->layout, 'Draft save must persist Phase 09 normalized layout.');
    Assert::same('First save', $entity->values['draftChangeNote'] ?? null, 'Normalized change note was not persisted.');
    Assert::same('none', $entity->values['sourceType'] ?? null, 'Source summary must follow normalized layout.');

    Assert::throws(
        fn () => $service->save('template-1', new DraftSaveRequest('{"schemaVersion":1,"document":{}}', 0)),
        RevisionConflict::class,
        'A stale draft save must fail under the row lock.',
    );
    Assert::same(1, $entity->values['revision'], 'A stale save must not mutate the draft.');

    $retry = $service->save('template-1', new DraftSaveRequest('{"schemaVersion":1,"document":{}}', 1));
    Assert::same(2, $retry->revision, 'A reload/retry with the current revision must succeed.');
    Assert::throws(
        fn () => $service->save('template-1', new DraftSaveRequest('{', 2)),
        MalformedLayout::class,
        'Malformed schema input must be rejected by Phase 09 processing.',
    );

    $entityLayout = $default;
    $entityLayout['dataSource'] = ['type' => 'entity', 'entityType' => 'Contact', 'relationshipDepth' => 2];
    $impactAnalyzer->references = [new UnresolvedSourceReference('variableOne', '/sections[id=sectionOne]/value')];
    $switchRequest = new DraftSaveRequest(json_encode($entityLayout, JSON_THROW_ON_ERROR), 2);

    try {
        $service->save('template-1', $switchRequest);
        throw new RuntimeException('Expected source confirmation was not requested.');
    } catch (SourceChangeConfirmationRequired $exception) {
        Assert::same(
            [['id' => 'variableOne', 'path' => '/sections[id=sectionOne]/value']],
            $exception->impactReport->toArray()['unresolvedReferences'],
            'Source-switch impact must report every analyzer reference.',
        );
    }
    Assert::same(2, $entity->values['revision'], 'An unconfirmed source change must not persist.');

    $confirmed = $service->save(
        'template-1',
        new DraftSaveRequest(json_encode($entityLayout, JSON_THROW_ON_ERROR), 2, true, 'Entity source'),
    );
    Assert::same(3, $confirmed->revision, 'A confirmed source switch must increment revision once.');
    Assert::same('entity', $entity->values['sourceType'] ?? null, 'Entity source summary was not persisted.');
    Assert::same('Contact', $entity->values['entityType'] ?? null, 'Entity type summary was not persisted.');
    Assert::same(
        [['id' => 'variableOne', 'path' => '/sections[id=sectionOne]/value']],
        $confirmed->toArray()['sourceChangeImpact']['unresolvedReferences'] ?? null,
        'Confirmed source switches must retain the reviewed impact report in the success response.',
    );

    $noSource = $default;
    $impactAnalyzer->references = [];
    Assert::throws(
        fn () => $service->save('template-1', new DraftSaveRequest(json_encode($noSource, JSON_THROW_ON_ERROR), 3)),
        SourceChangeConfirmationRequired::class,
        'Even a source switch with no current references requires explicit confirmation.',
    );

    $spreadsheetLayout = $default;
    $spreadsheetLayout['dataSource'] = ['type' => 'spreadsheet', 'format' => 'csv'];
    $access->spreadsheetAllowed = false;
    Assert::throws(
        fn () => $service->save(
            'template-1',
            new DraftSaveRequest(json_encode($spreadsheetLayout, JSON_THROW_ON_ERROR), 3, true),
        ),
        PermissionDenied::class,
        'Spreadsheet source selection requires the dedicated import permission.',
    );
    $access->spreadsheetAllowed = true;

    $access->allowed = false;
    Assert::throws(
        fn () => $service->save('template-1', new DraftSaveRequest(json_encode($entityLayout, JSON_THROW_ON_ERROR), 3)),
        PermissionDenied::class,
        'Unauthorized edits must be rejected before mutation.',
    );
    $access->allowed = true;
    $entity->values['status'] = 'Published';
    Assert::throws(
        fn () => $service->save('template-1', new DraftSaveRequest(json_encode($entityLayout, JSON_THROW_ON_ERROR), 3)),
        TemplateNotDraft::class,
        'Published or archived templates must not accept draft saves.',
    );

    echo "Phase 12 draft save service tests passed.\n";
}
