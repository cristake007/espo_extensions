<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree;

final readonly class ResolvedInline
{
    /** @param list<string> $marks @param array<string, scalar>|null $provenance */
    public function __construct(
        public string $type,
        public string $text,
        public array $marks = [],
        public ?string $color = null,
        public ?array $provenance = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type,
            'text' => $this->text,
            'marks' => $this->marks,
            'color' => $this->color,
            'provenance' => $this->provenance,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
