<?php

namespace Espo\Modules\ManagementAngajati\Hooks\Prezenta;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\ORM\Entity as EntitatePrincipala;
use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\DenumirePrezenta;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/** @implements BeforeSave<Entity> */
class PregatesteLaCreare implements BeforeSave
{
    public function __construct(
        private EntityManager $managerEntitati,
        private DenumirePrezenta $denumirePrezenta,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if (!$entity->isNew()) {
            return;
        }

        $this->denumirePrezenta->aplica($entity);

        if (!$entity instanceof EntitatePrincipala) {
            return;
        }

        $idAngajat = (string) $entity->get('assignedUserId');
        $angajat = $this->managerEntitati->getEntityById(User::ENTITY_TYPE, $idAngajat);

        if (!$angajat instanceof User) {
            return;
        }

        $entity->setLinkMultipleIdList('teams', $angajat->getTeamIdList());
    }
}
