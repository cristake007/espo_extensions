<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use DocumentBuilder\Tests\Support\FixtureLoader;

require dirname(__DIR__) . '/bootstrap.php';

$extensionRoot = dirname(__DIR__, 2);
$moduleRoot = $extensionRoot . '/files/custom/Espo/Modules/DocumentBuilder';
$loader = new FixtureLoader($moduleRoot . '/Resources');
$entityDefs = $loader->json('metadata/entityDefs/DocumentBuilderTemplateVersion.json');
$templateDefs = $loader->json('metadata/entityDefs/DocumentBuilderTemplate.json');
$scope = $loader->json('metadata/scopes/DocumentBuilderTemplateVersion.json');
$aclDefs = $loader->json('metadata/aclDefs/DocumentBuilderTemplateVersion.json');
$entityAcl = $loader->json('metadata/entityAcl/DocumentBuilderTemplateVersion.json');
$recordDefs = $loader->json('metadata/recordDefs/DocumentBuilderTemplateVersion.json');
$clientDefs = $loader->json('metadata/clientDefs/DocumentBuilderTemplateVersion.json');
$fields = $entityDefs['fields'] ?? [];
$links = $entityDefs['links'] ?? [];

$expectedFields = [
    'name',
    'template',
    'versionNumber',
    'schemaVersion',
    'layoutSnapshot',
    'sourceSnapshot',
    'publishedBy',
    'publishedAt',
    'changeNote',
    'checksum',
    'isCurrent',
    'assignedUser',
    'teams',
    'createdAt',
    'createdBy',
];
Assert::same($expectedFields, array_keys($fields), 'Template-version persistence fields changed unexpectedly.');

foreach ($expectedFields as $field) {
    Assert::same(true, $fields[$field]['readOnly'] ?? null, "Version field must be read-only: $field.");
}

foreach (array_keys($entityAcl['fields'] ?? []) as $field) {
    Assert::same(true, $entityAcl['fields'][$field]['readOnly'] ?? null, "Entity ACL must protect $field.");
}

Assert::same('jsonObject', $fields['layoutSnapshot']['type'] ?? null, 'Layout snapshots must use typed JSON storage.');
Assert::same('jsonObject', $fields['sourceSnapshot']['type'] ?? null, 'Source snapshots must use typed JSON storage.');
Assert::same(1, $fields['versionNumber']['min'] ?? null, 'Version numbers must be positive.');
Assert::same(1, $fields['schemaVersion']['min'] ?? null, 'Schema versions must be positive.');
Assert::same(64, $fields['checksum']['maxLength'] ?? null, 'SHA-256 storage must be exactly 64 hex characters.');
Assert::same('^[a-f0-9]{64}$', $fields['checksum']['pattern'] ?? null, 'Checksum field must reject non-SHA-256 text.');
Assert::same(true, $fields['isCurrent']['default'] ?? null, 'A newly published version starts current.');
Assert::same(true, $fields['assignedUser']['required'] ?? null, 'Publication-time ownership must be captured.');

Assert::same(
    ['type' => 'belongsTo', 'entity' => 'DocumentBuilderTemplate', 'foreign' => 'versions'],
    $links['template'] ?? null,
    'Version-to-template relationship changed.',
);
Assert::same(
    [
        'type' => 'hasMany',
        'entity' => 'DocumentBuilderTemplateVersion',
        'foreign' => 'template',
        'readOnly' => true,
        'orderBy' => 'versionNumber',
        'order' => 'desc',
    ],
    $templateDefs['links']['versions'] ?? null,
    'Template version-history relationship changed.',
);
Assert::same(
    ['templateId', 'versionNumber'],
    $entityDefs['indexes']['templateVersion']['columns'] ?? null,
    'Version uniqueness must be scoped to one template.',
);
Assert::same('unique', $entityDefs['indexes']['templateVersion']['type'] ?? null, 'Version uniqueness needs a database constraint.');

Assert::same(true, $entityDefs['transactionalSave'] ?? null, 'Version writes must be transactional.');
Assert::same('BasePlus', $scope['type'] ?? null, 'Version ACL needs native own/team semantics.');
Assert::same(['read'], $scope['aclActionList'] ?? null, 'Normal version CRUD must expose read only.');
Assert::same(false, $scope['tab'] ?? null, 'Versions must be reached through template history, not navigation.');
Assert::same(false, $scope['aclPortal'] ?? null, 'Version snapshots must not be portal-readable.');
Assert::same(true, $scope['hasPersonalData'] ?? null, 'Snapshot records need privacy eligibility.');
Assert::same(
    'Espo\\Modules\\DocumentBuilder\\Classes\\Acl\\DocumentBuilderTemplateVersionAccessChecker',
    $aclDefs['accessCheckerClassName'] ?? null,
    'Version ACL checker changed.',
);
Assert::same(true, $entityAcl['systemWriteForbidden'] ?? null, 'Formula writes must not mutate snapshots.');

foreach (['beforeCreate', 'beforeUpdate', 'beforeDelete'] as $operation) {
    $key = $operation . 'HookClassNameList';
    Assert::same(1, count($recordDefs[$key] ?? []), "Version $operation guard is missing.");
}

Assert::same(true, $recordDefs['massActions']['delete']['disabled'] ?? null, 'Mass deletion must be hidden.');
Assert::same(true, $recordDefs['actions']['merge']['disabled'] ?? null, 'Version merge must be disabled.');
Assert::same(true, $recordDefs['actions']['duplicate']['disabled'] ?? null, 'Version duplication must be disabled.');
Assert::same('controllers/record', $clientDefs['controller'] ?? null, 'Version reads must use the native record controller.');

$layoutFields = [];

foreach (['list', 'detail', 'search', 'filters'] as $layoutName) {
    $layout = $loader->json("layouts/DocumentBuilderTemplateVersion/$layoutName.json");
    $iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($layout));

    foreach ($iterator as $key => $value) {
        if (is_string($value) && ($key === 'name' || ($layoutName === 'filters' && is_int($key)))) {
            $layoutFields[$layoutName][$value] = true;
        }
    }
}

foreach ($layoutFields as $layoutName => $names) {
    foreach (array_keys($names) as $field) {
        Assert::isTrue(isset($fields[$field]), "Layout $layoutName references an unknown field: $field.");
    }
}

Assert::isFalse(is_file("$moduleRoot/Resources/layouts/DocumentBuilderTemplateVersion/edit.json"), 'Immutable versions must not have an edit layout.');

foreach (['en_US', 'ro_RO'] as $locale) {
    $i18n = $loader->json("i18n/$locale/DocumentBuilderTemplateVersion.json");
    $global = $loader->json("i18n/$locale/Global.json");
    Assert::same($expectedFields, array_keys($i18n['fields'] ?? []), "$locale must label every version field.");
    Assert::isTrue(isset($global['scopeNames']['DocumentBuilderTemplateVersion']), "$locale version scope name is missing.");
    Assert::isTrue(isset($global['scopeNamesPlural']['DocumentBuilderTemplateVersion']), "$locale plural version scope name is missing.");
}

$controller = file_get_contents("$moduleRoot/Controllers/DocumentBuilderTemplateVersion.php");
Assert::isTrue(is_string($controller), 'Could not read the version controller.');
Assert::contains('class DocumentBuilderTemplateVersion extends Record', $controller, 'Version API controller changed.');

echo "Phase 11 template-version metadata tests passed.\n";
