<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use DateTimeImmutable;
use Espo\Core\Utils\DateTime;
use Espo\Core\Utils\SystemUser;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/** Creeaza idempotent transele anuale, la scadenta sau pentru rezervari dintr-un an viitor. */
class AcordariAnualeConcediu
{
    public function __construct(
        private EntityManager $managerEntitati,
        private DateTime $dataOra,
        private SystemUser $utilizatorSistem,
        private SetariConcedii $setariConcedii,
    ) {}

    public function acordaScadente(): void
    {
        if (!$this->setariConcedii->esteAcordareaAnualaAutomataActiva()) {
            return;
        }

        $azi = $this->dataOra->getToday()->toString();
        $profiluri = $this->managerEntitati->getRDBRepository('ProfilConcediu')
            ->where([
                'initializat' => true,
                'dataUrmatoareiAcordari<=' => $azi,
            ])
            ->find();

        foreach ($profiluri as $profil) {
            $idProfil = $profil->getId();

            if (!$idProfil) {
                continue;
            }

            $this->managerEntitati->getTransactionManager()->run(
                fn () => $this->acordaScadenteProfil($idProfil, $azi)
            );
        }
    }

    /** @param list<int> $ani */
    public function pregatestePentruAni(Entity $profil, array $ani): void
    {
        if (!$this->setariConcedii->esteAcordareaAnualaAutomataActiva()) {
            return;
        }

        $urmatoarea = $this->data($profil->get('dataUrmatoareiAcordari'));

        if (!$urmatoarea) {
            return;
        }

        $primulAnViitor = (int) $urmatoarea->format('Y');
        $lunaZi = $urmatoarea->format('m-d');

        foreach (array_unique($ani) as $an) {
            if ($an < $primulAnViitor) {
                continue;
            }

            $this->creeazaDreptDacaLipseste(
                $profil,
                $an,
                sprintf('%04d-%s', $an, $lunaZi),
                false
            );
        }
    }

    private function acordaScadenteProfil(string $idProfil, string $azi): void
    {
        $profil = $this->managerEntitati->getRDBRepository('ProfilConcediu')
            ->where(['id' => $idProfil, 'initializat' => true])
            ->forUpdate()
            ->findOne();

        if (!$profil) {
            return;
        }

        $urmatoarea = $this->data($profil->get('dataUrmatoareiAcordari'));

        while ($urmatoarea && $urmatoarea->format('Y-m-d') <= $azi) {
            $this->creeazaDreptDacaLipseste(
                $profil,
                (int) $urmatoarea->format('Y'),
                $urmatoarea->format('Y-m-d'),
                true
            );
            $urmatoarea = $urmatoarea->modify('+1 year');
        }

        if ($urmatoarea) {
            $profil->set('dataUrmatoareiAcordari', $urmatoarea->format('Y-m-d'));
            $this->managerEntitati->saveEntity($profil);
        }
    }

    private function creeazaDreptDacaLipseste(
        Entity $profil,
        int $an,
        string $dataAcordare,
        bool $inregistreazaAcordarea,
    ): Entity {
        $drept = $this->managerEntitati->getRDBRepository('DreptConcediu')
            ->where([
                'profilId' => $profil->getId(),
                'anOrigine' => $an,
                'tip' => 'Anual',
            ])
            ->forUpdate()
            ->findOne();

        if (!$drept) {
            $drept = $this->managerEntitati->getNewEntity('DreptConcediu');
            $drept->set([
                'name' => sprintf('%s · %d · Anual', $profil->get('assignedUserName'), $an),
                'profilId' => $profil->getId(),
                'assignedUserId' => $profil->get('assignedUserId'),
                'assignedUserName' => $profil->get('assignedUserName'),
                'anOrigine' => $an,
                'tip' => 'Anual',
                'zileAcordate' => (int) $profil->get('zileAnuale'),
                'zileRezervate' => 0,
                'zileConsumate' => 0,
                'zileConsumateInitial' => 0,
                'dataAcordare' => $dataAcordare,
            ]);
            $this->managerEntitati->saveEntity($drept);
        }

        if ($inregistreazaAcordarea) {
            $this->inregistreazaAcordarea($profil, $drept, $an, $dataAcordare);
        }

        return $drept;
    }

    private function inregistreazaAcordarea(
        Entity $profil,
        Entity $drept,
        int $an,
        string $dataAcordare,
    ): void {
        $cheie = sprintf('acordare-anuala:%s:%d', $profil->getId(), $an);
        $existent = $this->managerEntitati->getRDBRepository('RegistruConcediu')
            ->where(['cheieOperatie' => $cheie])
            ->findOne();

        if ($existent) {
            return;
        }

        $registru = $this->managerEntitati->getNewEntity('RegistruConcediu');
        $registru->set([
            'name' => sprintf('Acordare anuala %d · %s', $an, $profil->get('assignedUserName')),
            'profilId' => $profil->getId(),
            'dreptId' => $drept->getId(),
            'tip' => 'AcordareAnuala',
            'numarZile' => (int) $drept->get('zileAcordate'),
            'motiv' => sprintf('Acordare anuala %d', $an),
            'cheieOperatie' => $cheie,
            'dataEfectiva' => $dataAcordare,
            'assignedUserId' => $profil->get('assignedUserId'),
            'actorId' => $this->utilizatorSistem->getId(),
        ]);
        $this->managerEntitati->saveEntity($registru);
    }

    private function data(mixed $valoare): ?DateTimeImmutable
    {
        if (!is_string($valoare)) {
            return null;
        }

        $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valoare);

        return $data !== false && $data->format('Y-m-d') === $valoare ? $data : null;
    }
}
