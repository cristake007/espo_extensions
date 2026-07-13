<?php

namespace Espo\Modules\HolidayManagement\FieldValidators\Settings\ApproverRole;

use Espo\Core\FieldValidation\Validator;
use Espo\Core\FieldValidation\Validator\Data;
use Espo\Core\FieldValidation\Validator\Failure;
use Espo\Entities\Role;
use Espo\Entities\Settings;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * @implements Validator<Settings>
 */
class AtMostTwoActiveInternalUsers implements Validator
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function validate(Entity $entity, string $field, Data $data): ?Failure
    {
        $roleId = $entity->get($field . 'Id');

        if (!$roleId || !is_string($roleId)) {
            return null;
        }

        $role = $this->entityManager
            ->getRDBRepositoryByClass(Role::class)
            ->getById($roleId);

        if (!$role) {
            return Failure::create();
        }

        $activeInternalUserCount = $this->entityManager
            ->getRelation($role, 'users')
            ->where([
                'type' => [User::TYPE_REGULAR, User::TYPE_ADMIN],
                'isActive' => true,
            ])
            ->count();

        return $activeInternalUserCount > 2 ? Failure::create() : null;
    }
}
