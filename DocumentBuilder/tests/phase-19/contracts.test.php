<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;

require dirname(__DIR__) . '/bootstrap.php';

$root = dirname(__DIR__, 2);
$client = "$root/files/client/custom/modules/document-builder";
$module = "$root/files/custom/Espo/Modules/DocumentBuilder";
$shell = file_get_contents("$client/src/views/editor/shell.js");
$template = file_get_contents("$client/res/templates/editor/shell.tpl");
$flow = file_get_contents("$client/src/editor/flow/flow-structure.js");
$provider = file_get_contents("$module/Tools/DocumentBuilder/Draft/ConfiguredLayoutProcessorProvider.php");

foreach (compact('shell', 'template', 'flow', 'provider') as $label => $source) {
    Assert::isTrue(is_string($source), "Could not read Phase 19 $label source.");
}

Assert::contains('NodeRegistry::phase19()', $provider, 'Draft saves must use the Phase 19 node registry.');
Assert::contains('CapabilityRegistry::phase19()', $provider, 'Draft saves must activate flow capability.');
Assert::contains('new AddFlowNodeCommand', $shell, 'Flow insertion must use command history.');
Assert::contains('new MoveFlowNodeCommand', $shell, 'Flow reordering must use command history.');
Assert::contains('new UpdateNodeCommand', $shell, 'Flow styling must use command history.');
Assert::contains('handleFlowDragEnd', $shell, 'Canceled drags must clear transient state.');
Assert::contains('event.originalEvent?.dataTransfer', $shell, 'jQuery drag events must use the native data-transfer object.');
Assert::contains('data-flow-drop="inside"', $template, 'Nested container drop targets are missing.');
Assert::contains('flowBreadcrumbs', $template, 'Hierarchy breadcrumbs are missing.');
Assert::contains('data-flow-setting="marginLeft"', $template, 'Per-edge margin controls are missing.');
Assert::contains('data-flow-setting="paddingRight"', $template, 'Per-edge padding controls are missing.');
Assert::contains('data-flow-setting="keepTogether"', $template, 'Keep-together control is missing.');
Assert::contains('data-flow-setting="startNewPage"', $template, 'Start-new-page control is missing.');
Assert::contains('maxNestingDepth', $flow, 'Client nesting limits are missing.');
Assert::contains('maxElements', $flow, 'Client element limits are missing.');

echo "Phase 19 flow library, drag/drop, breadcrumbs, inspector, and command contracts passed.\n";
