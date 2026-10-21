<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Classes\Record\Hooks;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\DeleteParams;
use Espo\Core\Record\Hook\DeleteHook;
use Espo\Core\Record\Hook\SaveHook;
use Espo\ORM\Entity;

/** Also protects API calls by technical admins, who bypass native ACL checks. */
final class ReadOnlySaveGuard implements SaveHook, DeleteHook
{
    public function process(Entity $entity, ?DeleteParams $params = null): void
    {
        throw new Forbidden('Generic record mutations are disabled. Use the authorized Employee Management operations.');
    }
}
