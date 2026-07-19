<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver;

use Espo\ORM\Entity;

interface EntityResolutionAccess
{
    public function canReadScope(string $entityType): bool;

    public function canReadRecord(Entity $record): bool;

    public function canReadField(string $entityType, string $field): bool;

    public function canReadLink(string $entityType, string $link): bool;
}
