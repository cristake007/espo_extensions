<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue;

final readonly class EntityMetadataTree
{
    /**
     * @param list<string> $path
     * @param list<EntityFieldItem> $fields
     * @param list<EntityRelationshipItem> $relationships
     */
    public function __construct(
        public string $rootEntityType,
        public string $entityType,
        public array $path,
        public array $fields,
        public array $relationships,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'rootEntityType' => $this->rootEntityType,
            'entityType' => $this->entityType,
            'path' => $this->path,
            'fields' => array_map(static fn (EntityFieldItem $item): array => $item->toArray(), $this->fields),
            'relationships' => array_map(
                static fn (EntityRelationshipItem $item): array => $item->toArray(),
                $this->relationships,
            ),
        ];
    }
}
