<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Classes\Acl\ZileLibere;

use Espo\Core\Acl\AccessCreateChecker;
use Espo\Core\Acl\AccessDeleteChecker;
use Espo\Core\Acl\AccessEditChecker;
use Espo\Core\Acl\AccessEntityCREDChecker;
use Espo\Core\Acl\AccessReadChecker;
use Espo\Core\Acl\DefaultAccessChecker;
use Espo\Core\Acl\ScopeData;
use Espo\Entities\User;
use Espo\Modules\ZileSarbatoare\Entities\ZileLibere;
use Espo\ORM\Entity;

final class AccessChecker implements
    AccessCreateChecker,
    AccessReadChecker,
    AccessEditChecker,
    AccessDeleteChecker,
    AccessEntityCREDChecker
{
    public function __construct(private DefaultAccessChecker $defaultAccessChecker)
    {}

    public function check(User $user, ScopeData $data): bool
    {
        return $this->defaultAccessChecker->check($user, $data);
    }

    public function checkCreate(User $user, ScopeData $data): bool
    {
        return $user->isAdmin();
    }

    public function checkRead(User $user, ScopeData $data): bool
    {
        return $this->defaultAccessChecker->checkRead($user, $data);
    }

    public function checkEdit(User $user, ScopeData $data): bool
    {
        return $user->isAdmin();
    }

    public function checkDelete(User $user, ScopeData $data): bool
    {
        return $user->isAdmin();
    }

    public function checkEntityCreate(User $user, Entity $entity, ScopeData $data): bool
    {
        return $user->isAdmin();
    }

    public function checkEntityRead(User $user, Entity $entity, ScopeData $data): bool
    {
        return $this->defaultAccessChecker->checkEntityRead($user, $entity, $data);
    }

    public function checkEntityEdit(User $user, Entity $entity, ScopeData $data): bool
    {
        if ($this->isManaged($entity)) {
            return false;
        }

        return $user->isAdmin();
    }

    public function checkEntityDelete(User $user, Entity $entity, ScopeData $data): bool
    {
        if ($this->isManaged($entity)) {
            return false;
        }

        return $user->isAdmin();
    }

    private function isManaged(Entity $entity): bool
    {
        return
            (bool) $entity->get('managed') ||
            $entity->get('source') === ZileLibere::SOURCE_NAGER_DATE;
    }
}
