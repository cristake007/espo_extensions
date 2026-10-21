<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Classes\Acl;

use Espo\Core\Acl\DefaultAccessChecker;
use Espo\Core\Acl\ScopeData;
use Espo\Entities\User;
use Espo\ORM\Entity;

/** Stage 1: retain native own/all read semantics; expose no business mutations. */
class ReadOnlyAccessChecker extends DefaultAccessChecker
{
    public function check(User $user, ScopeData $data): bool
    {
        return $this->checkRead($user, $data);
    }

    public function checkRead(User $user, ScopeData $data): bool
    {
        return $user->isRegular() && $user->isActive() && parent::checkRead($user, $data);
    }

    public function checkEntityRead(User $user, Entity $entity, ScopeData $data): bool
    {
        return $this->checkRead($user, $data) && parent::checkEntityRead($user, $entity, $data);
    }

    public function checkCreate(User $user, ScopeData $data): bool { return false; }
    public function checkEdit(User $user, ScopeData $data): bool { return false; }
    public function checkDelete(User $user, ScopeData $data): bool { return false; }
    public function checkEntityCreate(User $user, Entity $entity, ScopeData $data): bool { return false; }
    public function checkEntityEdit(User $user, Entity $entity, ScopeData $data): bool { return false; }
    public function checkEntityDelete(User $user, Entity $entity, ScopeData $data): bool { return false; }
}
