<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Classes\Record\OutputFilters\DocumentBuilderDocument;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Record\Output\Filter;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\ActionPermission;
use Espo\ORM\Entity;

final readonly class Snapshot implements Filter
{
    public function __construct(private Acl $acl)
    {}

    public function filter(Entity $entity): void
    {
        if ($this->acl->getPermissionLevel(ActionPermission::ViewDataSnapshots->value) === Table::LEVEL_YES) {
            return;
        }

        $entity->clear('dataSnapshot');
        $entity->clear('templateSnapshot');
    }
}
