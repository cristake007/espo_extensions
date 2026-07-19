<?php
declare(strict_types=1);
use DocumentBuilder\Tests\Support\Assert;
require dirname(__DIR__) . '/bootstrap.php';
$root = dirname(__DIR__, 2);
$client = "$root/files/client/custom/modules/document-builder";
$renderer = file_get_contents("$client/src/editor/renderer/browser-renderer.js");
$validator = file_get_contents("$client/src/editor/validation/editor-validator.js");
$shell = file_get_contents("$client/src/views/editor/shell.js");
$template = file_get_contents("$client/res/templates/editor/shell.tpl");
$css = file_get_contents("$client/res/css/editor.css");
$canvas = file_get_contents("$client/src/editor/canvas/document-canvas.js");
$detailLayout = json_decode(
    file_get_contents("$root/files/custom/Espo/Modules/DocumentBuilder/Resources/layouts/DocumentBuilderTemplate/detail.json"),
    true,
    flags: JSON_THROW_ON_ERROR,
);
Assert::contains('Json.clone(layout)', $renderer, 'The browser renderer must read a detached layout.');
Assert::isFalse(str_contains($renderer, 'EditorState'), 'The browser renderer must not own editor state.');
Assert::contains("severity: 'error'", $validator, 'Blocking validation severity is missing.');
Assert::contains("severity: 'warning'", $validator, 'Warning validation severity is missing.');
Assert::contains('data-action="focusValidationIssue"', $template, 'Validation issues must support focus navigation.');
Assert::contains("'aria-keyshortcuts', 'ArrowUp ArrowDown Home End'", $canvas, 'Canvas keyboard traversal is not exposed.');
Assert::isFalse(str_contains($template, 'document-builder-editor__node-badge'), 'Technical type badges remain visible.');
Assert::contains('fa-times-circle', $template, 'Errors need a non-color indication.');
Assert::contains('confirmRemoveComplexNode', $shell, 'Complex deletion must be confirmed.');
Assert::contains(':focus-visible', $css, 'Keyboard focus styling is missing.');
foreach ($detailLayout as $panelIndex => $panel) {
    foreach (($panel['rows'] ?? []) as $rowIndex => $row) {
        foreach ($row as $cellIndex => $cell) {
            Assert::isTrue(
                $cell === false || is_array($cell),
                "Detail layout panel $panelIndex row $rowIndex cell $cellIndex must be a field definition or false.",
            );
        }
    }
}
echo "Phase 23 renderer, validation, navigation, deletion, and accessibility contracts passed.\n";
