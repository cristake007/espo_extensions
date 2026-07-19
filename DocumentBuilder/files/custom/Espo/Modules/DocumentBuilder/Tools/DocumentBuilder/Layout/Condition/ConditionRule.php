<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Condition;

use DateTimeImmutable;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableIdentity;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValueType;
use InvalidArgumentException;

final readonly class ConditionRule
{
    public function __construct(
        public VariableIdentity $identity,
        public VariableValueType $valueType,
        public ConditionOperator $operator,
        public mixed $operand,
    ) {
        $noOperand = in_array($operator, [
            ConditionOperator::Exists,
            ConditionOperator::Missing,
            ConditionOperator::IsTrue,
            ConditionOperator::IsFalse,
        ], true);
        $textOperator = in_array($operator, [ConditionOperator::Contains, ConditionOperator::StartsWith], true);
        $ordered = in_array($operator, [
            ConditionOperator::GreaterThan,
            ConditionOperator::GreaterOrEqual,
            ConditionOperator::LessThan,
            ConditionOperator::LessOrEqual,
        ], true);
        $booleanOperator = in_array($operator, [ConditionOperator::IsTrue, ConditionOperator::IsFalse], true);

        if (($noOperand && $operand !== null) ||
            ($textOperator && (!$this->supportsTextOperator($valueType) || !is_string($operand))) ||
            ($ordered && !$this->orderedOperand($operand, $valueType)) ||
            ($booleanOperator && $valueType !== VariableValueType::Boolean) ||
            (!$noOperand && !$textOperator && !$ordered && !$this->matchesType($operand, $valueType))) {
            throw new InvalidArgumentException('A condition operand is incompatible with its operator and type.');
        }
    }

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): self
    {
        $keys = ['identity', 'valueType', 'operator', 'operand'];

        if (array_is_list($value) || array_diff(array_keys($value), $keys) !== [] ||
            array_diff($keys, array_keys($value)) !== [] ||
            !is_array($value['identity']) || array_is_list($value['identity'])) {
            throw new InvalidArgumentException('A condition rule has an invalid structure.');
        }

        $type = is_string($value['valueType']) ? VariableValueType::tryFrom($value['valueType']) : null;
        $operator = is_string($value['operator']) ? ConditionOperator::tryFrom($value['operator']) : null;

        if ($type === null || $operator === null) {
            throw new InvalidArgumentException('A condition rule type or operator is unsupported.');
        }

        return new self(VariableIdentity::fromArray($value['identity']), $type, $operator, $value['operand']);
    }

    private function supportsTextOperator(VariableValueType $type): bool
    {
        return in_array($type, [VariableValueType::Text, VariableValueType::Enum, VariableValueType::MultiValue], true);
    }

    private function matchesType(mixed $value, VariableValueType $type): bool
    {
        return match ($type) {
            VariableValueType::Boolean => is_bool($value),
            VariableValueType::Number, VariableValueType::Currency => $this->isFiniteNumber($value),
            VariableValueType::MultiValue => is_string($value) || is_int($value) || is_bool($value) ||
                (is_float($value) && is_finite($value)),
            default => is_string($value) && mb_strlen($value) <= 1000 &&
                preg_match('/[\x00-\x1F\x7F]/', $value) !== 1,
        };
    }

    private function orderedOperand(mixed $value, VariableValueType $type): bool
    {
        return match ($type) {
            VariableValueType::Number, VariableValueType::Currency => $this->isFiniteNumber($value),
            VariableValueType::Date => is_string($value) && $this->validDate($value),
            VariableValueType::DateTime => is_string($value) && $this->validDateTime($value),
            default => false,
        };
    }

    private function isFiniteNumber(mixed $value): bool
    {
        return is_int($value) || (is_float($value) && is_finite($value));
    }

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function validDateTime(string $value): bool
    {
        if (strlen($value) > 35 ||
            preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})\z/D', $value) !== 1) {
            return false;
        }

        try {
            new DateTimeImmutable($value);

            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
