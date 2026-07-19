<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;

require dirname(__DIR__) . '/bootstrap.php';
$root = dirname(__DIR__, 2);
$module = "$root/files/custom/Espo/Modules/DocumentBuilder";
$client = "$root/files/client/custom/modules/document-builder";
$schema = json_decode(file_get_contents("$module/Resources/jsonSchema/document-builder-layout-v1.json"), true, flags: JSON_THROW_ON_ERROR);
$validator = file_get_contents("$module/Tools/DocumentBuilder/Layout/LayoutValidator.php");
$formatter = file_get_contents("$module/Tools/DocumentBuilder/DataSource/Variable/VariableFormatter.php");
$system = file_get_contents("$module/Tools/DocumentBuilder/DataSource/Variable/SystemVariableResolver.php");
$presentation = file_get_contents("$client/src/editor/variables/variable-presentation.js");
$shell = file_get_contents("$client/src/views/editor/shell.js");
$template = file_get_contents("$client/res/templates/editor/shell.tpl");

Assert::same('#/$defs/variablePresentation', $schema['$defs']['inlineVariable']['properties']['presentation']['$ref'] ?? null, 'Inline variable presentation schema is missing.');
Assert::contains('isVariablePresentation', $validator, 'Server layout validation must own presentation syntax.');
Assert::contains('MissingValueDisposition::Failure', $formatter, 'Invalid or required values must have an explicit failure state.');
Assert::contains("SystemVariableResult::placeholder('pageNumber')", $system, 'Page variables must remain renderer-owned placeholders.');
foreach (['hideElement', 'hideRow', 'hideSection', 'warning', 'required'] as $policy) {
    Assert::contains("'$policy'", $presentation, "Missing client policy $policy.");
}
Assert::isFalse((bool) preg_match('/\b(eval|Function)\s*\(/', $presentation . $formatter), 'Variable presentation must not execute expressions.');
Assert::contains('changeVariablePresentation', $shell, 'The variable presentation inspector is not wired.');
Assert::contains('data-variable-presentation', $template, 'The variable presentation inspector controls are missing.');

echo "Phase 27 finite formatting, missing-policy, system-placeholder, and inspector contracts passed.\n";
