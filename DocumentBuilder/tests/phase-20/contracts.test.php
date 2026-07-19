<?php
declare(strict_types=1);
use DocumentBuilder\Tests\Support\Assert;
require dirname(__DIR__) . '/bootstrap.php';
$root = dirname(__DIR__, 2);
$client = "$root/files/client/custom/modules/document-builder";
$rich = file_get_contents("$client/src/editor/content/rich-text.js");
$shell = file_get_contents("$client/src/views/editor/shell.js");
$template = file_get_contents("$client/res/templates/editor/shell.tpl");
Assert::contains("'dompurify'", $rich, 'DOMPurify defense in depth is missing.');
Assert::contains('textContent', $rich, 'Rich text must render source values as text.');
Assert::isFalse(str_contains($rich, 'innerHTML'), 'Rich text must not expose an HTML rendering path.');
Assert::contains("getData('text/plain')", $shell, 'Paste must consume plain text only.');
Assert::isFalse(str_contains($shell, "getData('text/html')"), 'HTML paste must not be consumed.');
foreach (['heading', 'static-text', 'paragraph'] as $type) Assert::contains("data-library-type=\"$type\"", $template, "Missing $type library item.");
Assert::contains('new UpdateNodeCommand', $shell, 'Content edits must participate in undo/redo.');
echo "Phase 20 safe editor contracts passed.\n";
