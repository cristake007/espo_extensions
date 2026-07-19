<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\ActionPermission;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\PermissionDenied;

final readonly class AclEntityCatalogueAccess implements EntityCatalogueAccess
{
    public function __construct(private Acl $acl)
    {}

    public function requireCatalogueAccess(): void
    {
        if ($this->acl->getPermissionLevel(ActionPermission::DesignTemplates->value) !== Table::LEVEL_YES) {
            throw new PermissionDenied();
        }
    }

    public function canRead(string $entityType): bool
    {
        return $this->acl->checkScope($entityType, Table::ACTION_READ);
    }

    public function canReadField(string $entityType, string $field): bool
    {
        return $this->acl->checkField($entityType, $field, Table::ACTION_READ);
    }

    public function canReadLink(string $entityType, string $link): bool
    {
        return $this->acl->checkLink($entityType, $link, Table::ACTION_READ);
    }
}
