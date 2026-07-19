<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview;

final readonly class PreviewResult
{
    /** @param list<PreviewValue> $values */
    public function __construct(
        public string $templateId,
        public int $revision,
        public PreviewMode $mode,
        public array $values,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'templateId' => $this->templateId,
            'revision' => $this->revision,
            'mode' => $this->mode->value,
            'values' => array_map(static fn (PreviewValue $value): array => $value->toArray(), $this->values),
        ];
    }
}
