<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValue;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValueState;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValueType;
use Espo\ORM\Entity;
use InvalidArgumentException;

final class EntityValueMapper
{
    public function map(Entity $record, DirectFieldResolution $field): VariableValue
    {
        $raw = $record->get($field->field);

        if ($raw === null) {
            return new VariableValue($field->valueType, VariableValueState::Missing);
        }

        if ($field->valueType === VariableValueType::Currency) {
            $raw = [
                'amount' => $this->number($raw),
                'currency' => $field->currencyField === null ? null : $record->get($field->currencyField),
            ];
        } elseif ($field->valueType === VariableValueType::Number) {
            $raw = $this->number($raw);
        }

        try {
            return new VariableValue($field->valueType, VariableValueState::Present, $raw);
        } catch (InvalidArgumentException) {
            return new VariableValue($field->valueType, VariableValueState::Invalid);
        }
    }

    private function number(mixed $value): mixed
    {
        if (!is_string($value) || !is_numeric($value)) {
            return $value;
        }

        $number = (float) $value;

        return is_finite($number) ? $number : $value;
    }
}
