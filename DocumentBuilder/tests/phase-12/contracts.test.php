<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use DocumentBuilder\Tests\Support\FixtureLoader;

require dirname(__DIR__) . '/bootstrap.php';

$extensionRoot = dirname(__DIR__, 2);
$moduleRoot = $extensionRoot . '/files/custom/Espo/Modules/DocumentBuilder';
$loader = new FixtureLoader($moduleRoot . '/Resources');
$routes = $loader->json('routes.json');
$entityDefs = $loader->json('metadata/entityDefs/DocumentBuilderTemplate.json');
$entityAcl = $loader->json('metadata/entityAcl/DocumentBuilderTemplate.json');

Assert::isTrue(count($routes) >= 1, 'The Phase 12 draft route is missing.');
Assert::same('/DocumentBuilder/template/:id/draft', $routes[0]['route'] ?? null, 'Draft route changed.');
Assert::same('put', $routes[0]['method'] ?? null, 'Draft saving must be idempotent PUT semantics.');
Assert::same(
    'Espo\\Modules\\DocumentBuilder\\Api\\PutTemplateDraft',
    $routes[0]['actionClassName'] ?? null,
    'Draft route must use the API Action framework.',
);
Assert::isFalse(isset($routes[0]['noAuth']), 'Draft saving must require authentication.');

$changeNote = $entityDefs['fields']['draftChangeNote'] ?? null;
Assert::same('text', $changeNote['type'] ?? null, 'Draft change note needs persistent text storage.');
Assert::same(true, $changeNote['readOnly'] ?? null, 'Only the draft service may write change notes.');
Assert::same(true, $entityAcl['fields']['draftChangeNote']['readOnly'] ?? null, 'Entity ACL must protect change notes.');

$binding = file_get_contents("$moduleRoot/Binding.php");
$action = file_get_contents("$moduleRoot/Api/PutTemplateDraft.php");
$store = file_get_contents("$moduleRoot/Tools/DocumentBuilder/Draft/OrmDraftTemplateStore.php");

foreach (['binding' => $binding, 'action' => $action, 'store' => $store] as $label => $source) {
    Assert::isTrue(is_string($source), "Could not read Phase 12 $label source.");
}

Assert::contains('bindImplementation(DraftTemplateStore::class, OrmDraftTemplateStore::class)', $binding, 'ORM store binding is missing.');
Assert::contains('implements Action', $action, 'Draft endpoint must implement Espo API Action.');
Assert::contains("Conflict::createWithBody('revisionConflict'", $action, 'Stale writes need a structured 409 response.');
Assert::contains("Conflict::createWithBody('sourceChangeConfirmation'", $action, 'Source changes need a structured confirmation response.');
Assert::contains('new Forbidden(', $action, 'Permission failures must map to HTTP 403.');
Assert::contains('->forUpdate()', $store, 'Revision comparison must occur under a row lock.');
Assert::contains('getTransactionManager()->run(', $store, 'Draft mutation must run in one transaction.');

$service = file_get_contents("$moduleRoot/Tools/DocumentBuilder/Draft/DraftSaveService.php");
Assert::isTrue(is_string($service), 'Could not read the draft service.');
Assert::contains('$actualRevision + 1', $service, 'A successful save must increment revision exactly once.');
Assert::contains('SourceChangeConfirmationRequired', $service, 'Unconfirmed source changes must stop before persistence.');
Assert::contains("'pageSize' =>", $service, 'Page summary must be updated with the normalized layout.');
Assert::contains("'orientation' =>", $service, 'Orientation summary must be updated with the normalized layout.');

foreach (['en_US', 'ro_RO'] as $locale) {
    $templateI18n = $loader->json("i18n/$locale/DocumentBuilderTemplate.json");
    $moduleI18n = $loader->json("i18n/$locale/DocumentBuilder.json");
    Assert::isTrue(isset($templateI18n['fields']['draftChangeNote']), "$locale draft change-note label is missing.");
    Assert::isTrue(isset($moduleI18n['errors']['sourceChangeConfirmation']), "$locale source confirmation error is missing.");
}

echo "Phase 12 draft API and metadata contract tests passed.\n";
