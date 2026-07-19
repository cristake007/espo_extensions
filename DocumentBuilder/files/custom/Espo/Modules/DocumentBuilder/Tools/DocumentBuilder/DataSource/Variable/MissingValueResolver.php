<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable;

use InvalidArgumentException;

final class MissingValueResolver
{
    public function resolve(
        VariableValueState $state,
        MissingValuePolicy $policy,
        ?string $fallback,
    ): FormattedVariableValue {
        if ($state === VariableValueState::Present) {
            throw new InvalidArgumentException('A present value does not require missing-value resolution.');
        }

        if ($state === VariableValueState::Invalid) {
            return new FormattedVariableValue($state, MissingValueDisposition::Failure, null);
        }

        return match ($policy) {
            MissingValuePolicy::Empty => new FormattedVariableValue(
                $state,
                MissingValueDisposition::Display,
                '',
            ),
            MissingValuePolicy::Fallback => new FormattedVariableValue(
                $state,
                $fallback === null ? MissingValueDisposition::Failure : MissingValueDisposition::Display,
                $fallback,
            ),
            MissingValuePolicy::HideElement => new FormattedVariableValue(
                $state,
                MissingValueDisposition::HideElement,
                null,
            ),
            MissingValuePolicy::HideRow => new FormattedVariableValue(
                $state,
                MissingValueDisposition::HideRow,
                null,
            ),
            MissingValuePolicy::HideSection => new FormattedVariableValue(
                $state,
                MissingValueDisposition::HideSection,
                null,
            ),
            MissingValuePolicy::Warning => new FormattedVariableValue(
                $state,
                MissingValueDisposition::Warning,
                $fallback ?? '',
            ),
            MissingValuePolicy::Required => new FormattedVariableValue(
                $state,
                MissingValueDisposition::Failure,
                null,
            ),
        };
    }
}
