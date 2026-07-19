<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable;

final readonly class FormattedVariableValue
{
    public function __construct(
        public VariableValueState $state,
        public MissingValueDisposition $disposition,
        public ?string $text,
    ) {}

    /** @return array{state: string, disposition: string, text: ?string} */
    public function toArray(): array
    {
        return [
            'state' => $this->state->value,
            'disposition' => $this->disposition->value,
            'text' => $this->text,
        ];
    }
}
