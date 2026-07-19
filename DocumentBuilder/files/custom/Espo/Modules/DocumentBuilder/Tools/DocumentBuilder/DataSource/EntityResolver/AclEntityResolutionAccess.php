<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\ORM\Entity;

final readonly class AclEntityResolutionAccess implements EntityResolutionAccess
{
    public function __construct(private Acl $acl)
    {}

    public function canReadScope(string $entityType): bool
    {
        return $this->acl->checkScope($entityType, Table::ACTION_READ);
    }

    public function canReadRecord(Entity $record): bool
    {
        return $this->acl->checkEntity($record, Table::ACTION_READ);
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
