<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValue;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValueState;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValueType;

final readonly class TypeAwareSampleGenerator
{
    public function generate(VariableValueType $type): VariableValue
    {
        $value = match ($type) {
            VariableValueType::Text => 'Exemplu',
            VariableValueType::Date => '2025-01-15',
            VariableValueType::DateTime => '2025-01-15T12:30:00+00:00',
            VariableValueType::Number => 1234.5,
            VariableValueType::Currency => ['amount' => 1234.5, 'currency' => 'RON'],
            VariableValueType::Boolean => true,
            VariableValueType::Enum => 'sample',
            VariableValueType::MultiValue => ['sample', 'secondary'],
        };

        return new VariableValue($type, VariableValueState::Present, $value);
    }
}
