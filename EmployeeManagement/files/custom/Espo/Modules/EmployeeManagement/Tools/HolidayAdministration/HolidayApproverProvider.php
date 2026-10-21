<?php
declare(strict_types=1);
namespace Espo\Modules\EmployeeManagement\Tools\HolidayAdministration;

use Espo\Core\AclManager;
use Espo\Entities\User;
use Espo\ORM\EntityManager;

/** Effective native Roles (including Team roles), never a separate approver whitelist. */
final class HolidayApproverProvider
{
    public function __construct(private EntityManager $entityManager, private AclManager $aclManager) {}

    public function isApprover(User $user): bool
    {
        if (!$user->isRegular() || !$user->isActive()) { return false; }
        $acl = $this->aclManager->createUserAcl($user);
        return $acl->checkScope('EmployeeManagement') && $acl->checkScope('EmployeeHolidayApproval');
    }

    /** @return list<User> */
    public function users(): array
    {
        $users = [];
        foreach ($this->entityManager->getRDBRepositoryByClass(User::class)
            ->where(['type' => User::TYPE_REGULAR, 'isActive' => true])->order('name')->find() as $candidate) {
            if ($this->isApprover($candidate)) { $users[] = $candidate; }
        }
        return $users;
    }
}
