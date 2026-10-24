<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Language\LanguageFactory;
use Espo\Entities\Notification;
use Espo\Entities\Preferences;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/** Creeaza notificari native Espo pentru etapele fluxului de anulare. */
class NotificatorCereriConcediu
{
    public function __construct(
        private EntityManager $managerEntitati,
        private LanguageFactory $fabricaLimbi,
        private Config $configuratie,
    ) {}

    public function notificaRetragere(Entity $cerere): void
    {
        $this->notificaAprobatori($cerere, 'notificareCerereRetrasa');
    }

    public function notificaSolicitareAnulare(Entity $cerere): void
    {
        $this->notificaAprobatori($cerere, 'notificareSolicitareAnulare');
    }

    public function notificaDecizieAnulare(Entity $cerere, bool $aprobata): void
    {
        $idAngajat = $cerere->get('assignedUserId');

        if (!is_string($idAngajat)) {
            return;
        }

        $angajat = $this->managerEntitati->getRDBRepositoryByClass(User::class)
            ->where(['id' => $idAngajat, 'isActive' => true])
            ->findOne();

        if (!$angajat instanceof User) {
            return;
        }

        $this->creeaza(
            $angajat,
            $cerere,
            $aprobata ? 'notificareAnulareAprobata' : 'notificareAnulareRespinsa',
            '#CerereConcediu'
        );
    }

    private function notificaAprobatori(Entity $cerere, string $cheieMesaj): void
    {
        $idAprobatori = array_values(array_unique(array_filter(
            (array) $cerere->get('aprobatoriNecesari'),
            'is_string'
        )));

        if ($idAprobatori === []) {
            return;
        }

        foreach ($this->managerEntitati->getRDBRepositoryByClass(User::class)
            ->where(['id' => $idAprobatori, 'isActive' => true])
            ->find() as $aprobator) {
            if ($aprobator instanceof User) {
                $this->creeaza($aprobator, $cerere, $cheieMesaj, '#AdministrareConcedii');
            }
        }
    }

    private function creeaza(
        User $destinatar,
        Entity $cerere,
        string $cheieMesaj,
        string $ruta,
    ): void {
        $limba = $this->obtineLimba($destinatar);
        $mesaj = strtr(
            $limba->translateLabel($cheieMesaj, 'messages', 'CerereConcediu'),
            [
                '{angajat}' => (string) $cerere->get('assignedUserName'),
                '{inceput}' => (string) $cerere->get('dataInceput'),
                '{sfarsit}' => (string) $cerere->get('dataSfarsit'),
                '{ruta}' => $ruta,
            ]
        );
        $notificare = $this->managerEntitati
            ->getRDBRepositoryByClass(Notification::class)
            ->getNew();
        $notificare
            ->setType(Notification::TYPE_MESSAGE)
            ->setMessage($mesaj)
            ->setUserId($destinatar->getId())
            ->setActionId(md5(implode(':', [
                'management-angajati',
                $cheieMesaj,
                $cerere->getId(),
                $destinatar->getId(),
                (string) $cerere->get('modifiedAt'),
            ])))
            ->setRead(false)
            ->setData([
                'managementAngajatiConcediu' => true,
                'idCerere' => $cerere->getId(),
                'style' => 'text-info',
            ]);
        $this->managerEntitati->saveEntity($notificare);
    }

    private function obtineLimba(User $utilizator): Language
    {
        $preferinte = $this->managerEntitati
            ->getRepositoryByClass(Preferences::class)
            ->getById($utilizator->getId());
        $codLimba = Language::detectLanguage($this->configuratie, $preferinte) ?? 'en_US';

        return $this->fabricaLimbi->create($codLimba);
    }
}
