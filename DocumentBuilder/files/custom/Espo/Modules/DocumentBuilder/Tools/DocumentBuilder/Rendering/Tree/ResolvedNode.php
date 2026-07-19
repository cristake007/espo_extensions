<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree;

final readonly class ResolvedNode
{
    /**
     * @param array<string, mixed> $style
     * @param array<string, mixed> $attributes
     * @param list<ResolvedInline> $inline
     * @param list<ResolvedNode> $children
     * @param list<array<string, mixed>> $mediaReferences
     * @param list<array<string, mixed>> $collectionSlots
     */
    public function __construct(
        public string $id,
        public string $type,
        public array $style,
        public array $attributes,
        public array $inline = [],
        public array $children = [],
        public bool $conditionMatched = true,
        public array $mediaReferences = [],
        public array $collectionSlots = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'style' => $this->style,
            'attributes' => $this->attributes,
            'inline' => array_map(static fn (ResolvedInline $item): array => $item->toArray(), $this->inline),
            'children' => array_map(static fn (ResolvedNode $item): array => $item->toArray(), $this->children),
            'conditionMatched' => $this->conditionMatched,
            'mediaReferences' => $this->mediaReferences,
            'collectionSlots' => $this->collectionSlots,
        ];
    }
}
