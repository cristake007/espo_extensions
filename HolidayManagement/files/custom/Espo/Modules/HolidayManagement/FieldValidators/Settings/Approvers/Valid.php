<?php

namespace Espo\Modules\HolidayManagement\FieldValidators\Settings\Approvers;

use Espo\Core\FieldValidation\Validator;
use Espo\Core\FieldValidation\Validator\Data;
use Espo\Core\FieldValidation\Validator\Failure;
use Espo\Entities\Settings;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * @implements Validator<Settings>
 */
class Valid implements Validator
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function validate(Entity $entity, string $field, Data $data): ?Failure
    {
        $ids = $entity->get($field . 'Ids');

        if (!is_array($ids)) {
            return Failure::create();
        }

        $ids = array_values(array_unique(array_filter(
            $ids,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )));

        if (count($ids) < 1 || count($ids) > 2) {
            return Failure::create();
        }

        $activeInternalUserCount = $this->entityManager
            ->getRDBRepositoryByClass(User::class)
            ->where([
                'id' => $ids,
                'type' => [User::TYPE_REGULAR, User::TYPE_ADMIN],
                'isActive' => true,
            ])
            ->count();

        return $activeInternalUserCount !== count($ids) ? Failure::create() : null;
    }
}
