<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue;

interface EntityCatalogueAccess
{
    public function requireCatalogueAccess(): void;

    public function canRead(string $entityType): bool;

    public function canReadField(string $entityType, string $field): bool;

    public function canReadLink(string $entityType, string $link): bool;
}
