<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue;

final readonly class EntityCatalogueItem
{
    public function __construct(
        public string $entityType,
        public string $label,
        public bool $custom,
    ) {}

    /** @return array{entityType: string, label: string, custom: bool} */
    public function toArray(): array
    {
        return [
            'entityType' => $this->entityType,
            'label' => $this->label,
            'custom' => $this->custom,
        ];
    }
}
