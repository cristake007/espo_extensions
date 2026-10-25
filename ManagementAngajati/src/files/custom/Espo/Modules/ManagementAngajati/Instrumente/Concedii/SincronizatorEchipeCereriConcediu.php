<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use Espo\Core\ORM\Entity as EntitatePrincipala;
use Espo\Core\Name\Field;
use Espo\Entities\Team;
use Espo\Entities\User;
use Espo\ORM\EntityManager;

/** Mentine ACL-ul Team al cererilor sincronizat cu echipele curente ale angajatului. */
class SincronizatorEchipeCereriConcediu
{
    public function __construct(private EntityManager $managerEntitati) {}

    public function sincronizeaza(User $angajat): void
    {
        $idEchipe = [];

        foreach ($this->managerEntitati->getRelation($angajat, Field::TEAMS)->find() as $echipa) {
            if ($echipa instanceof Team) {
                $idEchipe[] = $echipa->getId();
            }
        }

        foreach ($this->managerEntitati->getRDBRepository('CerereConcediu')
            ->where(['assignedUserId' => $angajat->getId()])
            ->find() as $cerere) {
            if (!$cerere instanceof EntitatePrincipala) {
                continue;
            }

            $cerere->setLinkMultipleIdList(Field::TEAMS, $idEchipe);
            $this->managerEntitati->saveEntity($cerere);
        }
    }
}
