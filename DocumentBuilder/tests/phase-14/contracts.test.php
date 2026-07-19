<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use DocumentBuilder\Tests\Support\FixtureLoader;

require dirname(__DIR__) . '/bootstrap.php';

$extensionRoot = dirname(__DIR__, 2);
$moduleRoot = $extensionRoot . '/files/custom/Espo/Modules/DocumentBuilder';
$clientRoot = $extensionRoot . '/files/client/custom/modules/document-builder/src';
$loader = new FixtureLoader($moduleRoot . '/Resources');
$routes = $loader->json('routes.json');
$templateDefs = $loader->json('metadata/entityDefs/DocumentBuilderTemplate.json');
$templateClient = $loader->json('metadata/clientDefs/DocumentBuilderTemplate.json');
$versionClient = $loader->json('metadata/clientDefs/DocumentBuilderTemplateVersion.json');
$templateRecord = $loader->json('metadata/recordDefs/DocumentBuilderTemplate.json');
$relationships = $loader->json('layouts/DocumentBuilderTemplate/relationships.json');

Assert::same(5, count($routes), 'Phase 14 must expose exactly five bounded template routes.');
$expectedRoutes = [
    2 => ['/DocumentBuilder/template/:id/duplicate', 'post'],
    3 => ['/DocumentBuilder/template/:id/archive', 'post'],
    4 => ['/DocumentBuilder/template/:id/draft-from-version', 'post'],
];

foreach ($expectedRoutes as $index => [$path, $method]) {
    Assert::same($path, $routes[$index]['route'] ?? null, "Phase 14 route $path changed.");
    Assert::same($method, $routes[$index]['method'] ?? null, "Phase 14 route $path must use POST.");
    Assert::isFalse(isset($routes[$index]['noAuth']), "Phase 14 route $path must require authentication.");
}

$templateActions = array_column($templateClient['detailActionList'] ?? [], null, 'name');

foreach (['duplicateTemplate', 'draftFromPublishedVersion', 'viewVersions', 'archiveTemplate'] as $action) {
    Assert::isTrue(isset($templateActions[$action]), "Template detail action $action is missing.");
    Assert::contains('document-builder:handlers/', $templateActions[$action]['handler'], "Action $action has no module handler.");
}

$versionActions = array_column($versionClient['detailActionList'] ?? [], null, 'name');
Assert::isTrue(isset($versionActions['draftFromVersion']), 'Version detail must expose draft restoration.');
Assert::same(
    true,
    $templateClient['relationshipPanels']['versions']['readOnly'] ?? null,
    'Version history must be a read-only relationship panel.',
);
Assert::same(['versions'], $relationships, 'Version history must be present in Bottom Panels.');

Assert::same(true, $templateDefs['links']['versions']['readOnly'] ?? null, 'The version relationship must remain read-only.');
Assert::isFalse(isset($templateDefs['links']['generatedDocuments']), 'Generated-document metadata belongs to Phase 36/38 and must not reference an absent scope.');
Assert::same(true, $templateRecord['massActions']['delete']['disabled'] ?? null, 'Template mass deletion must remain disabled.');
$beforeDelete = file_get_contents("$moduleRoot/Classes/Record/Hooks/DocumentBuilderTemplate/BeforeDelete.php");
Assert::isTrue(is_string($beforeDelete), 'Could not read the template hard-delete hook.');
Assert::contains('throw new Forbidden', $beforeDelete, 'Hard delete must remain forbidden; archive is the lifecycle endpoint.');

$store = file_get_contents("$moduleRoot/Tools/DocumentBuilder/Lifecycle/OrmTemplateLifecycleStore.php");
$service = file_get_contents("$moduleRoot/Tools/DocumentBuilder/Lifecycle/TemplateLifecycleService.php");
$access = file_get_contents("$moduleRoot/Tools/DocumentBuilder/Lifecycle/AclTemplateLifecycleAccess.php");
$templateHandler = file_get_contents("$clientRoot/handlers/template-lifecycle.js");
$versionHandler = file_get_contents("$clientRoot/handlers/version-lifecycle.js");

foreach (compact('store', 'service', 'access', 'templateHandler', 'versionHandler') as $label => $source) {
    Assert::isTrue(is_string($source), "Could not read Phase 14 $label source.");
}

Assert::contains('getTransactionManager()->run(', $store, 'Every lifecycle mutation must be transactional.');
Assert::contains('->forUpdate()', $store, 'Lifecycle revision checks must occur under a template row lock.');
Assert::contains("'currentPublishedVersionId' => null", $service, 'Duplicates must not inherit a published version.');
Assert::contains("'status' => 'Archived'", $service, 'Archive must change lifecycle status.');
Assert::contains("'isActive' => false", $service, 'Archive must prohibit new generation.');
Assert::contains("'currentDraftLayout' => \$layout", $service, 'Draft restoration must copy the normalized immutable snapshot.');
Assert::contains('ActionPermission::PublishTemplates', $access, 'Archive requires publication authority.');
Assert::contains('ActionPermission::DesignTemplates', $access, 'Duplicate and draft restoration require design authority.');
Assert::contains('this.view.confirm(', $templateHandler, 'Destructive lifecycle UI actions require confirmation.');
Assert::contains('/duplicate`', $templateHandler, 'Duplicate UI endpoint is not wired.');
Assert::contains('/archive`', $templateHandler, 'Archive UI endpoint is not wired.');
Assert::contains('/draft-from-version`', $versionHandler, 'Version restoration UI endpoint is not wired.');
Assert::contains('/related/${this.view.model.id}/versions`', $templateHandler, 'Version-history navigation route is not wired.');

foreach (['en_US', 'ro_RO'] as $locale) {
    $templateI18n = $loader->json("i18n/$locale/DocumentBuilderTemplate.json");
    $versionI18n = $loader->json("i18n/$locale/DocumentBuilderTemplateVersion.json");
    $moduleI18n = $loader->json("i18n/$locale/DocumentBuilder.json");
    Assert::isTrue(isset($templateI18n['actions']['Archive']), "$locale archive action label is missing.");
    Assert::isTrue(isset($templateI18n['messages']['confirmDuplicate']), "$locale duplicate confirmation is missing.");
    Assert::isTrue(isset($versionI18n['actions']['Create Draft from Version']), "$locale version action label is missing.");
    Assert::isTrue(isset($moduleI18n['errors']['lifecycleConflict']), "$locale lifecycle conflict error is missing.");
}

echo "Phase 14 lifecycle API, ACL, navigation, and metadata contract tests passed.\n";
