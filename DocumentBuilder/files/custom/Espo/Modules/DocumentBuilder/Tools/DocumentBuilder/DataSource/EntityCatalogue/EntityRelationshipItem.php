<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue;

final readonly class EntityRelationshipItem
{
    public function __construct(
        public string $name,
        public string $label,
        public string $type,
        public string $targetEntityType,
        public bool $single,
        public bool $collection,
        public bool $custom,
        public bool $expandable,
        public bool $circular,
        public bool $depthLimited,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
