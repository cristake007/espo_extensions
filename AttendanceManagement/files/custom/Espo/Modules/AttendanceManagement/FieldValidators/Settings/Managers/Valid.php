<?php

declare(strict_types=1);

namespace Espo\Modules\AttendanceManagement\FieldValidators\Settings\Managers;

use Espo\Core\FieldValidation\Validator;
use Espo\Core\FieldValidation\Validator\Data;
use Espo\Core\FieldValidation\Validator\Failure;
use Espo\Entities\Settings;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/** @implements Validator<Settings> */
final class Valid implements Validator
{
    public function __construct(private EntityManager $entityManager)
    {}

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

        if (count($ids) > 50) {
            return Failure::create();
        }

        $count = $this->entityManager
            ->getRDBRepositoryByClass(User::class)
            ->where([
                'id' => $ids,
                'type' => [User::TYPE_REGULAR, User::TYPE_ADMIN],
                'isActive' => true,
            ])
            ->count();

        return $count !== count($ids) ? Failure::create() : null;
    }
}
