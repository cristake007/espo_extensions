<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable;

use InvalidArgumentException;

final readonly class VariableValue
{
    private const MAX_TEXT_LENGTH = 10000;

    public function __construct(
        public VariableValueType $type,
        public VariableValueState $state,
        public mixed $value = null,
    ) {
        if ($state !== VariableValueState::Present) {
            if ($value !== null) {
                throw new InvalidArgumentException('A non-present variable value cannot contain data.');
            }

            return;
        }

        $valid = match ($type) {
            VariableValueType::Text => $this->isBoundedText($value, self::MAX_TEXT_LENGTH),
            VariableValueType::Date,
            VariableValueType::DateTime => $this->isBoundedText($value, 35),
            VariableValueType::Enum => $this->isBoundedText($value, 200),
            VariableValueType::Number => $this->isFiniteNumber($value),
            VariableValueType::Currency => $this->isCurrency($value),
            VariableValueType::Boolean => is_bool($value),
            VariableValueType::MultiValue => $this->isMultiValue($value),
        };

        if (!$valid) {
            throw new InvalidArgumentException('A present variable value does not match its declared type.');
        }
    }

    private function isFiniteNumber(mixed $value): bool
    {
        return is_int($value) || (is_float($value) && is_finite($value));
    }

    private function isBoundedText(mixed $value, int $maximum): bool
    {
        return is_string($value) && mb_strlen($value) <= $maximum &&
            preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) !== 1;
    }

    private function isCurrency(mixed $value): bool
    {
        return is_array($value) && !array_is_list($value) && count($value) === 2 &&
            array_diff(array_keys($value), ['amount', 'currency']) === [] &&
            array_diff(['amount', 'currency'], array_keys($value)) === [] &&
            $this->isFiniteNumber($value['amount']) &&
            is_string($value['currency']) &&
            preg_match('/\A[A-Z]{3}\z/D', $value['currency']) === 1;
    }

    private function isMultiValue(mixed $value): bool
    {
        if (!is_array($value) || !array_is_list($value) || count($value) > 100) {
            return false;
        }

        $characters = 0;

        foreach ($value as $item) {
            if (!is_string($item) && !is_int($item) && !is_bool($item) &&
                !(is_float($item) && is_finite($item))) {
                return false;
            }

            $characters += mb_strlen((string) $item);

            if ($characters > self::MAX_TEXT_LENGTH ||
                (is_string($item) && !$this->isBoundedText($item, self::MAX_TEXT_LENGTH))) {
                return false;
            }
        }

        return true;
    }
}
