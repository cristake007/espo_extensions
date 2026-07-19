<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use DocumentBuilder\Tests\Support\FixtureLoader;

require dirname(__DIR__) . '/bootstrap.php';

$extensionRoot = dirname(__DIR__, 2);
$moduleRoot = $extensionRoot . '/files/custom/Espo/Modules/DocumentBuilder';
$loader = new FixtureLoader($moduleRoot . '/Resources');
$routes = $loader->json('routes.json');
$templateDefs = $loader->json('metadata/entityDefs/DocumentBuilderTemplate.json');
$versionDefs = $loader->json('metadata/entityDefs/DocumentBuilderTemplateVersion.json');

Assert::isTrue(count($routes) >= 2, 'The Phase 13 publication route is missing.');
Assert::same('/DocumentBuilder/template/:id/publish', $routes[1]['route'] ?? null, 'Publication route changed.');
Assert::same('post', $routes[1]['method'] ?? null, 'Publication must use POST semantics.');
Assert::same(
    'Espo\\Modules\\DocumentBuilder\\Api\\PostTemplatePublish',
    $routes[1]['actionClassName'] ?? null,
    'Publication route must use the Phase 13 API Action.',
);
Assert::isFalse(isset($routes[1]['noAuth']), 'Publication must require authentication.');

Assert::same(
    true,
    $templateDefs['fields']['currentPublishedVersion']['audited'] ?? null,
    'The active-version switch must be retained in native audit history.',
);
Assert::same(true, $templateDefs['fields']['status']['audited'] ?? null, 'Publication status must remain audited.');
Assert::same(
    ['templateId', 'versionNumber'],
    $versionDefs['indexes']['templateVersion']['columns'] ?? null,
    'Concurrent version allocation requires the unique template/version index.',
);

$binding = file_get_contents("$moduleRoot/Binding.php");
$action = file_get_contents("$moduleRoot/Api/PostTemplatePublish.php");
$store = file_get_contents("$moduleRoot/Tools/DocumentBuilder/Publication/OrmPublicationStore.php");
$service = file_get_contents("$moduleRoot/Tools/DocumentBuilder/Publication/PublicationService.php");
$validation = file_get_contents("$moduleRoot/Tools/DocumentBuilder/Publication/PublicationValidationService.php");

foreach (compact('binding', 'action', 'store', 'service', 'validation') as $label => $source) {
    Assert::isTrue(is_string($source), "Could not read Phase 13 $label source.");
}

Assert::contains('bindImplementation(PublicationStore::class, OrmPublicationStore::class)', $binding, 'Publication store binding is missing.');
Assert::contains('implements Action', $action, 'Publication endpoint must implement Espo API Action.');
Assert::contains("Conflict::createWithBody('publicationConflict'", $action, 'Concurrent publication needs a structured 409 response.');
Assert::contains('new Forbidden(', $action, 'Publication permission failures must map to HTTP 403.');
Assert::contains('publicationBlocker', $action, 'Validation responses must expose the blocking category.');
Assert::contains('->forUpdate()', $store, 'Version allocation must occur under a template row lock.');
Assert::contains('getTransactionManager()->run(', $store, 'Version creation and activation must share one transaction.');
Assert::contains("'isCurrent' => true", file_get_contents("$moduleRoot/Tools/DocumentBuilder/TemplateVersion/TemplateVersionSnapshotFactory.php"), 'A new publication snapshot must start current.');
Assert::contains("\$currentVersion->set('isCurrent', false)", $store, 'The store must retire the prior current marker.');
Assert::contains("'currentPublishedVersionId' => \$versionId", $store, 'The template must switch to the new version in the transaction.');
Assert::contains('PublicationConflict', $service, 'A non-draft must be rejected after acquiring the row lock.');
Assert::contains('requirePublishable', $validation, 'Schema-only capabilities must fail closed during publication.');

foreach (['publishedBy', 'publishedAt', 'changeNote', 'checksum'] as $field) {
    Assert::isTrue(isset($versionDefs['fields'][$field]), "Immutable publication audit field $field is missing.");
}

foreach (['en_US', 'ro_RO'] as $locale) {
    $moduleI18n = $loader->json("i18n/$locale/DocumentBuilder.json");
    Assert::isTrue(isset($moduleI18n['errors']['publicationConflict']), "$locale publication conflict error is missing.");
}

echo "Phase 13 publication API and transaction contract tests passed.\n";
