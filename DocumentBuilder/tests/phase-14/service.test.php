<?php

declare(strict_types=1);

namespace Espo\ORM {
    interface Entity
    {
        public function get(string $field): mixed;
        /** @param array<string, mixed> $values */
        public function setMultiple(array $values): void;
        public function getId(): ?string;
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
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutMigrator;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutNormalizer;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutParser;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutProcessor;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\LayoutValidator;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Node\NodeRegistry;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle\DraftFromVersionRequest;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle\DuplicateTemplateRequest;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle\TemplateDuplicateData;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle\TemplateLifecycleAccess;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle\TemplateLifecycleConflict;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle\TemplateLifecycleRequest;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle\TemplateLifecycleResult;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle\TemplateLifecycleService;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle\TemplateLifecycleStore;
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
        'LayoutParser.php', 'LayoutMigrator.php', 'RichTextSanitizer.php', 'LayoutNormalizer.php', 'LayoutValidator.php',
        'CanonicalSerializer.php', 'ProcessedLayout.php', 'LayoutProcessor.php',
    ] as $relativePath) {
        require "$layoutRoot/$relativePath";
    }

    foreach (['LayoutProcessorProvider.php', 'RevisionConflict.php'] as $relativePath) {
        require "$moduleRoot/Tools/DocumentBuilder/Draft/$relativePath";
    }

    foreach ([
        'TemplateLifecycleAccess.php', 'TemplateLifecycleConflict.php',
        'TemplateLifecycleRequest.php', 'DuplicateTemplateRequest.php',
        'DraftFromVersionRequest.php', 'TemplateDuplicateData.php',
        'TemplateLifecycleResult.php', 'TemplateLifecycleStore.php',
        'TemplateLifecycleService.php',
    ] as $relativePath) {
        require "$moduleRoot/Tools/DocumentBuilder/Lifecycle/$relativePath";
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

    final class Phase14Entity implements Entity
    {
        /** @param array<string, mixed> $values */
        public function __construct(public string $id, public array $values) {}
        public function get(string $field): mixed { return $this->values[$field] ?? null; }
        public function setMultiple(array $values): void { $this->values = array_replace($this->values, $values); }
        public function getId(): ?string { return $this->id; }
    }

    final class Phase14Store implements TemplateLifecycleStore
    {
        /** @var array<string, Phase14Entity> */
        public array $templates;
        /** @var array<string, Phase14Entity> */
        public array $versions;
        /** @var array<string, list<string>> */
        public array $teamIds;
        public bool $failAfterMutation = false;

        /**
         * @param list<Phase14Entity> $templates
         * @param list<Phase14Entity> $versions
         * @param array<string, list<string>> $teamIds
         */
        public function __construct(array $templates, array $versions, array $teamIds)
        {
            foreach ($templates as $template) {
                $this->templates[$template->id] = $template;
            }
            foreach ($versions as $version) {
                $this->versions[$version->id] = $version;
            }
            $this->teamIds = $teamIds;
        }

        public function duplicateLocked(string $templateId, callable $duplicator): TemplateLifecycleResult
        {
            $source = $this->templates[$templateId];
            $data = $duplicator($source, $this->teamIds[$templateId] ?? []);
            $copyId = 'template-copy';
            $this->templates[$copyId] = new Phase14Entity($copyId, $data->attributes);
            $this->teamIds[$copyId] = $data->teamIds;

            return new TemplateLifecycleResult('duplicate', $copyId, 'Draft', 0);
        }

        public function updateLocked(string $templateId, callable $updater): TemplateLifecycleResult
        {
            $before = $this->templates[$templateId]->values;

            try {
                $result = $updater($this->templates[$templateId]);

                if ($this->failAfterMutation) {
                    throw new RuntimeException('Simulated transaction failure.');
                }

                return $result;
            } catch (Throwable $exception) {
                $this->templates[$templateId]->values = $before;
                throw $exception;
            }
        }

        public function restoreLocked(
            string $templateId,
            string $versionId,
            callable $templateAuthorizer,
            callable $restorer,
        ): TemplateLifecycleResult {
            $templateAuthorizer($this->templates[$templateId]);
            $version = $this->versions[$versionId] ?? null;

            if ($version === null || $version->get('templateId') !== $templateId) {
                throw new RuntimeException('Version not found for template.');
            }

            return $this->updateLocked(
                $templateId,
                fn (Entity $template): TemplateLifecycleResult => $restorer($template, $version),
            );
        }
    }

    final class Phase14Access implements TemplateLifecycleAccess
    {
        public bool $duplicateAllowed = true;
        public bool $archiveAllowed = true;
        public bool $restoreAllowed = true;

        public function requireDuplicate(Entity $template): void
        {
            if (!$this->duplicateAllowed) {
                throw new PermissionDenied();
            }
        }
        public function requireArchive(Entity $template): void
        {
            if (!$this->archiveAllowed) {
                throw new PermissionDenied();
            }
        }
        public function requireDraftFromVersionTemplate(Entity $template): void
        {
            if (!$this->restoreAllowed) {
                throw new PermissionDenied();
            }
        }
        public function requireVersionRead(Entity $version): void
        {
            if (!$this->restoreAllowed) {
                throw new PermissionDenied();
            }
        }
    }

    $default = (new FixtureLoader($extensionRoot . '/tests/fixtures'))->json('layout/phase-08-default.json');
    $templateValues = [
        'name' => 'Published Contract',
        'category' => 'Contracts',
        'description' => 'Approved layout',
        'status' => 'Published',
        'sourceType' => 'none',
        'entityType' => null,
        'spreadsheetSchema' => new stdClass(),
        'currentDraftLayout' => $default,
        'revision' => 4,
        'draftChangeNote' => 'Published note',
        'pageSize' => 'A4',
        'orientation' => 'portrait',
        'isActive' => true,
        'currentPublishedVersionId' => 'version-2',
        'assignedUserId' => 'user-1',
        'generatedDocumentsIds' => ['document-1'],
    ];
    $versionOne = new Phase14Entity('version-1', [
        'templateId' => 'template-1',
        'versionNumber' => 1,
        'layoutSnapshot' => $default,
        'sourceSnapshot' => (object) ['type' => 'none'],
        'checksum' => str_repeat('a', 64),
        'isCurrent' => false,
    ]);
    $versionTwo = new Phase14Entity('version-2', [
        'templateId' => 'template-1',
        'versionNumber' => 2,
        'layoutSnapshot' => $default,
        'sourceSnapshot' => ['type' => 'none'],
        'checksum' => str_repeat('b', 64),
        'isCurrent' => true,
    ]);
    $template = new Phase14Entity('template-1', $templateValues);
    $store = new Phase14Store([$template], [$versionOne, $versionTwo], [
        'template-1' => ['team-b', 'team-a'],
    ]);
    $access = new Phase14Access();
    $service = new TemplateLifecycleService(
        $store,
        $access,
        $provider,
        new CanonicalSerializer(),
    );

    $duplicate = $service->duplicate('template-1', new DuplicateTemplateRequest(4));
    $copy = $store->templates[$duplicate->templateId];
    Assert::same('Copy of Published Contract', $copy->values['name'], 'Duplicate naming rule changed.');
    Assert::same('Draft', $copy->values['status'], 'A duplicate must always start as a draft.');
    Assert::same(0, $copy->values['revision'], 'A duplicate must start at revision zero.');
    Assert::same($default, $copy->values['currentDraftLayout'], 'The normalized design layout must be copied.');
    Assert::same('Contracts', $copy->values['category'], 'Template category must be copied.');
    Assert::same('Approved layout', $copy->values['description'], 'Template description must be copied.');
    Assert::same(null, $copy->values['currentPublishedVersionId'], 'Published-version links must not be copied.');
    Assert::same(null, $copy->values['draftChangeNote'], 'Publication notes must not be copied into a new draft.');
    Assert::isFalse(isset($copy->values['generatedDocumentsIds']), 'Generation history must not be copied.');
    Assert::same(['team-b', 'team-a'], $store->teamIds[$copy->id], 'ACL team projection must be copied.');
    Assert::same(2, count($store->versions), 'Template duplication must not duplicate immutable versions.');

    Assert::throws(
        fn () => $service->duplicate('template-1', new DuplicateTemplateRequest(3)),
        RevisionConflict::class,
        'Duplicating stale template state must fail.',
    );
    $access->duplicateAllowed = false;
    Assert::throws(
        fn () => $service->duplicate('template-1', new DuplicateTemplateRequest(4)),
        PermissionDenied::class,
        'Duplicate requires design/read/create access.',
    );
    $access->duplicateAllowed = true;

    $historyBeforeArchive = array_map(
        static fn (Phase14Entity $version): array => $version->values,
        $store->versions,
    );
    $access->archiveAllowed = false;
    Assert::throws(
        fn () => $service->archive('template-1', new TemplateLifecycleRequest(4)),
        PermissionDenied::class,
        'Archive requires publish authority and editable record access.',
    );
    Assert::same('Published', $template->values['status'], 'Denied archive must not change lifecycle state.');
    $access->archiveAllowed = true;
    $archive = $service->archive('template-1', new TemplateLifecycleRequest(4));
    Assert::same('Archived', $archive->status, 'Archive result status changed.');
    Assert::same(false, $template->values['isActive'], 'Archived templates must prohibit new generation.');
    Assert::same('version-2', $template->values['currentPublishedVersionId'], 'Archive must preserve the current published-version link.');
    Assert::same($historyBeforeArchive, array_map(
        static fn (Phase14Entity $version): array => $version->values,
        $store->versions,
    ), 'Archive must not mutate version history.');
    Assert::throws(
        fn () => $service->archive('template-1', new TemplateLifecycleRequest(4)),
        TemplateLifecycleConflict::class,
        'Repeated archive requests must report a lifecycle conflict.',
    );

    $restoreTemplate = new Phase14Entity('template-restore', array_replace($templateValues, [
        'currentPublishedVersionId' => 'restore-version',
    ]));
    $restoreVersion = new Phase14Entity('restore-version', [
        'templateId' => 'template-restore',
        'versionNumber' => 3,
        'layoutSnapshot' => $default,
        'sourceSnapshot' => (object) ['type' => 'none'],
        'checksum' => str_repeat('c', 64),
        'isCurrent' => true,
    ]);
    $restoreStore = new Phase14Store([$restoreTemplate], [$restoreVersion], []);
    $restoreService = new TemplateLifecycleService(
        $restoreStore,
        $access,
        $provider,
        new CanonicalSerializer(),
    );
    $versionBeforeRestore = $restoreVersion->values;
    $restored = $restoreService->createDraftFromVersion(
        'template-restore',
        new DraftFromVersionRequest(4, 'restore-version'),
    );
    Assert::same('Draft', $restored->status, 'Restoring a version must put the template in Draft status.');
    Assert::same(5, $restored->revision, 'Restoring a version must increment revision exactly once.');
    Assert::same($default, $restoreTemplate->values['currentDraftLayout'], 'Version layout was not restored.');
    Assert::same('Restored from version 3.', $restoreTemplate->values['draftChangeNote'], 'Restoration provenance note is missing.');
    Assert::same('restore-version', $restoreTemplate->values['currentPublishedVersionId'], 'Draft restoration must retain the published-version link.');
    Assert::same($versionBeforeRestore, $restoreVersion->values, 'Draft restoration must not mutate immutable history.');

    Assert::throws(
        fn () => $restoreService->createDraftFromVersion(
            'template-restore',
            new DraftFromVersionRequest(4, 'restore-version'),
        ),
        RevisionConflict::class,
        'A concurrent draft restoration must fail on revision mismatch.',
    );
    $access->restoreAllowed = false;
    $anotherTemplate = new Phase14Entity('template-denied', array_replace($templateValues, [
        'currentPublishedVersionId' => 'denied-version',
    ]));
    $deniedVersion = new Phase14Entity('denied-version', [
        'templateId' => 'template-denied', 'versionNumber' => 1,
        'layoutSnapshot' => $default, 'sourceSnapshot' => ['type' => 'none'],
    ]);
    $deniedService = new TemplateLifecycleService(
        new Phase14Store([$anotherTemplate], [$deniedVersion], []),
        $access,
        $provider,
        new CanonicalSerializer(),
    );
    Assert::throws(
        fn () => $deniedService->createDraftFromVersion(
            'template-denied',
            new DraftFromVersionRequest(4, 'unknown-version'),
        ),
        PermissionDenied::class,
        'Template access must be checked before a requested version is looked up.',
    );
    Assert::throws(
        fn () => $deniedService->createDraftFromVersion(
            'template-denied',
            new DraftFromVersionRequest(4, 'denied-version'),
        ),
        PermissionDenied::class,
        'Draft restoration requires template edit and version read access.',
    );
    $access->restoreAllowed = true;

    $rollbackTemplate = new Phase14Entity('template-rollback', $templateValues);
    $rollbackStore = new Phase14Store([$rollbackTemplate], [], []);
    $rollbackStore->failAfterMutation = true;
    $rollbackService = new TemplateLifecycleService(
        $rollbackStore,
        $access,
        $provider,
        new CanonicalSerializer(),
    );
    Assert::throws(
        fn () => $rollbackService->archive(
            'template-rollback',
            new TemplateLifecycleRequest(4),
        ),
        RuntimeException::class,
        'Lifecycle persistence failure must abort the transaction.',
    );
    Assert::same('Published', $rollbackTemplate->values['status'], 'Rollback must restore lifecycle status.');
    Assert::same(true, $rollbackTemplate->values['isActive'], 'Rollback must restore generation eligibility.');

    echo "Phase 14 template lifecycle service tests passed.\n";
}
