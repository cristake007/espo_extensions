<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use DocumentBuilder\Tests\Support\FixtureLoader;

require dirname(__DIR__) . '/bootstrap.php';
$root = dirname(__DIR__, 2);
$module = "$root/files/custom/Espo/Modules/DocumentBuilder";
$loader = new FixtureLoader("$module/Resources");
$entity = $loader->json('metadata/entityDefs/DocumentBuilderDocument.json');
$scope = $loader->json('metadata/scopes/DocumentBuilderDocument.json');
$acl = $loader->json('metadata/aclDefs/DocumentBuilderDocument.json');
$entityAcl = $loader->json('metadata/entityAcl/DocumentBuilderDocument.json');
$record = $loader->json('metadata/recordDefs/DocumentBuilderDocument.json');
$client = $loader->json('metadata/clientDefs/DocumentBuilderDocument.json');
$fields = $entity['fields'] ?? [];
$links = $entity['links'] ?? [];

Assert::same([
    'Pending', 'Generating', 'Completed', 'Completed with Warnings', 'Failed', 'Cancelled',
], $fields['status']['options'] ?? null, 'Generated-document statuses changed.');
Assert::same('Pending', $fields['status']['default'] ?? null, 'Generation history must start pending.');
foreach (['name', 'template', 'templateVersion', 'sourceType', 'outputFilename', 'generatedBy', 'assignedUser'] as $field) {
    Assert::same(true, $fields[$field]['required'] ?? null, "Required provenance field is nullable: $field.");
}
foreach (['sourceRecord', 'spreadsheetImportId', 'spreadsheetRowNumber', 'batchId', 'pdfAttachment', 'dataSnapshot', 'templateSnapshot', 'completedAt'] as $field) {
    Assert::isFalse(($fields[$field]['required'] ?? false) === true, "Future or lifecycle field must remain nullable: $field.");
}
Assert::same('file', $fields['pdfAttachment']['type'] ?? null, 'PDF bytes must use Espo Attachment/File Storage.');
Assert::same('jsonObject', $fields['dataSnapshot']['type'] ?? null, 'Data snapshot must be typed JSON.');
Assert::same(true, $fields['dataSnapshot']['exportDisabled'] ?? null, 'Sensitive data snapshots must not be exported.');
Assert::same(true, $fields['templateSnapshot']['exportDisabled'] ?? null, 'Template snapshots must not be exported.');
Assert::same('linkParent', $fields['sourceRecord']['type'] ?? null, 'Entity provenance must use a polymorphic source link.');
Assert::same('varchar', $fields['spreadsheetImportId']['type'] ?? null, 'Absent spreadsheet scope must remain a nullable ID hook.');
Assert::same('varchar', $fields['batchId']['type'] ?? null, 'Absent batch scope must remain a nullable ID hook.');

Assert::same('DocumentBuilderTemplate', $links['template']['entity'] ?? null, 'Template provenance link is missing.');
Assert::same('DocumentBuilderTemplateVersion', $links['templateVersion']['entity'] ?? null, 'Version provenance link is missing.');
Assert::same('belongsToParent', $links['sourceRecord']['type'] ?? null, 'Source record relationship changed.');
Assert::same('entityTeam', $links['teams']['relationName'] ?? null, 'Native team ownership changed.');
Assert::same(true, $entity['transactionalSave'] ?? null, 'History saves must be transactional.');
Assert::same(true, $entity['optimisticConcurrencyControl'] ?? null, 'Editable notes and ownership need stale-write protection.');
Assert::same(['sourceRecordType', 'sourceRecordId', 'createdAt', 'deleted'], $entity['indexes']['sourceRecord']['columns'] ?? null, 'Source-history index changed.');

Assert::same('BasePlus', $scope['type'] ?? null, 'History must use native own/team ACL semantics.');
Assert::same(['read', 'edit', 'delete'], $scope['aclActionList'] ?? null, 'Direct creation must not be role-grantable.');
Assert::same(false, $scope['aclPortal'] ?? null, 'Generated documents must not be portal-readable.');
Assert::same(true, $scope['hasPersonalData'] ?? null, 'Snapshot records need privacy eligibility.');
Assert::same('Espo\\Modules\\DocumentBuilder\\Classes\\Acl\\DocumentBuilderDocumentAccessChecker', $acl['accessCheckerClassName'] ?? null, 'Generated-document ACL checker is missing.');
Assert::same('Espo\\Core\\Acl\\DefaultOwnershipChecker', $acl['ownershipCheckerClassName'] ?? null, 'Native ownership checker is missing.');
Assert::same(true, $entityAcl['systemWriteForbidden'] ?? null, 'Formula must not mutate generation history.');
foreach (['status', 'template', 'templateVersion', 'sourceRecord', 'pdfAttachment', 'dataSnapshot', 'generatedBy', 'completedAt'] as $field) {
    Assert::same(true, $entityAcl['fields'][$field]['readOnly'] ?? null, "Entity ACL does not protect $field.");
}
Assert::isFalse(isset($entityAcl['fields']['description']), 'User notes should remain editable under normal record ACL.');

Assert::same('controllers/record', $client['controller'] ?? null, 'Generated documents must use native record views.');
Assert::same(['onlyMy'], $client['boolFilterList'] ?? null, 'Ownership filter changed.');
Assert::same([
    'Espo\\Modules\\DocumentBuilder\\Classes\\Record\\OutputFilters\\DocumentBuilderDocument\\Snapshot',
], $record['outputFilterClassNameList'] ?? null, 'Snapshot output filter is missing.');
Assert::same([
    'Espo\\Modules\\DocumentBuilder\\Classes\\Record\\Hooks\\DocumentBuilderDocument\\BeforeCreate',
], $record['beforeCreateHookClassNameList'] ?? null, 'Direct API creation guard is missing.');
Assert::same([
    'Espo\\Modules\\DocumentBuilder\\Classes\\Record\\Hooks\\DocumentBuilderDocument\\BeforeUpdate',
], $record['beforeUpdateHookClassNameList'] ?? null, 'Immutability hook is missing.');

$layoutFields = [];
foreach (['detail', 'edit', 'list', 'search', 'filters'] as $name) {
    $layout = $loader->json("layouts/DocumentBuilderDocument/$name.json");
    $iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($layout));
    foreach ($iterator as $key => $value) {
        if ($key === 'name' && is_string($value)) $layoutFields[$value] = true;
        if ($name === 'filters' && is_string($value)) $layoutFields[$value] = true;
    }
}
foreach (array_keys($layoutFields) as $field) {
    Assert::isTrue(isset($fields[$field]), "Generated-document layout references an unknown field: $field.");
}

foreach (['en_US', 'ro_RO'] as $locale) {
    $language = $loader->json("i18n/$locale/DocumentBuilderDocument.json");
    $global = $loader->json("i18n/$locale/Global.json");
    Assert::isTrue(isset($language['fields']['pdfAttachment']), "$locale PDF field translation is missing.");
    Assert::isTrue(isset($language['options']['status']['Completed with Warnings']), "$locale warning status translation is missing.");
    Assert::isTrue(isset($global['scopeNames']['DocumentBuilderDocument']), "$locale generated-document scope name is missing.");
}

echo "Phase 36 generated-document metadata contracts passed.\n";
