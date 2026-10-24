<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Espo\Core\Acl\Table;
use Espo\Core\AclManager;
use Espo\Core\ORM\Entity as EntitatePrincipala;
use Espo\Entities\User;
use Espo\ORM\EntityManager;

/** Aplica nivelurile native Own/Team/All ale entitatii Prezenta asupra unui angajat. */
class AccesAngajatiCondica
{
    private const string TIP_ENTITATE = 'Prezenta';

    public function __construct(
        private AclManager $managerAcl,
        private EntityManager $managerEntitati,
        private User $utilizatorCurent,
    ) {}

    public function poateCiti(User $angajat): bool
    {
        return $this->verifica($angajat, Table::ACTION_READ);
    }

    public function poateEdita(User $angajat): bool
    {
        return $this->verifica($angajat, Table::ACTION_EDIT);
    }

    /** @param 'read'|'edit' $actiune */
    private function verifica(User $angajat, string $actiune): bool
    {
        $subiectAcl = $this->managerEntitati->getNewEntity(self::TIP_ENTITATE);
        $subiectAcl->set('assignedUserId', $angajat->getId());

        if ($subiectAcl instanceof EntitatePrincipala) {
            $subiectAcl->setLinkMultipleIdList('teams', $angajat->getTeamIdList());
        }

        return $this->managerAcl->checkEntity(
            $this->utilizatorCurent,
            $subiectAcl,
            $actiune
        );
    }
}
