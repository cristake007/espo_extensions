<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue;

interface EntityCatalogueMetadata
{
    /** @return array<string, array<string, mixed>> */
    public function scopeDefinitions(): array;

    public function hasEntityDefinition(string $entityType): bool;

    public function isCustom(string $entityType): bool;

    /** @return array<string, mixed> */
    public function entityDefinition(string $entityType): array;
}
