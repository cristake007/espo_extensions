<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Utils\DateTime;
use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Angajati\OrdonatorAngajati;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/** Initializarea este explicita; modificarile ulterioare nu rescriu drepturile deja acordate. */
class ProfiluriConcediu
{
    public function __construct(
        private EntityManager $managerEntitati,
        private OrdonatorAngajati $ordonatorAngajati,
        private SolduriConcediu $solduri,
        private User $utilizator,
        private DateTime $dataOra,
    ) {}

    /** @return list<array<string, mixed>> */
    public function lista(): array
    {
        $angajati = $this->managerEntitati->getRDBRepositoryByClass(User::class)
            ->where(['type' => User::TYPE_REGULAR, 'isActive' => true])
            ->find();
        $rezultat = [];

        foreach ($this->ordonatorAngajati->ordoneaza($angajati) as $angajat) {
            $profil = $this->gaseste((string) $angajat->getId(), false);
            $rezultat[] = [
                'idAngajat' => $angajat->getId(),
                'numeAngajat' => $angajat->getName(),
                'dataAngajare' => $angajat->get('dataAngajare'),
                'initializat' => $profil ? (bool) $profil->get('initializat') : false,
                'zileAnuale' => $profil ? (int) $profil->get('zileAnuale') : 21,
                'dataUrmatoareiAcordari' => $profil?->get('dataUrmatoareiAcordari'),
                'sold' => $profil ? $this->solduri->sumar((string) $angajat->getId()) : null,
                'istoric' => $profil ? $this->solduri->istoric($profil->getId()) : [],
            ];
        }

        return $rezultat;
    }

    /**
     * @param array<string, mixed> $date
     * @return array<string, mixed>
     */
    public function initializeaza(array $date): array
    {
        $idAngajat = $this->sir($date['idAngajat'] ?? null);
        $zileAnuale = $this->numar($date['zileAnuale'] ?? null);
        $zileDisponibileAnCurent = $this->numar($date['zileDisponibileAnCurent'] ?? null);
        $zileReportateAnAnterior = $this->numar($date['zileReportateAnAnterior'] ?? null);
        $dataAcordare = $this->sir($date['dataUrmatoareiAcordari'] ?? null);

        if (!$idAngajat || $zileAnuale === null || $zileAnuale < 0 ||
            $zileDisponibileAnCurent === null || $zileDisponibileAnCurent < 0 ||
            $zileReportateAnAnterior === null || $zileReportateAnAnterior < 0 ||
            $dataAcordare === null || !$this->esteData($dataAcordare)) {
            $this->eroare('initializareConcediuInvalida');
        }

        if ($zileDisponibileAnCurent > $zileAnuale) {
            $this->eroare('zileDisponibilePesteDrept');
        }

        if ($dataAcordare <= $this->dataOra->getToday()->toString()) {
            $this->eroare('dataUrmatoareiAcordariTrebuieViitoare');
        }

        return $this->managerEntitati->getTransactionManager()->run(
            function () use (
                $idAngajat,
                $zileAnuale,
                $zileDisponibileAnCurent,
                $zileReportateAnAnterior,
                $dataAcordare
            ): array {
                $angajat = $this->obtineAngajat($idAngajat);

                if ($this->gaseste($idAngajat, true)) {
                    throw Conflict::createWithBody(
                        'profilConcediuExistent',
                        Body::create()->withMessageTranslation('profilConcediuExistent', 'ProfilConcediu')
                    );
                }

                $profil = $this->managerEntitati->getNewEntity('ProfilConcediu');
                $profil->set([
                    'name' => 'Concediu · ' . $angajat->getName(),
                    'assignedUserId' => $idAngajat,
                    'assignedUserName' => $angajat->getName(),
                    'zileAnuale' => $zileAnuale,
                    'dataUrmatoareiAcordari' => $dataAcordare,
                    'initializat' => true,
                ]);
                $this->managerEntitati->saveEntity($profil);

                $an = (int) substr($dataAcordare, 0, 4) - 1;
                $dataAcordareCurenta = sprintf('%d%s', $an, substr($dataAcordare, 4));
                $this->creeazaDrept(
                    $profil,
                    $angajat,
                    $an,
                    'Anual',
                    $zileDisponibileAnCurent,
                    0,
                    $dataAcordareCurenta,
                    'Inițializare sold disponibil din anul curent'
                );

                if ($zileReportateAnAnterior > 0) {
                    $this->creeazaDrept($profil, $angajat, $an - 1, 'ReportInitial',
                        $zileReportateAnAnterior, 0, $dataAcordareCurenta, 'Initializare report');
                }

                return ['idProfil' => $profil->getId(), 'sold' => $this->solduri->sumar($idAngajat)];
            }
        );
    }

    /**
     * @param array<string, mixed> $date
     * @return array<string, mixed>
     */
    public function actualizeazaSetari(array $date): array
    {
        $idAngajat = $this->sir($date['idAngajat'] ?? null);
        $zileAnuale = $this->numar($date['zileAnuale'] ?? null);
        if (!$idAngajat || $zileAnuale === null || $zileAnuale < 0) {
            $this->eroare('profilConcediuInvalid');
        }

        $profil = $this->gaseste($idAngajat, false);

        if (!$profil) {
            $this->eroare('profilConcediuNeinitializat');
        }

        $profil->set('zileAnuale', $zileAnuale);
        $this->managerEntitati->saveEntity($profil);

        return ['idProfil' => $profil->getId()];
    }

    public function obtineInitializat(string $idAngajat, bool $blocheaza = false): Entity
    {
        $profil = $this->gaseste($idAngajat, $blocheaza);

        if (!$profil || !$profil->get('initializat')) {
            $this->eroare('profilConcediuNeinitializat');
        }

        return $profil;
    }

    private function creeazaDrept(
        Entity $profil,
        User $angajat,
        int $an,
        string $tip,
        int $acordate,
        int $consumate,
        string $data,
        string $motiv,
    ): void {
        $drept = $this->managerEntitati->getNewEntity('DreptConcediu');
        $drept->set([
            'name' => sprintf('%s · %d · %s', $angajat->getName(), $an, $tip),
            'profilId' => $profil->getId(),
            'assignedUserId' => $angajat->getId(),
            'assignedUserName' => $angajat->getName(),
            'anOrigine' => $an,
            'tip' => $tip,
            'zileAcordate' => $acordate,
            'zileRezervate' => 0,
            'zileConsumate' => $consumate,
            'zileConsumateInitial' => $consumate,
            'dataAcordare' => $data,
        ]);
        $this->managerEntitati->saveEntity($drept);

        $registru = $this->managerEntitati->getNewEntity('RegistruConcediu');
        $registru->set([
            'name' => $motiv . ' · ' . $angajat->getName(),
            'profilId' => $profil->getId(),
            'dreptId' => $drept->getId(),
            'tip' => 'Initializare',
            'numarZile' => $acordate - $consumate,
            'motiv' => $motiv,
            'cheieOperatie' => 'initializare:' . $profil->getId() . ':' . $an . ':' . $tip,
            'dataEfectiva' => date('Y-m-d'),
            'assignedUserId' => $angajat->getId(),
            'actorId' => $this->utilizator->getId(),
        ]);
        $this->managerEntitati->saveEntity($registru);
    }

    private function gaseste(string $idAngajat, bool $blocheaza): ?Entity
    {
        $interogare = $this->managerEntitati->getRDBRepository('ProfilConcediu')
            ->where(['assignedUserId' => $idAngajat]);

        if ($blocheaza) {
            $interogare = $interogare->forUpdate();
        }

        return $interogare->findOne();
    }

    private function obtineAngajat(string $id): User
    {
        $angajat = $this->managerEntitati->getRDBRepositoryByClass(User::class)
            ->where(['id' => $id, 'type' => User::TYPE_REGULAR, 'isActive' => true])
            ->forUpdate()->findOne();

        if (!$angajat instanceof User) {
            $this->eroare('angajatInvalid');
        }

        return $angajat;
    }

    private function sir(mixed $valoare): ?string
    {
        return is_string($valoare) && trim($valoare) !== '' ? trim($valoare) : null;
    }

    private function numar(mixed $valoare): ?int
    {
        if (is_int($valoare)) {
            return $valoare;
        }

        if (is_float($valoare) && is_finite($valoare) && floor($valoare) === $valoare) {
            return (int) $valoare;
        }

        if (is_string($valoare) && preg_match('/^-?\d+$/', $valoare) === 1) {
            return (int) $valoare;
        }

        return null;
    }

    private function esteData(?string $valoare): bool
    {
        return $valoare !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valoare) === 1;
    }

    private function eroare(string $mesaj): never
    {
        throw BadRequest::createWithBody(
            $mesaj,
            Body::create()->withMessageTranslation($mesaj, 'ProfilConcediu')
        );
    }
}
