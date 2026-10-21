<?php
declare(strict_types=1);
namespace Espo\Modules\EmployeeManagement\Classes\Acl;

use Espo\Core\Acl\ScopeData;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

final class AbsenceAccessChecker extends ReadOnlyAccessChecker
{
    public function __construct(private EntityManager $em, \Espo\Core\AclManager $aclManager,
        \Espo\Core\Utils\Config $config, \Espo\Core\Acl\AccessChecker\ScopeChecker $scopeChecker)
    { parent::__construct($aclManager, $config, $scopeChecker); }
    public function checkEntityRead(User $user, Entity $entity, ScopeData $data): bool
    {
        $owner = $this->em->getEntityById('User', (string) $entity->get('assignedUserId'));
        return $owner instanceof User && $owner->isRegular() && $owner->isActive()
            && parent::checkEntityRead($user, $entity, $data);
    }
}
