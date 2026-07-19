<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Condition;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\EntityResolutionResult;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableIdentity;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValueState;
use InvalidArgumentException;

final readonly class RequiredVariableValidator
{
    /** @param array<string, mixed> $layout */
    public function validate(array $layout, EntityResolutionResult $values): void
    {
        $failures = [];
        $this->walk($layout, $values, $failures);

        if ($failures !== []) {
            throw new RequiredVariableFailure(array_keys($failures));
        }
    }

    /** @param array<string, true> $failures */
    private function walk(mixed $value, EntityResolutionResult $values, array &$failures): void
    {
        if (!is_array($value)) {
            return;
        }

        if (($value['type'] ?? null) === 'variable' &&
            ($value['presentation']['missing'] ?? null) === 'required') {
            $rawIdentity = $value['identity'] ?? null;

            if (!is_array($rawIdentity) || array_is_list($rawIdentity)) {
                throw new InvalidArgumentException('A required variable identity is invalid.');
            }

            $identity = VariableIdentity::fromArray($rawIdentity);
            $resolved = $values->find($identity);

            if ($resolved === null || $resolved->value->state !== VariableValueState::Present) {
                $failures[json_encode($identity->toArray(), JSON_THROW_ON_ERROR)] = true;
            }

            return;
        }

        foreach ($value as $item) {
            $this->walk($item, $values, $failures);
        }
    }
}
