<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use DocumentBuilder\Tests\Support\FixtureLoader;

require dirname(__DIR__) . '/bootstrap.php';

$extensionRoot = dirname(__DIR__, 2);
$clientRoot = $extensionRoot . '/files/client/custom/modules/document-builder';
$moduleRoot = $extensionRoot . '/files/custom/Espo/Modules/DocumentBuilder';
$loader = new FixtureLoader($moduleRoot . '/Resources');

$api = file_get_contents("$clientRoot/src/services/draft-api.js");
$coordinator = file_get_contents("$clientRoot/src/editor/save/draft-save-coordinator.js");
$precheck = file_get_contents("$clientRoot/src/editor/validation/layout-precheck.js");
$keyboard = file_get_contents("$clientRoot/src/editor/save/keyboard.js");
$guard = file_get_contents("$clientRoot/src/editor/save/dirty-guard.js");
$shell = file_get_contents("$clientRoot/src/views/editor/shell.js");
$dialog = file_get_contents("$clientRoot/src/views/editor/modals/revision-conflict.js");
$template = file_get_contents("$clientRoot/res/templates/editor/shell.tpl");

foreach (compact('api', 'coordinator', 'precheck', 'keyboard', 'guard', 'shell', 'dialog', 'template') as $label => $source) {
    Assert::isTrue(is_string($source), "Could not read Phase 17 $label source.");
}

Assert::contains('putRequest(', $api, 'Manual draft persistence must use the Phase 12 PUT contract.');
Assert::contains('layout: JSON.stringify(layout)', $api, 'The draft API must send JSON text to the Phase 12 endpoint.');
Assert::contains('expectedRevision', $api, 'Every draft save must carry an expected revision.');
Assert::contains('confirmSourceChange: false', $api, 'Phase 17 must not silently confirm a source change.');
Assert::contains('this.precheck.check(layout)', $coordinator, 'Client schema precheck must run before the request.');
Assert::contains('PAGE_DIMENSIONS_MM', $precheck, 'Client precheck must validate known page dimensions.');
Assert::contains('document.page.printableWidth', $precheck, 'Client precheck must reject impossible horizontal margins.');
Assert::contains('validateDefaults', $precheck, 'Client precheck must validate document defaults.');
Assert::contains('dataSource.entity.structure', $precheck, 'Client precheck must require entity-source fields.');
Assert::contains('dataSource.spreadsheet.structure', $precheck, 'Client precheck must require spreadsheet-source fields.');
Assert::contains('acceptSavedLayout(result.layout)', $coordinator, 'Success must accept the server-normalized layout.');
Assert::contains("return {status: 'conflict', conflict}", $coordinator, 'Revision conflicts must stop for user action.');
Assert::contains('retryConflict(conflict)', $coordinator, 'Conflict retry must be a separate explicit operation.');
Assert::contains('actualRevision', $coordinator, 'Explicit retry must use the current server revision.');
Assert::isFalse(str_contains($coordinator, 'setInterval'), 'Phase 17 must not introduce autosave.');
Assert::isFalse(str_contains($coordinator, 'setTimeout'), 'Phase 17 must remain manual-save only.');

Assert::contains("document.addEventListener('keydown'", $shell, 'The editor must register its save shortcut.');
Assert::contains("document.removeEventListener('keydown'", $shell, 'Editor teardown must remove the save shortcut listener.');
Assert::contains('event.preventDefault()', $shell, 'Ctrl/Cmd+S must suppress browser save behavior.');
Assert::contains('this.actionSave()', $shell, 'The keyboard shortcut must invoke manual save.');
Assert::contains('new DirtyGuard(', $shell, 'The editor must connect dirty state to router leave protection.');
Assert::contains('this.dirtyGuard.dispose()', $shell, 'Editor teardown must remove leave protection.');
Assert::contains("views/editor/modals/revision-conflict", $shell, 'A revision conflict must open the actionable dialog.');
Assert::contains("this.listenToOnce(view, 'retry'", $shell, 'The conflict dialog must offer explicit retry.');
Assert::contains("this.listenToOnce(view, 'reload'", $shell, 'The conflict dialog must offer explicit reload.');
Assert::contains('data-action="save"', $template, 'The toolbar save button is not wired.');
Assert::contains('role="alert"', $template, 'Save failures must be announced visibly.');

Assert::contains('addLeaveOutObject', $guard, 'Dirty state must protect client navigation and browser unload.');
Assert::contains('removeLeaveOutObject', $guard, 'Saved state must remove leave protection.');
Assert::contains("String(event.key).toLowerCase() === 's'", $keyboard, 'The keyboard matcher must recognize S case-insensitively.');
Assert::contains("label: 'Retry Save'", $dialog, 'The conflict dialog is missing explicit retry.');
Assert::contains("label: 'Reload Draft'", $dialog, 'The conflict dialog is missing explicit reload.');

foreach (['en_US', 'ro_RO'] as $locale) {
    $i18n = $loader->json("i18n/$locale/DocumentBuilderTemplate.json");

    foreach ([
        'editorSaving',
        'editorSaved',
        'editorClientValidationFailed',
        'editorRevisionConflict',
        'editorRetryWarning',
        'editorReloadFailed',
    ] as $message) {
        Assert::isTrue(isset($i18n['messages'][$message]), "$locale Phase 17 message $message is missing.");
    }
}

echo "Phase 17 manual-save, revision-conflict, shortcut, leave-warning, and error-display contracts passed.\n";
