<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Condition;

use DateTimeImmutable;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValue;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValueState;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValueType;

final readonly class ConditionEvaluator
{
    /** @param callable(array<string, mixed>): ?VariableValue $resolve */
    public function evaluate(VisibilityCondition $condition, callable $resolve): ConditionEvaluation
    {
        $results = array_map(
            fn (ConditionRule $rule): bool => $this->evaluateRule(
                $rule,
                $resolve($rule->identity->toArray()),
            ),
            $condition->rules,
        );
        $visible = $condition->mode === ConditionMode::All ?
            !in_array(false, $results, true) : in_array(true, $results, true);

        return new ConditionEvaluation($visible, $condition->target);
    }

    private function evaluateRule(ConditionRule $rule, ?VariableValue $value): bool
    {
        $state = $value?->state;
        $present = $state === VariableValueState::Present && $value->type === $rule->valueType;

        if ($rule->operator === ConditionOperator::Exists) {
            return $present;
        }

        if ($rule->operator === ConditionOperator::Missing) {
            return $state === null || $state === VariableValueState::Missing;
        }

        if (!$present) {
            return false;
        }

        $actual = $rule->valueType === VariableValueType::Currency ? $value->value['amount'] : $value->value;

        if (in_array($rule->valueType, [VariableValueType::Date, VariableValueType::DateTime], true) &&
            in_array($rule->operator, [
                ConditionOperator::GreaterThan,
                ConditionOperator::GreaterOrEqual,
                ConditionOperator::LessThan,
                ConditionOperator::LessOrEqual,
            ], true)) {
            try {
                $actual = (new DateTimeImmutable($actual))->getTimestamp();
                $operand = (new DateTimeImmutable($rule->operand))->getTimestamp();
            } catch (\Exception) {
                return false;
            }
        } else {
            $operand = $rule->operand;
        }

        if (in_array($rule->valueType, [VariableValueType::Number, VariableValueType::Currency], true) &&
            in_array($rule->operator, [ConditionOperator::Equals, ConditionOperator::NotEquals], true)) {
            $actual = (float) $actual;
            $operand = (float) $operand;
        }

        return match ($rule->operator) {
            ConditionOperator::Equals => $actual === $operand,
            ConditionOperator::NotEquals => $actual !== $operand,
            ConditionOperator::Contains => is_array($actual) ?
                in_array($operand, $actual, true) :
                (is_string($actual) && str_contains($actual, $operand)),
            ConditionOperator::StartsWith => is_string($actual) && str_starts_with($actual, $operand),
            ConditionOperator::GreaterThan => $actual > $operand,
            ConditionOperator::GreaterOrEqual => $actual >= $operand,
            ConditionOperator::LessThan => $actual < $operand,
            ConditionOperator::LessOrEqual => $actual <= $operand,
            ConditionOperator::IsTrue => $actual === true,
            ConditionOperator::IsFalse => $actual === false,
            default => false,
        };
    }
}
