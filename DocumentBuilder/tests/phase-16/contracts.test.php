<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;

require dirname(__DIR__) . '/bootstrap.php';

$extensionRoot = dirname(__DIR__, 2);
$clientRoot = $extensionRoot . '/files/client/custom/modules/document-builder/src';
$moduleFiles = [
    'editor/state/json.js',
    'editor/state/node-tree.js',
    'editor/state/stable-id-factory.js',
    'editor/state/editor-state.js',
    'editor/commands/command.js',
    'editor/commands/add-node.js',
    'editor/commands/remove-node.js',
    'editor/commands/move-node.js',
    'editor/commands/update-node.js',
    'editor/commands/duplicate-node.js',
];

$sources = [];

foreach ($moduleFiles as $relativePath) {
    $source = file_get_contents("$clientRoot/$relativePath");
    Assert::isTrue(is_string($source), "Could not read Phase 16 module $relativePath.");
    $sources[$relativePath] = $source;
}

$state = $sources['editor/state/editor-state.js'];
$tree = $sources['editor/state/node-tree.js'];
$idFactory = $sources['editor/state/stable-id-factory.js'];
$command = $sources['editor/commands/command.js'];
$combined = implode("\n", $sources);

Assert::contains('const HISTORY_LIMIT = 100', $state, 'Editor history must retain exactly 100 structural changes.');
Assert::contains('this.savedBaseline', $state, 'Editor state must own the saved baseline.');
Assert::contains('isDirty()', $state, 'Editor state must calculate dirty state.');
Assert::contains('this.future = []', $state, 'A new command must invalidate redo history.');
Assert::contains('cleanupSelection()', $state, 'Structural changes must clean stale selection.');
Assert::contains('getLayout()', $state, 'Editor layout reads must go through the state owner.');
Assert::contains('return Json.clone(this.layout)', $state, 'Editor state must not expose its mutable layout.');
Assert::contains('STABLE_ID_PATTERN', $tree, 'Client IDs must use the canonical Phase 08 safe-ID contract.');
Assert::contains('getRandomValues', $idFactory, 'Runtime stable IDs must use browser cryptographic randomness.');
Assert::contains('instances can only be executed once', $command, 'Commands must be explicit one-shot mutations.');
Assert::isFalse(str_contains($combined, 'Espo.Ajax'), 'Server requests must never be editor history commands.');
Assert::isFalse(str_contains($combined, 'document.querySelector'), 'Editor commands must not mutate the DOM.');

$shell = file_get_contents("$clientRoot/views/editor/shell.js");
Assert::isTrue(is_string($shell), 'Could not read the editor shell.');
Assert::contains('new EditorState(', $shell, 'The editor shell must initialize the authoritative state owner.');
Assert::contains('executeCommand(command)', $shell, 'Later editor views need one command dispatch boundary.');
Assert::contains('this.editorState.undo()', $shell, 'The toolbar must delegate undo to editor state.');
Assert::contains('this.editorState.redo()', $shell, 'The toolbar must delegate redo to editor state.');

echo "Phase 16 state ownership, stable-ID, command, history, and client-boundary contracts passed.\n";
