<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Classes\Record\Hooks;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\Hook\LinkHook;
use Espo\Core\Record\Hook\UnlinkHook;
use Espo\ORM\Entity;

final class ReadOnlyLinkGuard implements LinkHook, UnlinkHook
{
    public function process(Entity $entity, string $link, Entity $foreignEntity): void
    {
        throw new Forbidden('Employee Management relationships are read-only in this stage.');
    }
}
