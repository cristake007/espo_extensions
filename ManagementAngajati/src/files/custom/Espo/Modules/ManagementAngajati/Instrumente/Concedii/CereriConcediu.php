<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use DateTimeImmutable;
use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Utils\DateTime;
use Espo\Core\Utils\Language;
use Espo\Entities\User;
use Espo\Entities\Attachment;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class CereriConcediu
{
    public function __construct(
        private EntityManager $managerEntitati,
        private User $utilizator,
        private Acl $acl,
        private DateTime $dataOra,
        private CalculatorZileConcediu $calculator,
        private ProfiluriConcediu $profiluri,
        private SolduriConcediu $solduri,
        private SetariConcedii $setari,
        private InstantaneuDocumenteConcediu $instantaneuDocumente,
        private DocumenteConcediu $documenteConcediu,
        private NotificatorCereriConcediu $notificator,
        private Language $limba,
    ) {}

    /** @return array<string, mixed> */
    public function spatiuPersonal(): array
    {
        $this->verificaAccesSpatiuPersonal();

        if ($this->utilizator->isAdmin()) {
            return [
                'disponibil' => false,
                'motivIndisponibil' => 'administratorFaraConcediuPersonal',
                'sold' => null,
                'cereri' => [],
            ];
        }

        $profil = $this->managerEntitati->getRDBRepository('ProfilConcediu')
            ->where([
                'assignedUserId' => $this->utilizator->getId(),
                'initializat' => true,
            ])
            ->findOne();

        if (!$profil) {
            return [
                'disponibil' => false,
                'motivIndisponibil' => 'profilConcediuNeinitializat',
                'sold' => null,
                'cereri' => [],
            ];
        }

        $cereri = $this->managerEntitati->getRDBRepository('CerereConcediu')
            ->where(['assignedUserId' => $this->utilizator->getId()])
            ->order('dataInceput', 'DESC')->find();

        return [
            'disponibil' => true,
            'sold' => $this->solduri->sumar($this->utilizator->getId()),
            'cereri' => array_map(fn (Entity $cerere): array => $this->serializare($cerere),
                iterator_to_array($cereri)),
        ];
    }

    /**
     * @param array<string, mixed> $date
     * @return array<string, mixed>
     */
    public function creeaza(array $date): array
    {
        $this->verificaAngajat();

        if (!$this->acl->checkScope('CerereConcediu', Table::ACTION_CREATE)) {
            throw new Forbidden();
        }

        $inceput = $this->sir($date['dataInceput'] ?? null);
        $sfarsit = $this->sir($date['dataSfarsit'] ?? null);
        $descriere = $this->sir($date['descriere'] ?? null) ?? '';

        if (!$inceput || !$sfarsit || !$this->esteData($inceput) || !$this->esteData($sfarsit)) {
            $this->eroare('perioadaConcediuInvalida');
        }

        $azi = $this->dataOra->getToday()->toString();

        if ($inceput < $azi) {
            $this->eroare('concediuInTrecut', [
                'dataMinima' => $this->dataOra->convertSystemDate($azi),
            ]);
        }

        if ($sfarsit < $inceput) {
            $this->eroare('dataSfarsitInainteDeInceput');
        }

        return $this->managerEntitati->getTransactionManager()->run(
            function () use ($inceput, $sfarsit, $descriere): array {
                $angajat = $this->managerEntitati->getRDBRepositoryByClass(User::class)
                    ->where(['id' => $this->utilizator->getId()])->forUpdate()->findOne();

                if (!$angajat instanceof User) {
                    throw new Forbidden();
                }

                $profil = $this->profiluri->obtineInitializat($angajat->getId(), true);
                $calcul = $this->calculator->calculeaza($angajat, $inceput, $sfarsit);
                $inceputEfectiv = $calcul['date'][0];
                $sfarsitEfectiv = $calcul['date'][count($calcul['date']) - 1];
                $this->verificaPrezenteLaCreare($angajat->getId(), $calcul['date']);
                $this->verificaSuprapunere(
                    $angajat->getId(),
                    $inceputEfectiv,
                    $sfarsitEfectiv
                );
                $aprobatori = $this->setari->aprobatoriPentru($angajat->getId());

                if ($aprobatori === []) {
                    $this->eroare('faraAprobatorDisponibil');
                }

                $necesare = $this->setari->necesitaAmbii() ? count($aprobatori) : 1;
                $cerere = $this->managerEntitati->getNewEntity('CerereConcediu');
                $cerere->set([
                    'name' => $angajat->getName(),
                    'dataInceput' => $inceputEfectiv,
                    'dataSfarsit' => $sfarsitEfectiv,
                    'numarZile' => $calcul['total'],
                    'stare' => 'InAsteptare',
                    'descriere' => $descriere,
                    'profilId' => $profil->getId(),
                    'assignedUserId' => $angajat->getId(),
                    'assignedUserName' => $angajat->getName(),
                    'aprobariNecesare' => $necesare,
                    'aprobatoriNecesari' => $aprobatori,
                    'aprobatoriConfirmati' => [],
                ]);
                $this->managerEntitati->saveEntity($cerere);
                $this->solduri->rezerva($cerere, $profil, $calcul['date']);

                return $this->serializare($cerere) + ['sold' => $this->solduri->sumar($angajat->getId())];
            }
        );
    }

    /**
     * Calculeaza perioada folosind aceleasi reguli ca salvarea cererii.
     *
     * Rezultatul este numai o previzualizare pentru formular. La salvare,
     * calculul este refacut in tranzactie si ramane sursa de adevar.
     *
     * @param array<string, mixed> $date
     * @return array{numarZile: int, dataInceputEfectiva: string, dataSfarsitEfectiva: string}
     */
    public function previzualizeazaPerioada(array $date): array
    {
        $this->verificaAngajat();

        if (!$this->acl->checkScope('CerereConcediu', Table::ACTION_CREATE)) {
            throw new Forbidden();
        }

        $inceput = $this->sir($date['dataInceput'] ?? null);
        $sfarsit = $this->sir($date['dataSfarsit'] ?? null);

        if (!$inceput || !$sfarsit || !$this->esteData($inceput) || !$this->esteData($sfarsit)) {
            $this->eroare('perioadaConcediuInvalida');
        }

        if ($sfarsit < $inceput) {
            $this->eroare('dataSfarsitInainteDeInceput');
        }

        $angajat = $this->managerEntitati->getRDBRepositoryByClass(User::class)
            ->where(['id' => $this->utilizator->getId()])
            ->findOne();

        if (!$angajat instanceof User) {
            throw new Forbidden();
        }

        $calcul = $this->calculator->calculeaza($angajat, $inceput, $sfarsit);

        return [
            'numarZile' => $calcul['total'],
            'dataInceputEfectiva' => $calcul['date'][0],
            'dataSfarsitEfectiva' => $calcul['date'][count($calcul['date']) - 1],
        ];
    }

    /** @return array{cereri: list<array<string, mixed>>} */
    public function listaManageriala(): array
    {
        $this->verificaManager();
        $cereri = $this->managerEntitati->getRDBRepository('CerereConcediu')
            ->order('dataInceput', 'DESC')->find();

        return ['cereri' => array_values(array_map(
            fn (Entity $cerere): array => $this->serializare($cerere),
            iterator_to_array($cereri)
        ))];
    }

    /** @return array<string, mixed> */
    public function decide(string $idCerere, string $decizie, ?string $motiv): array
    {
        $this->verificaManager();

        if (!in_array($decizie, ['Aproba', 'Respinge'], true)) {
            $this->eroare('decizieConcediuInvalida');
        }

        return $this->managerEntitati->getTransactionManager()->run(
            function () use ($idCerere, $decizie, $motiv): array {
                $cerere = $this->managerEntitati->getRDBRepository('CerereConcediu')
                    ->where(['id' => $idCerere])->forUpdate()->findOne();

                if (!$cerere) {
                    throw new NotFound();
                }

                if (!$this->acl->checkEntityEdit($cerere)) {
                    throw new Forbidden();
                }

                if ($cerere->get('stare') !== 'InAsteptare') {
                    throw Conflict::createWithBody(
                        'cerereDejaDecisa',
                        Body::create()->withMessageTranslation('cerereDejaDecisa', 'CerereConcediu')
                    );
                }

                if ($cerere->get('assignedUserId') === $this->utilizator->getId()) {
                    throw Forbidden::createWithBody(
                        'autoAprobareInterzisa',
                        Body::create()->withMessageTranslation('autoAprobareInterzisa', 'CerereConcediu')
                    );
                }

                $eligibili = array_values(array_filter((array) $cerere->get('aprobatoriNecesari'), 'is_string'));

                if (!in_array($this->utilizator->getId(), $eligibili, true)) {
                    throw new Forbidden();
                }

                $profil = $this->profiluri->obtineInitializat((string) $cerere->get('assignedUserId'), true);

                if ($decizie === 'Respinge') {
                    $this->solduri->elibereaza($cerere, $profil);
                    $cerere->set([
                        'stare' => 'Respinsa',
                        'decisaLa' => date('Y-m-d H:i:s'),
                        'decisaDeId' => $this->utilizator->getId(),
                        'motivDecizie' => $motiv,
                    ]);
                } else {
                    $confirmati = array_values(array_unique([
                        ...array_filter((array) $cerere->get('aprobatoriConfirmati'), 'is_string'),
                        $this->utilizator->getId(),
                    ]));
                    $cerere->set('aprobatoriConfirmati', $confirmati);

                    if (count($confirmati) >= (int) $cerere->get('aprobariNecesare')) {
                        $this->verificaPrezenteExistente($cerere);
                        $cerere->set(
                            'instantaneuDocumente',
                            $this->instantaneuDocumente->creeaza($cerere)
                        );
                        $this->solduri->consuma($cerere, $profil);
                        $cerere->set([
                            'stare' => 'Aprobata',
                            'decisaLa' => date('Y-m-d H:i:s'),
                            'decisaDeId' => $this->utilizator->getId(),
                        ]);
                    }
                }

                $this->managerEntitati->saveEntity($cerere);

                return $this->serializare($cerere);
            }
        );
    }

    /** @return array<string, mixed> */
    public function solicitaAnulare(string $idCerere, ?string $motiv): array
    {
        $this->verificaAngajat();

        return $this->managerEntitati->getTransactionManager()->run(
            function () use ($idCerere, $motiv): array {
                $cerere = $this->managerEntitati->getRDBRepository('CerereConcediu')
                    ->where(['id' => $idCerere])->forUpdate()->findOne();

                if (!$cerere) {
                    throw new NotFound();
                }

                if ($cerere->get('assignedUserId') !== $this->utilizator->getId() ||
                    !$this->acl->checkEntityEdit($cerere)) {
                    throw new Forbidden();
                }

                $stare = (string) $cerere->get('stare');

                if ($stare === 'InAsteptare') {
                    $profil = $this->profiluri->obtineInitializat($this->utilizator->getId(), true);
                    $this->solduri->elibereaza($cerere, $profil);
                    $cerere->set([
                        'stare' => 'Anulata',
                        'anulareSolicitataLa' => date('Y-m-d H:i:s'),
                        'motivAnulare' => $motiv,
                        'anulataLa' => date('Y-m-d H:i:s'),
                        'anulataDeId' => $this->utilizator->getId(),
                    ]);
                    $this->managerEntitati->saveEntity($cerere);
                    $this->notificator->notificaRetragere($cerere);

                    return $this->serializare($cerere) + [
                        'sold' => $this->solduri->sumar($this->utilizator->getId()),
                    ];
                }

                if ($stare !== 'Aprobata') {
                    $this->eroare('cerereNuPoateFiAnulata');
                }

                $azi = $this->dataOra->getToday()->toString();

                if ((string) $cerere->get('dataInceput') <= $azi) {
                    $this->eroare('anulareDupaIncepereaConcediului');
                }

                $motiv = trim((string) $motiv);

                if (mb_strlen($motiv) < 3) {
                    $this->eroare('motivAnulareObligatoriu');
                }

                $cerere->set([
                    'stare' => 'AnulareSolicitata',
                    'anulareSolicitataLa' => date('Y-m-d H:i:s'),
                    'motivAnulare' => $motiv,
                    'aprobatoriAnulareConfirmati' => [],
                ]);
                $this->managerEntitati->saveEntity($cerere);
                $this->notificator->notificaSolicitareAnulare($cerere);

                return $this->serializare($cerere);
            }
        );
    }

    /** @return array<string, mixed> */
    public function decideAnulare(
        string $idCerere,
        string $decizie,
        ?string $motiv,
    ): array {
        $this->verificaManager();

        if (!in_array($decizie, ['Aproba', 'Respinge'], true)) {
            $this->eroare('decizieAnulareInvalida');
        }

        return $this->managerEntitati->getTransactionManager()->run(
            function () use ($idCerere, $decizie, $motiv): array {
                $cerere = $this->managerEntitati->getRDBRepository('CerereConcediu')
                    ->where(['id' => $idCerere])->forUpdate()->findOne();

                if (!$cerere) {
                    throw new NotFound();
                }

                if (!$this->acl->checkEntityEdit($cerere)) {
                    throw new Forbidden();
                }

                if ($cerere->get('stare') !== 'AnulareSolicitata') {
                    $this->eroare('anulareDejaDecisa');
                }

                if ($cerere->get('assignedUserId') === $this->utilizator->getId()) {
                    throw Forbidden::createWithBody(
                        'autoAprobareAnulareInterzisa',
                        Body::create()->withMessageTranslation(
                            'autoAprobareAnulareInterzisa',
                            'CerereConcediu'
                        )
                    );
                }

                $eligibili = array_values(array_filter(
                    (array) $cerere->get('aprobatoriNecesari'),
                    'is_string'
                ));

                if (!in_array($this->utilizator->getId(), $eligibili, true)) {
                    throw new Forbidden();
                }

                if ($decizie === 'Respinge') {
                    $cerere->set([
                        'stare' => 'Aprobata',
                        'motivDecizieAnulare' => $motiv,
                        'aprobatoriAnulareConfirmati' => [],
                    ]);
                    $decizieFinala = true;
                } else {
                    $confirmati = array_values(array_unique([
                        ...array_filter(
                            (array) $cerere->get('aprobatoriAnulareConfirmati'),
                            'is_string'
                        ),
                        $this->utilizator->getId(),
                    ]));
                    $cerere->set('aprobatoriAnulareConfirmati', $confirmati);

                    if (count($confirmati) >= (int) $cerere->get('aprobariNecesare')) {
                        $profil = $this->profiluri->obtineInitializat(
                            (string) $cerere->get('assignedUserId'),
                            true
                        );
                        $motivRestaurare = trim((string) $cerere->get('motivAnulare'));
                        $this->solduri->restaureaza($cerere, $profil, $motivRestaurare);
                        $cerere->set([
                            'stare' => 'Anulata',
                            'anulataLa' => date('Y-m-d H:i:s'),
                            'anulataDeId' => $this->utilizator->getId(),
                            'motivDecizieAnulare' => $motiv,
                        ]);
                        $this->documenteConcediu->stergePentruCerere($cerere);
                        $decizieFinala = true;
                    }
                }

                $this->managerEntitati->saveEntity($cerere);

                if ($decizieFinala ?? false) {
                    $this->notificator->notificaDecizieAnulare(
                        $cerere,
                        $decizie === 'Aproba'
                    );
                }

                return $this->serializare($cerere);
            }
        );
    }

    private function verificaSuprapunere(string $idAngajat, string $inceput, string $sfarsit): void
    {
        $suprapunere = $this->managerEntitati->getRDBRepository('CerereConcediu')
            ->where([
                'assignedUserId' => $idAngajat,
                'stare' => ['InAsteptare', 'Aprobata', 'AnulareSolicitata'],
                'dataInceput<=' => $sfarsit,
                'dataSfarsit>=' => $inceput,
            ])->findOne();

        if ($suprapunere) {
            throw Conflict::createWithBody(
                'cerereConcediuSuprapusa',
                Body::create()->withMessageTranslation('cerereConcediuSuprapusa', 'CerereConcediu')
            );
        }
    }

    private function verificaPrezenteExistente(Entity $cerere): void
    {
        $prezenta = $this->managerEntitati->getRDBRepository('Prezenta')
            ->where([
                'assignedUserId' => $cerere->get('assignedUserId'),
                'dataPrezenta>=' => $cerere->get('dataInceput'),
                'dataPrezenta<=' => $cerere->get('dataSfarsit'),
            ])
            ->findOne();

        if (!$prezenta) {
            return;
        }

        throw Conflict::createWithBody(
            'concediuCuPrezentaExistenta',
            Body::create()->withMessageTranslation(
                'concediuCuPrezentaExistenta',
                'CerereConcediu'
            )
        );
    }

    /** @param list<string> $dateLucratoare */
    private function verificaPrezenteLaCreare(string $idAngajat, array $dateLucratoare): void
    {
        if ($dateLucratoare === []) {
            return;
        }

        $prezenta = $this->managerEntitati->getRDBRepository('Prezenta')
            ->where([
                'assignedUserId' => $idAngajat,
                'dataPrezenta' => $dateLucratoare,
            ])
            ->findOne();

        if (!$prezenta) {
            return;
        }

        throw Conflict::createWithBody(
            'cerereCuPrezentaExistenta',
            Body::create()->withMessageTranslation(
                'cerereCuPrezentaExistenta',
                'CerereConcediu'
            )
        );
    }

    private function verificaAngajat(): void
    {
        if (!$this->utilizator->isRegular() || !$this->utilizator->isActive() ||
            !$this->acl->checkScope('CerereConcediu', Table::ACTION_READ)) {
            throw new Forbidden();
        }
    }

    private function verificaAccesSpatiuPersonal(): void
    {
        if ($this->utilizator->isAdmin()) {
            return;
        }

        $this->verificaAngajat();
    }

    private function verificaManager(): void
    {
        if ($this->utilizator->isAdmin()) {
            return;
        }

        if (!$this->utilizator->isRegular() || !$this->utilizator->isActive() ||
            !$this->acl->checkScope('GestionareAngajati') ||
            !$this->acl->checkScope('CerereConcediu', Table::ACTION_READ) ||
            !$this->acl->checkScope('CerereConcediu', Table::ACTION_EDIT) ||
            $this->acl->getLevel('CerereConcediu', Table::ACTION_READ) !== Table::LEVEL_ALL ||
            $this->acl->getLevel('CerereConcediu', Table::ACTION_EDIT) !== Table::LEVEL_ALL) {
            throw new Forbidden();
        }
    }

    /** @return array<string, mixed> */
    private function serializare(Entity $cerere): array
    {
        $stare = (string) $cerere->get('stare');
        $esteProprie = $cerere->get('assignedUserId') === $this->utilizator->getId();
        $esteInainteDeConcediu = (string) $cerere->get('dataInceput') >
            $this->dataOra->getToday()->toString();

        return [
            'id' => $cerere->getId(),
            'nume' => $cerere->get('name'),
            'idAngajat' => $cerere->get('assignedUserId'),
            'numeAngajat' => $cerere->get('assignedUserName'),
            'dataInceput' => $cerere->get('dataInceput'),
            'dataSfarsit' => $cerere->get('dataSfarsit'),
            'numarZile' => (int) $cerere->get('numarZile'),
            'stare' => $stare,
            'descriere' => $cerere->get('descriere'),
            'motivAnulare' => $cerere->get('motivAnulare'),
            'aprobariNecesare' => (int) $cerere->get('aprobariNecesare'),
            'numarAprobari' => count((array) $cerere->get('aprobatoriConfirmati')),
            'poateFiDecisa' => $stare === 'InAsteptare' &&
                $cerere->get('assignedUserId') !== $this->utilizator->getId() &&
                in_array($this->utilizator->getId(), (array) $cerere->get('aprobatoriNecesari'), true),
            'poateSolicitaAnulare' => $esteProprie && (
                $stare === 'InAsteptare' || ($stare === 'Aprobata' && $esteInainteDeConcediu)
            ),
            'poateDecideAnulare' => $stare === 'AnulareSolicitata' &&
                !$esteProprie &&
                in_array($this->utilizator->getId(), (array) $cerere->get('aprobatoriNecesari'), true) &&
                !in_array(
                    $this->utilizator->getId(),
                    (array) $cerere->get('aprobatoriAnulareConfirmati'),
                    true
                ),
            'numarAprobariAnulare' => count(
                (array) $cerere->get('aprobatoriAnulareConfirmati')
            ),
            'esteAnulareSolicitata' => $stare === 'AnulareSolicitata',
            'poateGeneraDocumente' => in_array(
                $stare,
                ['Aprobata', 'AnulareSolicitata'],
                true
            ) && (is_object($cerere->get('instantaneuDocumente')) ||
                is_array($cerere->get('instantaneuDocumente'))),
            'documente' => $this->serializareDocumente($cerere),
        ];
    }

    /** @return list<array{id: string, nume: string, url: string}> */
    private function serializareDocumente(Entity $cerere): array
    {
        $rezultat = [];
        foreach ($this->managerEntitati->getRDBRepositoryByClass(Attachment::class)->where([
            'parentType' => 'CerereConcediu',
            'parentId' => $cerere->getId(),
            'field' => 'documenteConcediu',
        ])->order('name', 'ASC')->find() as $atasament) {
            if (!$atasament instanceof Attachment) {
                continue;
            }
            $rezultat[] = [
                'id' => $atasament->getId(),
                'nume' => (string) $atasament->getName(),
                'url' => 'api/v1/Attachment/file/' . $atasament->getId(),
            ];
        }
        return $rezultat;
    }

    private function sir(mixed $valoare): ?string
    {
        return is_string($valoare) && trim($valoare) !== '' ? trim($valoare) : null;
    }

    private function esteData(string $valoare): bool
    {
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valoare);

        return $data !== false && $data->format('Y-m-d') === $valoare;
    }

    /** @param array<string, string> $dateTraducere */
    private function eroare(string $mesaj, array $dateTraducere = []): never
    {
        throw BadRequest::createWithBody(
            $mesaj,
            Body::create()->withMessage($this->traduceMesaj($mesaj, $dateTraducere))
        );
    }

    /** @param array<string, string> $dateTraducere */
    private function traduceMesaj(string $cheie, array $dateTraducere): string
    {
        $mesaj = $this->limba->translateLabel($cheie, 'messages', 'CerereConcediu');

        foreach ($dateTraducere as $cheieData => $valoare) {
            $mesaj = str_replace('{' . $cheieData . '}', $valoare, $mesaj);
        }

        return $mesaj;
    }
}
