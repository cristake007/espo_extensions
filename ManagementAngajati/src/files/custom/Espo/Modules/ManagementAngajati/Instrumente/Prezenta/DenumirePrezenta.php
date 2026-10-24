<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/** Stabileste consecvent numele tehnic al unei inregistrari de prezenta. */
class DenumirePrezenta
{
    public function __construct(private EntityManager $managerEntitati) {}

    public function aplica(Entity $prezenta): void
    {
        $idAngajat = (string) $prezenta->get('assignedUserId');
        $data = (string) $prezenta->get('dataPrezenta');
        $numeAngajat = trim((string) $prezenta->get('assignedUserName'));

        if ($numeAngajat === '' && $idAngajat !== '') {
            $angajat = $this->managerEntitati->getEntityById(User::ENTITY_TYPE, $idAngajat);

            if ($angajat instanceof User) {
                $numeAngajat = trim($angajat->getName() ?? '');
            }
        }

        $prezenta->set(
            'name',
            ($numeAngajat !== '' ? $numeAngajat : $idAngajat) . ' · ' . $data
        );
    }
}
