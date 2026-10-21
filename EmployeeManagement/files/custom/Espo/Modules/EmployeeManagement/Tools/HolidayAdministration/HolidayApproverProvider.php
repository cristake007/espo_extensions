<?php
declare(strict_types=1);
namespace Espo\Modules\EmployeeManagement\Tools\HolidayAdministration;

use Espo\Core\AclManager;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/** Effective native Roles (including Team roles), never a separate approver whitelist. */
final class HolidayApproverProvider
{
    private const REQUEST = 'EmployeeHolidayRequest';
    private const QUEUE_FIELDS = [
        'assignedUserId',
        'assignedUserName',
        'dateStartDate',
        'dateEndDate',
        'description',
        'cancellationReason',
        'days',
        'status',
        'accountingRevision',
    ];

    public function __construct(private EntityManager $entityManager, private AclManager $aclManager) {}

    public function isApprover(User $user): bool
    {
        if (!$user->isRegular() || !$user->isActive()) { return false; }
        $acl = $this->aclManager->createUserAcl($user);
        return $acl->checkScope('EmployeeManagement') && $acl->checkScope('EmployeeHolidayApproval');
    }

    /** The same effective access required to open and read the approval queue. */
    public function canReadApprovalRequest(User $user, Entity $request): bool
    {
        if (!$this->isApprover($user)) {
            return false;
        }

        $acl = $this->aclManager->createUserAcl($user);

        return $acl->getLevel(self::REQUEST, 'read') === 'all'
            && $acl->checkEntity($request, 'read')
            && !array_intersect(
                self::QUEUE_FIELDS,
                $acl->getScopeForbiddenAttributeList(self::REQUEST, 'read'),
            );
    }

    /** @return list<User> */
    public function users(?Entity $request = null): array
    {
        $users = [];
        foreach ($this->entityManager->getRDBRepositoryByClass(User::class)
            ->where(['type' => User::TYPE_REGULAR, 'isActive' => true])->order('name')->find() as $candidate) {
            if (
                $request
                    ? $this->canReadApprovalRequest($candidate, $request)
                    : $this->isApprover($candidate)
            ) {
                $users[] = $candidate;
            }
        }
        return $users;
    }
}
