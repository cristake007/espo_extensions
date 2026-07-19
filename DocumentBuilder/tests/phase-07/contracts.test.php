<?php

declare(strict_types=1);

namespace Espo\ORM {
    interface Entity
    {
        public function getEntityType(): string;
    }
}

namespace Espo\Core\Acl {
    final class Table
    {
        public const LEVEL_YES = 'yes';
        public const ACTION_READ = 'read';
        public const ACTION_EDIT = 'edit';
    }
}

namespace Espo\Core {
    use Espo\ORM\Entity;

    final class Acl
    {
        /** @var array<string, string> */
        public array $permissionLevels = [];

        /** @var array<string, bool> */
        public array $scopeAccess = [];

        /** @var array<string, bool> */
        public array $fieldAccess = [];

        /** @var array<string, bool> */
        public array $linkAccess = [];

        /** @var array<int, bool> */
        public array $entityAccess = [];

        /** @var list<string> */
        public array $calls = [];

        public function getPermissionLevel(string $permission): string
        {
            $this->calls[] = "permission:$permission";

            return $this->permissionLevels[$permission] ?? 'no';
        }

        public function checkScope(string $scope, ?string $action = null): bool
        {
            $this->calls[] = "scope:$scope:$action";

            return $this->scopeAccess[$scope] ?? true;
        }

        public function checkEntity(Entity $entity, string $action = 'read'): bool
        {
            $this->calls[] = 'entity:' . $entity->getEntityType() . ":$action";

            return $this->entityAccess[spl_object_id($entity)] ?? true;
        }

        public function checkField(string $scope, string $field, string $action = 'read'): bool
        {
            $this->calls[] = "field:$scope:$field:$action";

            return $this->fieldAccess["$scope.$field"] ?? true;
        }

        public function checkLink(string $scope, string $link, string $action = 'read'): bool
        {
            $this->calls[] = "link:$scope:$link:$action";

            return $this->linkAccess["$scope.$link"] ?? true;
        }
    }
}

namespace {
    use DocumentBuilder\Tests\Support\Assert;
    use DocumentBuilder\Tests\Support\FixtureLoader;
    use Espo\Core\Acl;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Audit\AuditContextSanitizer;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Audit\AuditEventCategory;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Error\ErrorCategory;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Error\PublicError;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Error\PublicWarning;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Error\WarningCode;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\ActionAccessPolicy;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\ActionPermission;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\FieldReadRequirement;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\LinkReadRequirement;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\PermissionDenied;
    use Espo\ORM\Entity;

    require dirname(__DIR__) . '/bootstrap.php';

    $extensionRoot = dirname(__DIR__, 2);
    $moduleRoot = $extensionRoot . '/files/custom/Espo/Modules/DocumentBuilder';
    $resourceLoader = new FixtureLoader($moduleRoot . '/Resources');
    $fixtureLoader = new FixtureLoader($extensionRoot . '/tests/fixtures');

    foreach ([
        'Security/ActionPermission.php',
        'Security/PermissionDenied.php',
        'Security/FieldReadRequirement.php',
        'Security/LinkReadRequirement.php',
        'Security/ActionAccessPolicy.php',
        'Audit/AuditEventCategory.php',
        'Audit/AuditContextSanitizer.php',
        'Error/ErrorCategory.php',
        'Error/PublicError.php',
        'Error/WarningCode.php',
        'Error/PublicWarning.php',
    ] as $relativePath) {
        require "$moduleRoot/Tools/DocumentBuilder/$relativePath";
    }

    final readonly class TestEntity implements Entity
    {
        public function __construct(private string $entityType)
        {}

        public function getEntityType(): string
        {
            return $this->entityType;
        }
    }

    $expectedPermissionFields = [
        'documentBuilderDesignTemplatesPermission',
        'documentBuilderPublishTemplatesPermission',
        'documentBuilderGenerateDocumentsPermission',
        'documentBuilderGenerateBatchesPermission',
        'documentBuilderUseSpreadsheetImportsPermission',
        'documentBuilderManageSharedMediaPermission',
        'documentBuilderViewDataSnapshotsPermission',
        'documentBuilderDeleteGeneratedDocumentsPermission',
        'documentBuilderConfigurePermission',
    ];

    Assert::same(
        $expectedPermissionFields,
        array_map(
            static fn (ActionPermission $permission): string => $permission->fieldName(),
            ActionPermission::cases(),
        ),
        'The action-permission enum must expose the nine approved permissions.',
    );

    $aclMetadata = $resourceLoader->json('metadata/app/acl.json');
    Assert::same(
        array_merge(['__APPEND__'], $expectedPermissionFields),
        $aclMetadata['valuePermissionList'] ?? null,
        'Module ACL metadata must append the nine action permissions without replacing core permissions.',
    );
    Assert::same(
        array_fill_keys($expectedPermissionFields, 'yes'),
        $aclMetadata['valuePermissionHighestLevels'] ?? null,
        'Administrators must inherit the highest action-permission level.',
    );
    Assert::same(
        array_fill_keys($expectedPermissionFields, 'no'),
        $aclMetadata['permissionsStrictDefaults'] ?? null,
        'Unconfigured non-administrator permissions must fail closed.',
    );

    $roleMetadata = $resourceLoader->json('metadata/entityDefs/Role.json');
    Assert::same(
        $expectedPermissionFields,
        array_keys($roleMetadata['fields'] ?? []),
        'Role metadata must persist exactly the approved permissions.',
    );

    foreach ($expectedPermissionFields as $field) {
        Assert::same(
            ["not-set", 'yes', 'no'],
            $roleMetadata['fields'][$field]['options'] ?? null,
            "Role permission levels changed for $field.",
        );
        Assert::same(
            'not-set',
            $roleMetadata['fields'][$field]['default'] ?? null,
            "A role grant must not be guessed for $field.",
        );
    }

    foreach (['en_US', 'ro_RO'] as $locale) {
        $roleI18n = $resourceLoader->json("i18n/$locale/Role.json");
        Assert::same(
            $expectedPermissionFields,
            array_keys($roleI18n['fields'] ?? []),
            "$locale must label every role permission.",
        );
        Assert::same(
            $expectedPermissionFields,
            array_keys($roleI18n['tooltips'] ?? []),
            "$locale must explain every role permission.",
        );
    }

    $aclCases = $fixtureLoader->json('acl/cases.json');

    foreach ($aclCases['cases'] as $case) {
        $checks = $case['checks'];
        $acl = new Acl();
        $permission = $case['operation'] === 'view-snapshot'
            ? ActionPermission::ViewDataSnapshots
            : ActionPermission::GenerateDocuments;
        $acl->permissionLevels[$permission->value] = $checks['action'] ? 'yes' : 'no';
        $acl->scopeAccess['Contact'] = $checks['scope'];
        $acl->fieldAccess['Contact.privateField'] = $checks['fields'];
        $acl->linkAccess['Contact.account'] = $checks['links'];

        $source = new TestEntity('Contact');
        $related = new TestEntity('Account');
        $acl->entityAccess[spl_object_id($source)] = $checks['record'];
        $acl->entityAccess[spl_object_id($related)] = $checks['relatedRecords'];
        $policy = new ActionAccessPolicy($acl);
        $decision = 'allow';

        try {
            $policy->requireSourceRead(
                $permission,
                $source,
                [new FieldReadRequirement('Contact', 'privateField')],
                [new LinkReadRequirement('Contact', 'account')],
            );
            $readableRelated = $policy->filterReadableRelatedRecords($permission, [$related]);

            if (count($readableRelated) !== 1) {
                $decision = 'filter';
            }
        } catch (PermissionDenied $exception) {
            $decision = 'deny';
            $publicText = $exception->getMessage();

            foreach (['Contact', 'privateField', 'account', 'forbidden-value'] as $forbiddenText) {
                Assert::isFalse(
                    str_contains($publicText, $forbiddenText),
                    'Permission failures must not identify inaccessible data.',
                );
            }
        }

        Assert::same(
            $case['expected']['decision'],
            $decision,
            "ACL fixture decision changed: {$case['id']}.",
        );
        Assert::isFalse(
            $case['expected']['discloseForbiddenValue'],
            "The ACL fixture must prohibit disclosure: {$case['id']}.",
        );
    }

    $acl = new Acl();
    $acl->permissionLevels[ActionPermission::GenerateDocuments->value] = 'yes';
    $policy = new ActionAccessPolicy($acl);
    $source = new TestEntity('Contact');
    $policy->requireSourceRead(
        ActionPermission::GenerateDocuments,
        $source,
        [new FieldReadRequirement('Contact', 'name')],
        [new LinkReadRequirement('Contact', 'account')],
    );
    Assert::same(
        [
            'permission:documentBuilderGenerateDocuments',
            'scope:Contact:read',
            'entity:Contact:read',
            'scope:Contact:read',
            'field:Contact:name:read',
            'scope:Contact:read',
            'link:Contact:account:read',
        ],
        $acl->calls,
        'The policy must compose action, scope, record, field, and link checks in a stable order.',
    );

    Assert::throws(
        fn () => new FieldReadRequirement('../Contact', 'name'),
        InvalidArgumentException::class,
        'ACL requirement identifiers must reject path-like input.',
    );
    Assert::throws(
        fn () => $policy->requireSourceRead(
            ActionPermission::GenerateDocuments,
            $source,
            [new stdClass()],
        ),
        InvalidArgumentException::class,
        'The policy must reject untyped field requirements.',
    );

    $editAcl = new Acl();
    $editAcl->permissionLevels[ActionPermission::DesignTemplates->value] = 'yes';
    $editableTemplate = new TestEntity('DocumentBuilderTemplate');
    (new ActionAccessPolicy($editAcl))->requireRecordEdit(
        ActionPermission::DesignTemplates,
        $editableTemplate,
    );
    Assert::same(
        [
            'permission:documentBuilderDesignTemplates',
            'scope:DocumentBuilderTemplate:edit',
            'entity:DocumentBuilderTemplate:edit',
        ],
        $editAcl->calls,
        'Draft editing must compose action, scope, and record edit checks.',
    );

    Assert::same(
        [
            'templateLifecycle',
            'publication',
            'generation',
            'batch',
            'spreadsheetImport',
            'sharedMedia',
            'authorization',
            'securityValidation',
            'settings',
        ],
        array_column(AuditEventCategory::cases(), 'value'),
        'Audit event categories changed unexpectedly.',
    );

    $sanitized = (new AuditContextSanitizer())->sanitize([
        'actorId' => 'user-1',
        'sourceEntityType' => 'Contact',
        'sourceRecordId' => 'contact-1',
        'recordCount' => 4,
        'retryable' => false,
        'resolvedValue' => 'private salary 12345',
        'snapshot' => ['secret' => 'raw snapshot'],
        'stack' => '/opt/private/File.php:42',
        "unsafe\nkey" => 'token-value',
        'technicalCode' => '../unsafe-path',
    ]);
    Assert::same('user-1', $sanitized['actorId'] ?? null, 'Safe audit actor identity was removed.');
    Assert::same('contact-1', $sanitized['sourceRecordId'] ?? null, 'Required source identity was removed.');
    Assert::same(5, $sanitized['redactedFieldCount'] ?? null, 'Unsafe audit fields were not counted as redacted.');
    $serializedAudit = json_encode($sanitized, JSON_THROW_ON_ERROR);

    foreach (['salary', '12345', 'raw snapshot', '/opt/private', 'token-value', 'unsafe-path', "unsafe\nkey"] as $secret) {
        Assert::isFalse(
            str_contains($serializedAudit, $secret),
            'Security-safe logging must discard arbitrary keys and values.',
        );
    }

    $expectedStatusMap = [
        'validation' => 400,
        'permission' => 403,
        'sourceRecordMissing' => 404,
        'variableMissing' => 422,
        'relatedRecordMissing' => 422,
        'mediaMissing' => 422,
        'renderer' => 500,
        'fileStorage' => 500,
        'batchJob' => 500,
        'revisionConflict' => 409,
        'sourceChangeConfirmation' => 409,
        'publicationConflict' => 409,
        'lifecycleConflict' => 409,
    ];

    foreach (ErrorCategory::cases() as $category) {
        Assert::same(
            $expectedStatusMap[$category->value],
            $category->httpStatus(),
            "HTTP status changed for {$category->value}.",
        );
        Assert::same(
            "errors.{$category->value}",
            $category->messageKey(),
            "Translation key changed for {$category->value}.",
        );
    }

    $publicError = new PublicError(ErrorCategory::Renderer, 'element-1', 'request-1', retryable: true);
    Assert::same(500, $publicError->httpStatus(), 'Public errors must use the category HTTP mapping.');
    Assert::same(
        [
            'category' => 'renderer',
            'messageKey' => 'errors.renderer',
            'retryable' => true,
            'elementId' => 'element-1',
            'correlationId' => 'request-1',
        ],
        $publicError->toArray(),
        'Public error output must contain only stable, safe fields.',
    );
    Assert::isFalse(
        (new PublicError(ErrorCategory::Validation, retryable: true))->retryable,
        'Non-retryable errors must not accept unsafe retry guidance.',
    );
    Assert::throws(
        fn () => new PublicError(ErrorCategory::Validation, '../secret'),
        InvalidArgumentException::class,
        'Public error identifiers must reject unsafe data.',
    );

    Assert::same(
        ['valueUnavailable', 'mediaUnavailable', 'collectionTruncated', 'rendererCompatibility'],
        array_column(WarningCode::cases(), 'value'),
        'Public warnings must not expose an authorization or filtered-record warning.',
    );
    Assert::same(
        [
            'code' => 'valueUnavailable',
            'messageKey' => 'warnings.valueUnavailable',
            'elementId' => 'element-1',
        ],
        (new PublicWarning(WarningCode::ValueUnavailable, 'element-1'))->toArray(),
        'Warnings must expose only a fixed code, translation key, and safe element ID.',
    );

    foreach (['en_US', 'ro_RO'] as $locale) {
        $i18n = $resourceLoader->json("i18n/$locale/DocumentBuilder.json");
        Assert::same(
            array_keys($expectedStatusMap),
            array_keys($i18n['errors'] ?? []),
            "$locale error messages must match the error contract.",
        );
        Assert::same(
            array_column(WarningCode::cases(), 'value'),
            array_keys($i18n['warnings'] ?? []),
            "$locale warnings must match the warning contract.",
        );
    }

    echo "Phase 07 permission, audit, redaction, and error contract checks passed.\n";
}
