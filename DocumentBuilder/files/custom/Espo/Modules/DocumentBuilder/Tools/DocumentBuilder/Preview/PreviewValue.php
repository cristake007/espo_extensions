<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\SourceProvenance;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableIdentity;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValue;

final readonly class PreviewValue
{
    public function __construct(
        public VariableIdentity $identity,
        public VariableValue $value,
        public PreviewValueOrigin $origin,
        public ?SourceProvenance $provenance = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $state = $this->value->state->value === 'forbidden' ? 'restricted' : $this->value->state->value;

        return [
            'identity' => $this->identity->toArray(),
            'type' => $this->value->type->value,
            'state' => $state,
            'value' => $this->value->value,
            'origin' => $this->origin->value,
            'provenance' => $this->provenance?->toArray(),
        ];
    }
}
