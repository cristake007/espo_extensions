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
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\LayoutProcessorProvider;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\RevisionConflict;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CanonicalSerializer;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CapabilityRegistry;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Error\InvalidLayout;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutMigrator;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutNormalizer;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutParser;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutProcessor;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutValidator;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Node\NodeRegistry;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\DataSourcePublicationValidator;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\MediaPublicationValidator;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\NoopMediaPublicationValidator;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\NoopVariablePublicationValidator;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\Phase13DataSourcePublicationValidator;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\PublicationActor;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\PublicationBlockerCategory;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\PublicationConflict;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\PublicationRecordAccess;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\PublicationRequest;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\PublicationResult;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\PublicationService;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\PublicationStore;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\PublicationValidationContext;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\PublicationValidationException;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\PublicationValidationService;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\VariablePublicationValidator;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\PermissionDenied;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\TemplateVersion\TemplateVersionSnapshot;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\TemplateVersion\TemplateVersionSnapshotFactory;
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
        'Source/EntitySourceDescriptor.php', 'Source/SpreadsheetFormat.php',
        'Source/SpreadsheetSourceDescriptor.php',
        'Node/NodeKind.php', 'Node/NodeDefinition.php', 'Node/UnknownNodeType.php', 'Node/NodeRegistry.php',
        'LayoutDefaults.php', 'ValidationError.php', 'ValidationResult.php',
        'Error/LayoutProcessingException.php', 'Error/LayoutTooLarge.php', 'Error/MalformedLayout.php',
        'Error/UnsupportedSchemaVersion.php', 'Error/InvalidLayout.php', 'Migration/LayoutMigration.php',
        'LayoutParser.php', 'LayoutMigrator.php', 'RichTextSanitizer.php', 'LayoutNormalizer.php', 'LayoutValidator.php',
        'CanonicalSerializer.php', 'ProcessedLayout.php', 'LayoutProcessor.php',
    ] as $relativePath) {
        require "$layoutRoot/$relativePath";
    }

    foreach (['LayoutProcessorProvider.php', 'RevisionConflict.php'] as $relativePath) {
        require "$moduleRoot/Tools/DocumentBuilder/Draft/$relativePath";
    }

    foreach (['TemplateVersionSnapshot.php', 'TemplateVersionSnapshotFactory.php'] as $relativePath) {
        require "$moduleRoot/Tools/DocumentBuilder/TemplateVersion/$relativePath";
    }

    foreach ([
        'PublicationRequest.php', 'PublicationResult.php', 'PublicationConflict.php',
        'PublicationActor.php', 'PublicationRecordAccess.php', 'PublicationStore.php',
        'PublicationBlockerCategory.php', 'PublicationValidationException.php',
        'PublicationValidationContext.php', 'DataSourcePublicationValidator.php',
        'MediaPublicationValidator.php', 'VariablePublicationValidator.php',
        'Phase13DataSourcePublicationValidator.php', 'NoopMediaPublicationValidator.php',
        'NoopVariablePublicationValidator.php', 'PublicationValidationService.php',
        'PublicationService.php',
    ] as $relativePath) {
        require "$moduleRoot/Tools/DocumentBuilder/Publication/$relativePath";
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

    final class Phase13Entity implements Entity
    {
        /** @param array<string, mixed> $values */
        public function __construct(public array $values) {}
        public function get(string $field): mixed { return $this->values[$field] ?? null; }
        public function setMultiple(array $values): void { $this->values = array_replace($this->values, $values); }
    }

    final class Phase13Store implements PublicationStore
    {
        /** @param list<array<string, mixed>> $versions */
        public function __construct(
            public Phase13Entity $template,
            public array $versions = [],
            public bool $failAfterCurrentSwitch = false,
        ) {}

        public function publishLocked(string $templateId, callable $snapshotFactory): PublicationResult
        {
            $templateBefore = $this->template->values;
            $versionsBefore = $this->versions;

            try {
                $numbers = array_column($this->versions, 'versionNumber');
                $nextNumber = $numbers === [] ? 1 : max($numbers) + 1;
                $snapshot = $snapshotFactory($this->template, $nextNumber, ['team-b', 'team-a']);

                foreach ($this->versions as &$version) {
                    $version['isCurrent'] = false;
                }
                unset($version);

                if ($this->failAfterCurrentSwitch) {
                    throw new RuntimeException('Simulated persistence failure.');
                }

                $attributes = $snapshot->attributes();
                $versionId = 'version-' . $nextNumber;
                $attributes['id'] = $versionId;
                $this->versions[] = $attributes;
                $this->template->setMultiple([
                    'status' => 'Published',
                    'currentPublishedVersionId' => $versionId,
                    'isActive' => true,
                ]);

                return new PublicationResult(
                    $templateId,
                    $versionId,
                    $nextNumber,
                    $snapshot->checksum(),
                    $attributes['publishedAt'],
                );
            } catch (Throwable $exception) {
                $this->template->values = $templateBefore;
                $this->versions = $versionsBefore;
                throw $exception;
            }
        }
    }

    final class Phase13Access implements PublicationRecordAccess
    {
        public function __construct(public bool $allowed = true) {}
        public function requirePublish(Entity $template): void
        {
            if (!$this->allowed) {
                throw new PermissionDenied();
            }
        }
    }

    $actor = new class implements PublicationActor {
        public function id(): string { return 'publisher-1'; }
        public function publishedAt(): DateTimeImmutable
        {
            return new DateTimeImmutable('2026-07-19 10:00:00', new DateTimeZone('UTC'));
        }
    };

    $validation = static function (
        ?MediaPublicationValidator $media = null,
        ?VariablePublicationValidator $variable = null,
    ): PublicationValidationService {
        return new PublicationValidationService(
            new Phase13DataSourcePublicationValidator(),
            $media ?? new NoopMediaPublicationValidator(),
            $variable ?? new NoopVariablePublicationValidator(),
        );
    };

    $makeService = static function (
        Phase13Store $store,
        Phase13Access $access,
        PublicationValidationService $publicationValidation,
    ) use ($provider, $actor): PublicationService {
        return new PublicationService(
            $store,
            $access,
            $provider,
            $publicationValidation,
            new TemplateVersionSnapshotFactory(new CanonicalSerializer()),
            $actor,
        );
    };

    $default = (new FixtureLoader($extensionRoot . '/tests/fixtures'))->json('layout/phase-08-default.json');
    $template = new Phase13Entity([
        'name' => 'Contract',
        'status' => 'Draft',
        'revision' => 7,
        'currentDraftLayout' => $default,
        'draftChangeNote' => 'Approved draft',
        'sourceType' => 'none',
        'entityType' => null,
        'assignedUserId' => 'user-1',
        'isActive' => true,
    ]);
    $priorLayout = ['schemaVersion' => 1, 'immutable' => true];
    $store = new Phase13Store($template, [[
        'id' => 'version-1',
        'versionNumber' => 1,
        'layoutSnapshot' => $priorLayout,
        'checksum' => str_repeat('a', 64),
        'isCurrent' => true,
    ]]);
    $access = new Phase13Access();
    $service = $makeService($store, $access, $validation());

    $result = $service->publish('template-1', new PublicationRequest(7));
    Assert::same(2, $result->versionNumber, 'Publication must allocate the next version number under the lock.');
    Assert::same('Published', $template->values['status'], 'Successful publication must activate the template.');
    Assert::same('version-2', $template->values['currentPublishedVersionId'], 'The template must point at the new version.');
    Assert::same(1, count(array_filter($store->versions, static fn (array $v): bool => $v['isCurrent'])), 'Exactly one version must be current.');
    Assert::same($priorLayout, $store->versions[0]['layoutSnapshot'], 'Prior version snapshot content must remain immutable.');
    Assert::same(str_repeat('a', 64), $store->versions[0]['checksum'], 'Prior version checksums must remain immutable.');
    Assert::same('Approved draft', $store->versions[1]['changeNote'], 'The draft change note must be captured in the version.');
    Assert::same(['team-a', 'team-b'], $store->versions[1]['teamsIds'], 'Publication-time teams must be normalized in the snapshot.');
    Assert::same(64, strlen($result->checksum), 'A published version must expose its SHA-256 checksum.');

    Assert::throws(
        fn () => $service->publish('template-1', new PublicationRequest(7)),
        PublicationConflict::class,
        'A serialized concurrent publisher must not publish an already activated draft.',
    );

    $freshTemplate = static fn (array $overrides = []): Phase13Entity => new Phase13Entity(array_replace([
        'name' => 'Contract', 'status' => 'Draft', 'revision' => 7,
        'currentDraftLayout' => $default, 'draftChangeNote' => null,
        'sourceType' => 'none', 'entityType' => null, 'assignedUserId' => 'user-1',
    ], $overrides));

    $deniedAccess = new Phase13Access(false);
    Assert::throws(
        fn () => $makeService(new Phase13Store($freshTemplate()), $deniedAccess, $validation())
            ->publish('template-1', new PublicationRequest(7)),
        PermissionDenied::class,
        'Publication requires both the publish action and editable record access.',
    );

    Assert::throws(
        fn () => $makeService(new Phase13Store($freshTemplate()), new Phase13Access(), $validation())
            ->publish('template-1', new PublicationRequest(6)),
        RevisionConflict::class,
        'Publication must reject a stale draft revision.',
    );

    $entityLayout = $default;
    $entityLayout['dataSource'] = ['type' => 'entity', 'entityType' => 'Contact', 'relationshipDepth' => 2];
    $capabilityTemplate = $freshTemplate([
        'currentDraftLayout' => $entityLayout,
        'sourceType' => 'entity',
        'entityType' => 'Contact',
    ]);

    try {
        $makeService(new Phase13Store($capabilityTemplate), new Phase13Access(), $validation())
            ->publish('template-1', new PublicationRequest(7));
        throw new RuntimeException('Expected capability publication blocker was not raised.');
    } catch (PublicationValidationException $exception) {
        Assert::same(PublicationBlockerCategory::Capability, $exception->category, 'Schema-only sources must fail closed as capability blockers.');
    }

    try {
        $makeService(
            new Phase13Store($freshTemplate(['sourceType' => 'entity'])),
            new Phase13Access(),
            $validation(),
        )->publish('template-1', new PublicationRequest(7));
        throw new RuntimeException('Expected data-source publication blocker was not raised.');
    } catch (PublicationValidationException $exception) {
        Assert::same(PublicationBlockerCategory::DataSource, $exception->category, 'Source summary mismatches must block publication.');
    }

    $mediaBlocker = new class implements MediaPublicationValidator {
        public function validate(PublicationValidationContext $context): void
        {
            throw new PublicationValidationException(PublicationBlockerCategory::Media, 'media.invalid');
        }
    };
    Assert::throws(
        fn () => $makeService(
            new Phase13Store($freshTemplate()),
            new Phase13Access(),
            $validation($mediaBlocker),
        )->publish('template-1', new PublicationRequest(7)),
        PublicationValidationException::class,
        'Media validation blockers must abort publication.',
    );

    $variableBlocker = new class implements VariablePublicationValidator {
        public function validate(PublicationValidationContext $context): void
        {
            throw new PublicationValidationException(PublicationBlockerCategory::Variable, 'variable.unresolved');
        }
    };
    Assert::throws(
        fn () => $makeService(
            new Phase13Store($freshTemplate()),
            new Phase13Access(),
            $validation(variable: $variableBlocker),
        )->publish('template-1', new PublicationRequest(7)),
        PublicationValidationException::class,
        'Variable validation blockers must abort publication.',
    );

    $invalidLayout = $default;
    $invalidLayout['unexpected'] = true;
    Assert::throws(
        fn () => $makeService(
            new Phase13Store($freshTemplate(['currentDraftLayout' => $invalidLayout])),
            new Phase13Access(),
            $validation(),
        )->publish('template-1', new PublicationRequest(7)),
        InvalidLayout::class,
        'Layout validation blockers must abort publication.',
    );

    $rollbackTemplate = $freshTemplate();
    $rollbackStore = new Phase13Store($rollbackTemplate, [[
        'id' => 'version-1', 'versionNumber' => 1, 'layoutSnapshot' => $priorLayout,
        'checksum' => str_repeat('a', 64), 'isCurrent' => true,
    ]], true);
    Assert::throws(
        fn () => $makeService($rollbackStore, new Phase13Access(), $validation())
            ->publish('template-1', new PublicationRequest(7)),
        RuntimeException::class,
        'Persistence failure must abort publication.',
    );
    Assert::same('Draft', $rollbackTemplate->values['status'], 'Rollback must preserve draft lifecycle state.');
    Assert::same(1, count($rollbackStore->versions), 'Rollback must not retain a partial new version.');
    Assert::same(true, $rollbackStore->versions[0]['isCurrent'], 'Rollback must restore the prior current marker.');

    echo "Phase 13 publication service tests passed.\n";
}
