<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use DocumentBuilder\Tests\Support\FixtureLoader;

require dirname(__DIR__) . '/bootstrap.php';

$extensionRoot = dirname(__DIR__, 2);
$moduleRoot = $extensionRoot . '/files/custom/Espo/Modules/DocumentBuilder';
$clientModuleRoot = $extensionRoot . '/files/client/custom/modules/document-builder';
$loader = new FixtureLoader($moduleRoot . '/Resources');
$clientRoutes = $loader->json('metadata/app/clientRoutes.json');
$clientMetadata = $loader->json('metadata/app/client.json');
$templateClient = $loader->json('metadata/clientDefs/DocumentBuilderTemplate.json');

$route = $clientRoutes['DocumentBuilderTemplate/editor/:id'] ?? [];
Assert::same('DocumentBuilderTemplate', $route['params']['controller'] ?? null, 'The editor route must dispatch through the template controller.');
Assert::same('editor', $route['params']['action'] ?? null, 'The editor route must dispatch the editor action.');
Assert::same(
    'document-builder:controllers/document-builder-template',
    $templateClient['controller'] ?? null,
    'Template routes must use the Document Builder record controller.',
);

$actions = array_column($templateClient['detailActionList'] ?? [], null, 'name');
$editorAction = $actions['openEditor'] ?? [];
Assert::same('edit', $editorAction['acl'] ?? null, 'The editor detail action must require edit access.');
Assert::same('isEditorVisible', $editorAction['checkVisibilityFunction'] ?? null, 'The editor action must have draft visibility logic.');

Assert::same(
    ['__APPEND__', 'client/custom/modules/document-builder/res/css/editor.css'],
    $clientMetadata['cssList'] ?? null,
    'Editor CSS must be appended without replacing EspoCRM styles.',
);

$controller = file_get_contents("$clientModuleRoot/src/controllers/document-builder-template.js");
$view = file_get_contents("$clientModuleRoot/src/views/editor/shell.js");
$handler = file_get_contents("$clientModuleRoot/src/handlers/template-lifecycle.js");
$template = file_get_contents("$clientModuleRoot/res/templates/editor/shell.tpl");
$css = file_get_contents("$clientModuleRoot/res/css/editor.css");

foreach (compact('controller', 'view', 'handler', 'template', 'css') as $label => $source) {
    Assert::isTrue(is_string($source), "Could not read Phase 15 $label source.");
}

Assert::contains("this.handleCheckAccess('edit')", $controller, 'The editor controller must reject missing scope edit access.');
Assert::contains("this.model.fetch({main: true})", $view, 'The editor must fetch the record through EspoCRM model APIs.');
Assert::contains("checkModel(this.model, 'edit')", $view, 'The editor must check record edit access after loading.');
Assert::contains("this.model.get('status') !== 'Draft'", $view, 'Direct non-draft access must be rejected.');
Assert::contains('abortLastFetch()', $view, 'Editor teardown must abort an in-flight model fetch.');
Assert::contains('super.remove()', $view, 'Editor teardown must delegate listener and DOM cleanup to the base view.');
Assert::contains('focus({preventScroll: true})', $view, 'The loaded or failed editor must receive a deterministic focus entry.');
Assert::contains('#DocumentBuilderTemplate/editor/${this.view.model.id}', $handler, 'The template action must navigate to the draft editor route.');
Assert::contains("this.view.model.get('status') === 'Draft'", $handler, 'The template action must only be visible for drafts.');

foreach (['toolbar', 'left', 'canvas-host', 'inspector', 'status'] as $region) {
    Assert::contains("document-builder-editor__$region", $template, "The editor $region region is missing.");
}

foreach (['isLoading', 'isError', 'editorEmptyCanvas'] as $stateMarker) {
    Assert::contains($stateMarker, $template, "The editor template is missing state marker $stateMarker.");
}

foreach (['en_US', 'ro_RO'] as $locale) {
    $i18n = $loader->json("i18n/$locale/DocumentBuilderTemplate.json");

    foreach (['editorLoading', 'editorAccessDenied', 'editorDraftOnly', 'editorLoadFailed', 'editorReady'] as $message) {
        Assert::isTrue(isset($i18n['messages'][$message]), "$locale editor message $message is missing.");
    }
}

echo "Phase 15 editor route, access, shell, state, focus, and teardown contracts passed.\n";
