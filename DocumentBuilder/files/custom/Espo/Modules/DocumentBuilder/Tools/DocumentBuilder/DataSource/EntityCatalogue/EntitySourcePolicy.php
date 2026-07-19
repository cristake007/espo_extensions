<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue;

interface EntitySourcePolicy
{
    public function allows(string $entityType): bool;
}
