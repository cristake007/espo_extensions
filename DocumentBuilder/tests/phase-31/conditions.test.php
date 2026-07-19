<?php

declare(strict_types=1);

use DocumentBuilder\Tests\Support\Assert;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\EntityResolutionResult;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\ResolvedEntityValue;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\SourceProvenance;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableIdentity;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValue;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValueState;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValueType;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Condition\ConditionEvaluator;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Condition\RequiredVariableFailure;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Condition\RequiredVariableValidator;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Condition\VisibilityCondition;

require dirname(__DIR__) . '/bootstrap.php';
$root = dirname(__DIR__, 2) . '/files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder';
foreach (['VariableSource.php', 'VariableType.php', 'VariableUsage.php', 'VariablePath.php',
    'VariableIdentity.php', 'VariableValueType.php', 'VariableValueState.php', 'VariableValue.php'] as $file) {
    require "$root/DataSource/Variable/$file";
}
foreach (['SourceProvenance.php', 'ResolvedEntityValue.php', 'EntityResolutionResult.php'] as $file) {
    require "$root/DataSource/EntityResolver/$file";
}
foreach (['ConditionMode.php', 'ConditionTarget.php', 'ConditionOperator.php', 'ConditionRule.php',
    'VisibilityCondition.php', 'ConditionEvaluation.php', 'ConditionEvaluator.php',
    'RequiredVariableFailure.php', 'RequiredVariableValidator.php'] as $file) {
    require "$root/Layout/Condition/$file";
}

$identityArray = ['source'=>'entity', 'type'=>'direct', 'entityType'=>'Contact', 'path'=>['name']];
$identity = VariableIdentity::fromArray($identityArray);
$condition = static fn (string $type, string $operator, mixed $operand = null, string $target = 'element', string $mode = 'all'): VisibilityCondition =>
    VisibilityCondition::fromArray(['target'=>$target, 'mode'=>$mode, 'rules'=>[[
        'identity'=>$identityArray, 'valueType'=>$type, 'operator'=>$operator, 'operand'=>$operand,
    ]]]);
$evaluate = static function (VisibilityCondition $condition, ?VariableValue $value): bool {
    return (new ConditionEvaluator())->evaluate($condition, static fn (array $identity): ?VariableValue => $value)->visible;
};

$present = static fn (VariableValueType $type, mixed $value): VariableValue =>
    new VariableValue($type, VariableValueState::Present, $value);
Assert::isTrue($evaluate($condition('text', 'exists'), $present(VariableValueType::Text, 'Ana')), 'Exists failed.');
Assert::isTrue($evaluate($condition('text', 'missing'), new VariableValue(VariableValueType::Text, VariableValueState::Missing)), 'Missing failed.');
Assert::isFalse($evaluate($condition('text', 'missing'), new VariableValue(VariableValueType::Text, VariableValueState::Forbidden)), 'Restricted values must not masquerade as missing.');
Assert::isTrue($evaluate($condition('text', 'equals', 'Ana'), $present(VariableValueType::Text, 'Ana')), 'Text equals failed.');
Assert::isTrue($evaluate($condition('enum', 'notEquals', 'inactive'), $present(VariableValueType::Enum, 'active')), 'Enum not-equals failed.');
Assert::isTrue($evaluate($condition('text', 'contains', 'na'), $present(VariableValueType::Text, 'Ana')), 'Text contains failed.');
Assert::isTrue($evaluate($condition('multiValue', 'contains', 'active'), $present(VariableValueType::MultiValue, ['active'])), 'Multi-value contains failed.');
Assert::isTrue($evaluate($condition('text', 'startsWith', 'An'), $present(VariableValueType::Text, 'Ana')), 'Starts-with failed.');
Assert::isTrue($evaluate($condition('number', 'greaterThan', 4), $present(VariableValueType::Number, 5)), 'Number ordering failed.');
Assert::isTrue($evaluate($condition('number', 'equals', 5), $present(VariableValueType::Number, 5.0)), 'Equivalent finite numbers must compare consistently.');
Assert::isTrue($evaluate($condition('currency', 'greaterOrEqual', 5), $present(VariableValueType::Currency, ['amount'=>5, 'currency'=>'RON'])), 'Currency ordering failed.');
Assert::isTrue($evaluate($condition('date', 'lessThan', '2025-02-01'), $present(VariableValueType::Date, '2025-01-01')), 'Date ordering failed.');
Assert::isTrue($evaluate($condition('datetime', 'lessOrEqual', '2025-01-01T12:00:00+00:00'), $present(VariableValueType::DateTime, '2025-01-01T12:00:00+00:00')), 'Datetime ordering failed.');
Assert::isTrue($evaluate($condition('boolean', 'isTrue'), $present(VariableValueType::Boolean, true)), 'Boolean true failed.');
Assert::isTrue($evaluate($condition('boolean', 'isFalse'), $present(VariableValueType::Boolean, false)), 'Boolean false failed.');
Assert::same('parent', $condition('text', 'exists', null, 'parent')->target->value, 'Parent target was lost.');

$allAny = static fn (string $mode): VisibilityCondition => VisibilityCondition::fromArray([
    'target'=>'element', 'mode'=>$mode, 'rules'=>[
        ['identity'=>$identityArray, 'valueType'=>'text', 'operator'=>'exists', 'operand'=>null],
        ['identity'=>$identityArray, 'valueType'=>'text', 'operator'=>'equals', 'operand'=>'other'],
    ],
]);
Assert::isFalse($evaluate($allAny('all'), $present(VariableValueType::Text, 'Ana')), 'All mode failed.');
Assert::isTrue($evaluate($allAny('any'), $present(VariableValueType::Text, 'Ana')), 'Any mode failed.');

Assert::throws(fn () => $condition('boolean', 'contains', 'x'), InvalidArgumentException::class, 'Invalid operator/type was accepted.');
Assert::throws(fn () => $condition('number', 'greaterThan', '4'), InvalidArgumentException::class, 'Invalid ordered operand was accepted.');
Assert::throws(fn () => $condition('date', 'lessThan', 'not-a-date'), InvalidArgumentException::class, 'Invalid date operand was accepted.');
Assert::throws(fn () => VisibilityCondition::fromArray(['target'=>'element', 'mode'=>'all', 'rules'=>array_fill(0, 26, [
    'identity'=>$identityArray, 'valueType'=>'text', 'operator'=>'exists', 'operand'=>null,
])]), InvalidArgumentException::class, 'Unbounded condition group was accepted.');
Assert::throws(fn () => VisibilityCondition::fromArray(['target'=>'element', 'mode'=>'all', 'rules'=>[], 'expression'=>'php']), InvalidArgumentException::class, 'Arbitrary expression property was accepted.');

$layout = ['sections'=>[['type'=>'paragraph', 'content'=>[['type'=>'variable', 'identity'=>$identityArray,
    'presentation'=>['missing'=>'required']]]]]];
$provenance = new SourceProvenance('Contact', 'contact1', 'name');
$validator = new RequiredVariableValidator();
$validator->validate($layout, new EntityResolutionResult([
    new ResolvedEntityValue($identity, $present(VariableValueType::Text, 'Ana'), $provenance),
]));
Assert::throws(fn () => $validator->validate($layout, new EntityResolutionResult([
    new ResolvedEntityValue($identity, new VariableValue(VariableValueType::Text, VariableValueState::Forbidden), $provenance),
])), RequiredVariableFailure::class, 'A restricted required value did not stop generation.');

echo "Phase 31 bounded condition and required-variable tests passed.\n";
