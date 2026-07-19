<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use DocumentBuilder\Tests\Support\FixtureLoader;

require dirname(__DIR__) . '/bootstrap.php';

$extensionRoot = dirname(__DIR__, 2);
$moduleRoot = $extensionRoot . '/files/custom/Espo/Modules/DocumentBuilder';
$resourceLoader = new FixtureLoader($moduleRoot . '/Resources');
$fixtureLoader = new FixtureLoader($extensionRoot . '/tests/fixtures');

$entityDefs = $resourceLoader->json('metadata/entityDefs/DocumentBuilderTemplate.json');
$scope = $resourceLoader->json('metadata/scopes/DocumentBuilderTemplate.json');
$aclDefs = $resourceLoader->json('metadata/aclDefs/DocumentBuilderTemplate.json');
$entityAcl = $resourceLoader->json('metadata/entityAcl/DocumentBuilderTemplate.json');
$recordDefs = $resourceLoader->json('metadata/recordDefs/DocumentBuilderTemplate.json');
$clientDefs = $resourceLoader->json('metadata/clientDefs/DocumentBuilderTemplate.json');
$canonicalDefault = $fixtureLoader->json('layout/phase-08-default.json');
$fields = $entityDefs['fields'] ?? [];
$links = $entityDefs['links'] ?? [];

$expectedFields = [
    'name',
    'category',
    'description',
    'status',
    'sourceType',
    'entityType',
    'spreadsheetSchema',
    'currentDraftLayout',
    'revision',
    'draftChangeNote',
    'pageSize',
    'orientation',
    'isActive',
    'currentPublishedVersion',
    'assignedUser',
    'teams',
    'createdAt',
    'modifiedAt',
    'createdBy',
    'modifiedBy',
];
Assert::same($expectedFields, array_keys($fields), 'Template persistence fields changed unexpectedly.');

Assert::same('varchar', $fields['name']['type'] ?? null, 'Template name must be a varchar.');
Assert::same(true, $fields['name']['required'] ?? null, 'Template name must be required.');
Assert::same(150, $fields['name']['maxLength'] ?? null, 'Template name bound changed.');
Assert::same('$noBadCharacters', $fields['name']['pattern'] ?? null, 'Template name sanitization changed.');

Assert::same(
    ['Draft', 'Published', 'Archived'],
    $fields['status']['options'] ?? null,
    'Template lifecycle statuses changed.',
);
Assert::same('Draft', $fields['status']['default'] ?? null, 'New templates must start as drafts.');
Assert::same(
    ['none', 'entity', 'spreadsheet'],
    $fields['sourceType']['options'] ?? null,
    'Primary source variants changed.',
);
Assert::same('none', $fields['sourceType']['default'] ?? null, 'Native creation must be source-neutral.');
Assert::same('jsonObject', $fields['spreadsheetSchema']['type'] ?? null, 'Spreadsheet schema storage must be typed JSON.');
Assert::same('jsonObject', $fields['currentDraftLayout']['type'] ?? null, 'Draft layout storage must be typed JSON.');
Assert::same(
    $canonicalDefault,
    $fields['currentDraftLayout']['default'] ?? null,
    'Native template creation must persist the Phase 09 canonical default.',
);
Assert::same(0, $fields['revision']['default'] ?? null, 'A new draft revision must start at zero.');
Assert::same(0, $fields['revision']['min'] ?? null, 'Draft revision must not be negative.');
Assert::same(
    $canonicalDefault['document']['page']['size'],
    $fields['pageSize']['default'] ?? null,
    'Page-size summary must match the canonical layout.',
);
Assert::same(
    $canonicalDefault['document']['page']['orientation'],
    $fields['orientation']['default'] ?? null,
    'Orientation summary must match the canonical layout.',
);
Assert::same(true, $fields['isActive']['default'] ?? null, 'New drafts must be active.');

$lifecycleManagedFields = [
    'status',
    'sourceType',
    'entityType',
    'spreadsheetSchema',
    'currentDraftLayout',
    'revision',
    'draftChangeNote',
    'pageSize',
    'orientation',
    'isActive',
    'currentPublishedVersion',
];

foreach ($lifecycleManagedFields as $field) {
    Assert::same(true, $fields[$field]['readOnly'] ?? null, "Lifecycle field must be read-only: $field.");
    Assert::same(
        true,
        $entityAcl['fields'][$field]['readOnly'] ?? null,
        "Entity ACL must protect lifecycle field: $field.",
    );
}

Assert::same(true, $entityAcl['systemWriteForbidden'] ?? null, 'Formula writes must not bypass lifecycle services.');
Assert::same(
    ['type' => 'belongsTo', 'entity' => 'DocumentBuilderTemplateVersion'],
    $links['currentPublishedVersion'] ?? null,
    'The forward current-version relationship changed.',
);
Assert::same(
    ['type' => 'belongsTo', 'entity' => 'User'],
    $links['assignedUser'] ?? null,
    'Assigned-user ownership must use Espo native semantics.',
);
Assert::same('entityTeam', $links['teams']['relationName'] ?? null, 'Team ownership must use the native relation.');
Assert::same(true, $fields['assignedUser']['required'] ?? null, 'Every template must have an assigned owner.');

Assert::same(true, $entityDefs['transactionalSave'] ?? null, 'Template saves must be transactional.');
Assert::same(true, $entityDefs['optimisticConcurrencyControl'] ?? null, 'Native metadata edits need stale-write protection.');
Assert::same('modifiedAt', $entityDefs['collection']['orderBy'] ?? null, 'Template list ordering changed.');
Assert::same(
    ['sourceType', 'entityType', 'deleted'],
    $entityDefs['indexes']['source']['columns'] ?? null,
    'Source lookup index changed.',
);

Assert::same('DocumentBuilder', $scope['module'] ?? null, 'Template scope must belong to the module.');
Assert::same('BasePlus', $scope['type'] ?? null, 'Template scope must support native ownership and teams.');
Assert::same(true, $scope['entity'] ?? null, 'Template scope must be an entity.');
Assert::same(true, $scope['object'] ?? null, 'Template scope must be a business object.');
Assert::same(true, $scope['acl'] ?? null, 'Template scope must participate in roles.');
Assert::same(false, $scope['aclPortal'] ?? null, 'Templates must not be exposed to portal roles.');
Assert::same(false, in_array('delete', $scope['aclActionList'] ?? [], true), 'Hard-delete must not be grantable.');
Assert::same('status', $scope['statusField'] ?? null, 'Status-field metadata changed.');
Assert::same(true, $scope['hasPersonalData'] ?? null, 'Template text must remain eligible for privacy handling.');

Assert::same(
    'Espo\\Modules\\DocumentBuilder\\Classes\\Acl\\DocumentBuilderTemplateAccessChecker',
    $aclDefs['accessCheckerClassName'] ?? null,
    'Scope ACL must compose the design permission with Espo native record checks.',
);
Assert::same(
    'Espo\\Core\\Acl\\DefaultOwnershipChecker',
    $aclDefs['ownershipCheckerClassName'] ?? null,
    'Own/team ACL must use Espo native ownership checks.',
);
Assert::same(
    'documentBuilderDesignTemplates',
    $scope['tabAclPermission'] ?? null,
    'The template tab must require the dedicated design permission.',
);
Assert::same(true, $entityAcl['links']['currentPublishedVersion']['readOnly'] ?? null, 'Current version link must be protected.');
Assert::same(true, $recordDefs['massActions']['delete']['disabled'] ?? null, 'Mass hard-delete must be hidden.');
Assert::same(true, $recordDefs['actions']['merge']['disabled'] ?? null, 'Template merge must be disabled.');
Assert::same(
    ['Espo\\Modules\\DocumentBuilder\\Classes\\Record\\Hooks\\DocumentBuilderTemplate\\BeforeCreate'],
    $recordDefs['beforeCreateHookClassNameList'] ?? null,
    'Native create must install the canonical-default hook.',
);
Assert::same(
    ['Espo\\Modules\\DocumentBuilder\\Classes\\Record\\Hooks\\DocumentBuilderTemplate\\BeforeDelete'],
    $recordDefs['beforeDeleteHookClassNameList'] ?? null,
    'Native delete must install the hard-delete guard.',
);
Assert::same('controllers/record', $clientDefs['controller'] ?? null, 'The frontend must use native CRUD.');
Assert::same(['onlyMy'], $clientDefs['boolFilterList'] ?? null, 'Ownership filter changed.');

$layoutNames = [];

foreach (['list', 'detail', 'edit', 'search', 'filters'] as $layoutName) {
    $layout = $resourceLoader->json("layouts/DocumentBuilderTemplate/$layoutName.json");
    $iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($layout));

    foreach ($iterator as $key => $value) {
        if (
            is_string($value) &&
            ($key === 'name' || ($layoutName === 'filters' && is_int($key)))
        ) {
            $layoutNames[$layoutName][$value] = true;
        }
    }
}

foreach ($layoutNames as $layoutName => $names) {
    foreach (array_keys($names) as $field) {
        Assert::isTrue(isset($fields[$field]), "Layout $layoutName references an unknown field: $field.");
    }
}

foreach ($lifecycleManagedFields as $field) {
    Assert::isFalse(isset($layoutNames['edit'][$field]), "Native edit must not expose lifecycle field: $field.");
}

Assert::isTrue(
    isset($layoutNames['detail']['currentPublishedVersion']),
    'The current published version must be visible after Phase 11 defines its target entity.',
);

foreach (['en_US', 'ro_RO'] as $locale) {
    $i18n = $resourceLoader->json("i18n/$locale/DocumentBuilderTemplate.json");
    $global = $resourceLoader->json("i18n/$locale/Global.json");

    Assert::same($expectedFields, array_keys($i18n['fields'] ?? []), "$locale must label every template field.");
    Assert::same(
        ['Draft', 'Published', 'Archived'],
        array_keys($i18n['options']['status'] ?? []),
        "$locale must label every lifecycle status.",
    );
    Assert::same(
        ['none', 'entity', 'spreadsheet'],
        array_keys($i18n['options']['sourceType'] ?? []),
        "$locale must label every source type.",
    );
    Assert::isTrue(
        isset($global['scopeNames']['DocumentBuilderTemplate']),
        "$locale must provide the template scope name.",
    );
    Assert::isTrue(
        isset($global['scopeNamesPlural']['DocumentBuilderTemplate']),
        "$locale must provide the plural template scope name.",
    );
}

$controllerPath = "$moduleRoot/Controllers/DocumentBuilderTemplate.php";
$controllerSource = file_get_contents($controllerPath);

if ($controllerSource === false) {
    throw new RuntimeException('Could not read the template controller.');
}

Assert::contains('use Espo\\Core\\Controllers\\Record;', $controllerSource, 'Template API must use the native record controller.');
Assert::contains('class DocumentBuilderTemplate extends Record', $controllerSource, 'Template CRUD controller changed.');

echo "Phase 10 template metadata tests passed.\n";
