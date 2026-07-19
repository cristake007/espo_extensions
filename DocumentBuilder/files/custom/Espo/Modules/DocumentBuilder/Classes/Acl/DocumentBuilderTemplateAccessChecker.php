<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Classes\Acl;

use Espo\Core\Acl\AccessCreateChecker;
use Espo\Core\Acl\AccessDeleteChecker;
use Espo\Core\Acl\AccessEditChecker;
use Espo\Core\Acl\AccessEntityCreateChecker;
use Espo\Core\Acl\AccessEntityDeleteChecker;
use Espo\Core\Acl\AccessEntityEditChecker;
use Espo\Core\Acl\AccessEntityReadChecker;
use Espo\Core\Acl\AccessEntityStreamChecker;
use Espo\Core\Acl\AccessReadChecker;
use Espo\Core\Acl\AccessStreamChecker;
use Espo\Core\Acl\DefaultAccessChecker;
use Espo\Core\Acl\ScopeData;
use Espo\Core\Acl\Table;
use Espo\Core\AclManager;
use Espo\Entities\User;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\ActionPermission;
use Espo\ORM\Entity;

final readonly class DocumentBuilderTemplateAccessChecker implements
    AccessCreateChecker,
    AccessReadChecker,
    AccessEditChecker,
    AccessDeleteChecker,
    AccessStreamChecker,
    AccessEntityCreateChecker,
    AccessEntityReadChecker,
    AccessEntityEditChecker,
    AccessEntityDeleteChecker,
    AccessEntityStreamChecker
{
    public function __construct(
        private AclManager $aclManager,
        private DefaultAccessChecker $defaultAccessChecker,
    ) {}

    public function check(User $user, ScopeData $data): bool
    {
        return $this->canDesign($user) && $this->defaultAccessChecker->check($user, $data);
    }

    public function checkCreate(User $user, ScopeData $data): bool
    {
        return $this->canDesign($user) && $this->defaultAccessChecker->checkCreate($user, $data);
    }

    public function checkRead(User $user, ScopeData $data): bool
    {
        return $this->canDesign($user) && $this->defaultAccessChecker->checkRead($user, $data);
    }

    public function checkEdit(User $user, ScopeData $data): bool
    {
        return $this->canDesign($user) && $this->defaultAccessChecker->checkEdit($user, $data);
    }

    public function checkDelete(User $user, ScopeData $data): bool
    {
        return false;
    }

    public function checkStream(User $user, ScopeData $data): bool
    {
        return $this->canDesign($user) && $this->defaultAccessChecker->checkStream($user, $data);
    }

    public function checkEntityCreate(User $user, Entity $entity, ScopeData $data): bool
    {
        return $this->canDesign($user) && $this->defaultAccessChecker->checkEntityCreate($user, $entity, $data);
    }

    public function checkEntityRead(User $user, Entity $entity, ScopeData $data): bool
    {
        return $this->canDesign($user) && $this->defaultAccessChecker->checkEntityRead($user, $entity, $data);
    }

    public function checkEntityEdit(User $user, Entity $entity, ScopeData $data): bool
    {
        return $this->canDesign($user) && $this->defaultAccessChecker->checkEntityEdit($user, $entity, $data);
    }

    public function checkEntityDelete(User $user, Entity $entity, ScopeData $data): bool
    {
        return false;
    }

    public function checkEntityStream(User $user, Entity $entity, ScopeData $data): bool
    {
        return $this->canDesign($user) && $this->defaultAccessChecker->checkEntityStream($user, $entity, $data);
    }

    private function canDesign(User $user): bool
    {
        return $this->aclManager->getPermissionLevel(
            $user,
            ActionPermission::DesignTemplates->value,
        ) === Table::LEVEL_YES;
    }
}
