<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Utils\DateTime;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Util;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/** Gestioneaza transele anuale si alocarile, consumand intotdeauna drepturile cele mai vechi. */
class SolduriConcediu
{
    public function __construct(
        private EntityManager $managerEntitati,
        private User $utilizator,
        private Language $limba,
        private DateTime $dataOra,
        private AcordariAnualeConcediu $acordariAnuale,
    ) {}

    /** @return array{disponibile: int, rezervate: int, consumate: int, peAni: list<array<string, int|string>>} */
    public function sumar(string $idAngajat): array
    {
        $disponibile = $rezervate = 0;
        $peAni = [];
        $drepturiActive = [];
        $azi = $this->dataOra->getToday()->toString();

        foreach ($this->obtineDrepturi($idAngajat, false) as $drept) {
            if ((string) $drept->get('dataAcordare') > $azi) {
                continue;
            }

            $drepturiActive[] = $drept;
            $acordate = (int) $drept->get('zileAcordate');
            $rezervat = (int) $drept->get('zileRezervate');
            $consumat = (int) $drept->get('zileConsumate');
            $ramase = max(0, $acordate - $rezervat - $consumat);
            $disponibile += $ramase;
            $rezervate += $rezervat;
            $peAni[] = [
                'anOrigine' => (int) $drept->get('anOrigine'),
                'tip' => (string) $drept->get('tip'),
                'acordate' => $acordate,
                'rezervate' => $rezervat,
                'consumate' => $consumat,
                'disponibile' => $ramase,
            ];
        }

        $consumate = $this->calculeazaConsumateInAn(
            $drepturiActive,
            (int) substr($this->dataOra->getToday()->toString(), 0, 4)
        );

        return compact('disponibile', 'rezervate', 'consumate', 'peAni');
    }

    /** @return list<array<string, int|string|null>> */
    public function istoric(string $idProfil, int $limita = 5): array
    {
        $inregistrari = $this->managerEntitati->getRDBRepository('RegistruConcediu')
            ->where(['profilId' => $idProfil])
            ->order('createdAt', 'DESC')
            ->limit(0, $limita)
            ->find();
        $rezultat = [];

        foreach ($inregistrari as $inregistrare) {
            $rezultat[] = [
                'tip' => (string) $inregistrare->get('tip'),
                'numarZile' => (int) $inregistrare->get('numarZile'),
                'motiv' => $inregistrare->get('motiv'),
                'dataEfectiva' => (string) $inregistrare->get('dataEfectiva'),
                'actor' => $inregistrare->get('actorName') ?? $inregistrare->get('actorId'),
            ];
        }

        return $rezultat;
    }

    /**
     * Reconstituie soldul disponibil imediat dupa fiecare cerere, exclusiv din ledger.
     *
     * @param list<Entity> $cereri
     * @return list<array<string, bool|int|string|null>>
     */
    public function cronologieCereri(Entity $profil, array $cereri): array
    {
        $cereriDupaId = [];

        foreach ($cereri as $cerere) {
            $cereriDupaId[$cerere->getId()] = $cerere;
        }

        if ($cereriDupaId === []) {
            return [];
        }

        $inregistrari = $this->managerEntitati->getRDBRepository('RegistruConcediu')
            ->where(['profilId' => $profil->getId()])
            ->order('createdAt', 'ASC')
            ->order('id', 'ASC')
            ->find();
        $soldDisponibil = 0;
        $cronologie = [];
        $pozitiiOperatii = [];

        foreach ($inregistrari as $inregistrare) {
            $tip = (string) $inregistrare->get('tip');
            $numarZile = (int) $inregistrare->get('numarZile');
            $soldInainte = $soldDisponibil;

            if (in_array($tip, [
                'Initializare',
                'AcordareAnuala',
                'Corectie',
                'Rezervare',
                'Eliberare',
                'Restaurare',
            ], true)) {
                $soldDisponibil += $numarZile;
            }

            $idCerere = (string) $inregistrare->get('cerereId');

            if (!in_array($tip, ['Rezervare', 'Eliberare', 'Restaurare'], true)) {
                continue;
            }

            $cerere = $cereriDupaId[$idCerere] ?? null;

            if (!$cerere instanceof Entity) {
                continue;
            }

            if ($cronologie === []) {
                $cronologie[] = [
                    'idCerere' => null,
                    'esteInitializare' => true,
                    'tipEveniment' => 'Initializare',
                    'dataInceput' => null,
                    'dataSfarsit' => null,
                    'numarZile' => 0,
                    'stare' => 'Initializare',
                    'descriere' => null,
                    'soldInainte' => $soldInainte,
                    'soldDupa' => $soldInainte,
                    'zileRezervate' => 0,
                    'zileRestituite' => 0,
                    'impactFinal' => 0,
                    'dataOperatie' => null,
                    'motivOperatie' => null,
                ];
            }

            $tipEveniment = $tip === 'Rezervare' ? 'Rezervare' : 'Restituire';
            $cheieOperatie = $tipEveniment . ':' . $idCerere;

            if (!array_key_exists($cheieOperatie, $pozitiiOperatii)) {
                $pozitiiOperatii[$cheieOperatie] = count($cronologie);
                $cronologie[] = [
                    'idCerere' => $idCerere,
                    'esteInitializare' => false,
                    'tipEveniment' => $tipEveniment,
                    'dataInceput' => (string) $cerere->get('dataInceput'),
                    'dataSfarsit' => (string) $cerere->get('dataSfarsit'),
                    'numarZile' => abs($numarZile),
                    'stare' => (string) $cerere->get('stare'),
                    'descriere' => $cerere->get('descriere'),
                    'soldInainte' => $soldInainte,
                    'soldDupa' => $soldDisponibil,
                    'zileRezervate' => $tipEveniment === 'Rezervare' ? abs($numarZile) : 0,
                    'zileRestituite' => $tipEveniment === 'Restituire' ? abs($numarZile) : 0,
                    'impactFinal' => $numarZile,
                    'dataOperatie' => (string) $inregistrare->get('dataEfectiva'),
                    'motivOperatie' => $inregistrare->get('motiv'),
                ];
                continue;
            }

            $pozitie = $pozitiiOperatii[$cheieOperatie];
            $cronologie[$pozitie]['soldDupa'] = $soldDisponibil;
            $cronologie[$pozitie]['numarZile'] += abs($numarZile);
            $cronologie[$pozitie][
                $tipEveniment === 'Rezervare' ? 'zileRezervate' : 'zileRestituite'
            ] += abs($numarZile);
            $cronologie[$pozitie]['impactFinal'] += $numarZile;

            if (!$cronologie[$pozitie]['motivOperatie'] && $inregistrare->get('motiv')) {
                $cronologie[$pozitie]['motivOperatie'] = $inregistrare->get('motiv');
            }
        }

        return array_values($cronologie);
    }

    /** @return array{sold: array<string, mixed>, istoric: list<array<string, int|string|null>>} */
    public function corecteaza(string $idAngajat, int $diferenta, string $motiv): array
    {
        $motiv = trim($motiv);

        if ($diferenta === 0 || mb_strlen($motiv) < 3) {
            throw BadRequest::createWithBody(
                'corectieSoldInvalida',
                Body::create()->withMessage(
                    $this->traduceMesaj('corectieSoldInvalida', [], 'ProfilConcediu')
                )
            );
        }

        return $this->managerEntitati->getTransactionManager()->run(
            function () use ($idAngajat, $diferenta, $motiv): array {
                $profil = $this->managerEntitati->getRDBRepository('ProfilConcediu')
                    ->where(['assignedUserId' => $idAngajat, 'initializat' => true])
                    ->forUpdate()
                    ->findOne();

                if (!$profil) {
                    throw BadRequest::createWithBody(
                        'profilConcediuNeinitializat',
                        Body::create()->withMessage(
                            $this->traduceMesaj(
                                'profilConcediuNeinitializat',
                                [],
                                'ProfilConcediu'
                            )
                        )
                    );
                }

                $drepturi = $this->obtineDrepturi($idAngajat, true);

                if ($diferenta > 0) {
                    $this->adaugaCorectiePozitiva($profil, $drepturi, $diferenta);
                } else {
                    $this->aplicaCorectieNegativa($drepturi, abs($diferenta));
                }

                $this->inregistreaza(
                    $profil,
                    null,
                    null,
                    'Corectie',
                    $diferenta,
                    'corectie:' . Util::generateCryptId(),
                    $motiv
                );

                return [
                    'sold' => $this->sumar($idAngajat),
                    'istoric' => $this->istoric($profil->getId()),
                ];
            }
        );
    }

    /** @param list<Entity> $drepturi */
    private function calculeazaConsumateInAn(array $drepturi, int $an): int
    {
        if ($drepturi === []) {
            return 0;
        }

        $drepturiIds = [];
        $total = 0;

        foreach ($drepturi as $drept) {
            $drepturiIds[] = $drept->getId();

            if ((int) $drept->get('anOrigine') === $an) {
                $total += (int) $drept->get('zileConsumateInitial');
            }
        }

        foreach ($this->managerEntitati->getRDBRepository('AlocareConcediu')->where([
            'dreptId' => $drepturiIds,
            'anConsum' => $an,
            'stare' => 'Consumata',
        ])->find() as $alocare) {
            $total += (int) $alocare->get('numarZile');
        }

        return $total;
    }

    /** @param list<Entity> $drepturi */
    private function adaugaCorectiePozitiva(Entity $profil, array $drepturi, int $zile): void
    {
        $azi = $this->dataOra->getToday()->toString();
        $an = (int) substr($azi, 0, 4);
        $dreptCorectie = null;

        foreach ($drepturi as $drept) {
            if ((int) $drept->get('anOrigine') === $an && $drept->get('tip') === 'Corectie') {
                $dreptCorectie = $drept;
                break;
            }
        }

        if (!$dreptCorectie) {
            $dreptCorectie = $this->managerEntitati->getNewEntity('DreptConcediu');
            $dreptCorectie->set([
                'name' => sprintf('%s · %d · Corectie', $profil->get('assignedUserName'), $an),
                'profilId' => $profil->getId(),
                'assignedUserId' => $profil->get('assignedUserId'),
                'assignedUserName' => $profil->get('assignedUserName'),
                'anOrigine' => $an,
                'tip' => 'Corectie',
                'zileAcordate' => 0,
                'zileRezervate' => 0,
                'zileConsumate' => 0,
                'zileConsumateInitial' => 0,
                'dataAcordare' => $azi,
            ]);
        }

        $dreptCorectie->set(
            'zileAcordate',
            (int) $dreptCorectie->get('zileAcordate') + $zile
        );
        $this->managerEntitati->saveEntity($dreptCorectie);
    }

    /** @param list<Entity> $drepturi */
    private function aplicaCorectieNegativa(array $drepturi, int $zile): void
    {
        $disponibile = 0;

        foreach ($drepturi as $drept) {
            if ((string) $drept->get('dataAcordare') > $this->dataOra->getToday()->toString()) {
                continue;
            }

            $disponibile += max(
                0,
                (int) $drept->get('zileAcordate') -
                (int) $drept->get('zileRezervate') -
                (int) $drept->get('zileConsumate')
            );
        }

        if ($zile > $disponibile) {
            throw Conflict::createWithBody(
                'corectieSoldPesteDisponibil',
                Body::create()->withMessage($this->traduceMesaj(
                    'corectieSoldPesteDisponibil',
                    [
                        'scadere' => (string) $zile,
                        'disponibile' => (string) $disponibile,
                    ],
                    'ProfilConcediu'
                ))
            );
        }

        $ramase = $zile;

        foreach ($drepturi as $drept) {
            if ((string) $drept->get('dataAcordare') > $this->dataOra->getToday()->toString()) {
                continue;
            }

            $disponibilDrept = max(
                0,
                (int) $drept->get('zileAcordate') -
                (int) $drept->get('zileRezervate') -
                (int) $drept->get('zileConsumate')
            );
            $scadere = min($ramase, $disponibilDrept);

            if ($scadere === 0) {
                continue;
            }

            $drept->set('zileAcordate', (int) $drept->get('zileAcordate') - $scadere);
            $this->managerEntitati->saveEntity($drept);
            $ramase -= $scadere;

            if ($ramase === 0) {
                break;
            }
        }
    }

    /** @param list<string> $dateLucratoare */
    public function rezerva(Entity $cerere, Entity $profil, array $dateLucratoare): void
    {
        $ani = array_map(static fn (string $data): int => (int) substr($data, 0, 4), $dateLucratoare);
        $this->acordariAnuale->pregatestePentruAni($profil, $ani);
        $drepturi = $this->obtineDrepturi((string) $profil->get('assignedUserId'), true);
        $necesarePeAni = array_count_values($ani);
        $alocatePeAni = [];
        /** @var array<string, array{drept: Entity, an: int, zile: int}> $alocari */
        $alocari = [];

        foreach ($dateLucratoare as $dataConsum) {
            $anConsum = (int) substr($dataConsum, 0, 4);
            $dreptSelectat = null;

            foreach ($drepturi as $drept) {
                if ((int) $drept->get('anOrigine') > $anConsum ||
                    (string) $drept->get('dataAcordare') > $dataConsum) {
                    continue;
                }

                $disponibil = max(0,
                    (int) $drept->get('zileAcordate') -
                    (int) $drept->get('zileRezervate') -
                    (int) $drept->get('zileConsumate')
                );
                if ($disponibil === 0) {
                    continue;
                }

                $dreptSelectat = $drept;
                break;
            }

            if (!$dreptSelectat) {
                throw Conflict::createWithBody(
                    'soldConcediuInsuficient',
                    Body::create()->withMessage($this->traduceMesaj(
                        'soldConcediuInsuficientDetaliat',
                        [
                            'an' => (string) $anConsum,
                            'necesare' => (string) ($necesarePeAni[$anConsum] ?? 0),
                            'disponibile' => (string) ($alocatePeAni[$anConsum] ?? 0),
                        ]
                    ))
                );
            }

            $dreptSelectat->set(
                'zileRezervate',
                (int) $dreptSelectat->get('zileRezervate') + 1
            );
            $alocatePeAni[$anConsum] = ($alocatePeAni[$anConsum] ?? 0) + 1;
            $cheie = $dreptSelectat->getId() . ':' . $anConsum;
            $alocari[$cheie] ??= ['drept' => $dreptSelectat, 'an' => $anConsum, 'zile' => 0];
            $alocari[$cheie]['zile']++;
        }

        foreach ($drepturi as $drept) {
            if (array_filter(
                $alocari,
                static fn (array $alocare): bool => $alocare['drept'] === $drept
            ) === []) {
                continue;
            }

            $this->managerEntitati->saveEntity($drept);
        }

        foreach ($alocari as $alocare) {
            $this->creeazaAlocare($cerere, $alocare['drept'], $alocare['an'], $alocare['zile']);
            $this->inregistreaza(
                $profil,
                $cerere,
                $alocare['drept'],
                'Rezervare',
                -$alocare['zile'],
                'rezervare:' . $alocare['an']
            );
        }
    }

    public function consuma(Entity $cerere, Entity $profil): void
    {
        foreach ($this->obtineAlocari($cerere, true) as $alocare) {
            if ($alocare->get('stare') !== 'Rezervata') {
                continue;
            }

            $drept = $this->managerEntitati->getRDBRepository('DreptConcediu')
                ->where(['id' => $alocare->get('dreptId')])->forUpdate()->findOne();

            if (!$drept) {
                throw Conflict::createWithBody(
                    'dreptConcediuLipsa',
                    Body::create()->withMessageTranslation('dreptConcediuLipsa', 'CerereConcediu')
                );
            }

            $zile = (int) $alocare->get('numarZile');
            $drept->set([
                'zileRezervate' => max(0, (int) $drept->get('zileRezervate') - $zile),
                'zileConsumate' => (int) $drept->get('zileConsumate') + $zile,
            ]);
            $alocare->set('stare', 'Consumata');
            $this->managerEntitati->saveEntity($drept);
            $this->managerEntitati->saveEntity($alocare);
            $this->inregistreaza(
                $profil,
                $cerere,
                $drept,
                'Consumare',
                -$zile,
                'consumare:' . $alocare->getId()
            );
        }
    }

    public function elibereaza(Entity $cerere, Entity $profil): void
    {
        foreach ($this->obtineAlocari($cerere, true) as $alocare) {
            if ($alocare->get('stare') !== 'Rezervata') {
                continue;
            }

            $drept = $this->managerEntitati->getRDBRepository('DreptConcediu')
                ->where(['id' => $alocare->get('dreptId')])->forUpdate()->findOne();

            if (!$drept) {
                throw Conflict::createWithBody(
                    'dreptConcediuLipsa',
                    Body::create()->withMessageTranslation('dreptConcediuLipsa', 'CerereConcediu')
                );
            }

            $zile = (int) $alocare->get('numarZile');
            $drept->set('zileRezervate', max(0, (int) $drept->get('zileRezervate') - $zile));
            $alocare->set('stare', 'Eliberata');
            $this->managerEntitati->saveEntity($drept);
            $this->managerEntitati->saveEntity($alocare);
            $this->inregistreaza(
                $profil,
                $cerere,
                $drept,
                'Eliberare',
                $zile,
                'eliberare:' . $alocare->getId()
            );
        }
    }

    /**
     * Reface exact transele consumate de o cerere aprobata anulata.
     * Alocarile pastrate fac operatia determinista si conserva ordinea FIFO initiala.
     */
    public function restaureaza(Entity $cerere, Entity $profil, string $motiv): void
    {
        foreach ($this->obtineAlocari($cerere, true) as $alocare) {
            if ($alocare->get('stare') !== 'Consumata') {
                continue;
            }

            $drept = $this->managerEntitati->getRDBRepository('DreptConcediu')
                ->where(['id' => $alocare->get('dreptId')])->forUpdate()->findOne();

            if (!$drept) {
                throw Conflict::createWithBody(
                    'dreptConcediuLipsa',
                    Body::create()->withMessageTranslation('dreptConcediuLipsa', 'CerereConcediu')
                );
            }

            $zile = (int) $alocare->get('numarZile');
            $consumate = (int) $drept->get('zileConsumate');

            $consumateInitial = (int) $drept->get('zileConsumateInitial');

            if ($zile > $consumate || $consumate - $zile < $consumateInitial) {
                throw Conflict::createWithBody(
                    'soldConcediuInconsistent',
                    Body::create()->withMessageTranslation('soldConcediuInconsistent', 'CerereConcediu')
                );
            }

            $drept->set('zileConsumate', $consumate - $zile);
            $alocare->set('stare', 'Eliberata');
            $this->managerEntitati->saveEntity($drept);
            $this->managerEntitati->saveEntity($alocare);
            $this->inregistreaza(
                $profil,
                $cerere,
                $drept,
                'Restaurare',
                $zile,
                'restaurare:' . $alocare->getId(),
                $motiv
            );
        }
    }

    /** @return list<Entity> */
    private function obtineDrepturi(string $idAngajat, bool $blocheaza): array
    {
        $interogare = $this->managerEntitati->getRDBRepository('DreptConcediu')
            ->where(['assignedUserId' => $idAngajat])
            ->order('anOrigine', 'ASC')
            ->order('dataAcordare', 'ASC');

        if ($blocheaza) {
            $interogare = $interogare->forUpdate();
        }

        return array_values(iterator_to_array($interogare->find()));
    }

    /** @return list<Entity> */
    private function obtineAlocari(Entity $cerere, bool $blocheaza): array
    {
        $interogare = $this->managerEntitati->getRDBRepository('AlocareConcediu')
            ->where(['cerereId' => $cerere->getId()]);

        if ($blocheaza) {
            $interogare = $interogare->forUpdate();
        }

        return array_values(iterator_to_array($interogare->find()));
    }

    private function creeazaAlocare(Entity $cerere, Entity $drept, int $anConsum, int $zile): void
    {
        $alocare = $this->managerEntitati->getNewEntity('AlocareConcediu');
        $alocare->set([
            'name' => sprintf('%s · %d · %s', $cerere->get('name'), $anConsum, $drept->get('name')),
            'cerereId' => $cerere->getId(),
            'dreptId' => $drept->getId(),
            'anConsum' => $anConsum,
            'numarZile' => $zile,
            'stare' => 'Rezervata',
        ]);
        $this->managerEntitati->saveEntity($alocare);
    }

    private function inregistreaza(
        Entity $profil,
        ?Entity $cerere,
        ?Entity $drept,
        string $tip,
        int $zile,
        string $operatie,
        ?string $motiv = null,
    ): void {
        $registru = $this->managerEntitati->getNewEntity('RegistruConcediu');
        $cheie = implode(':', [$operatie, $cerere?->getId() ?? $profil->getId(), $drept?->getId() ?? '-', abs($zile)]);
        $registru->set([
            'name' => $tip . ' · ' . $profil->get('name'),
            'profilId' => $profil->getId(),
            'cerereId' => $cerere?->getId(),
            'dreptId' => $drept?->getId(),
            'tip' => $tip,
            'numarZile' => $zile,
            'motiv' => $motiv,
            'cheieOperatie' => $cheie,
            'dataEfectiva' => date('Y-m-d'),
            'assignedUserId' => $profil->get('assignedUserId'),
            'actorId' => $this->utilizator->getId(),
        ]);
        $this->managerEntitati->saveEntity($registru);
    }

    /** @param array<string, string> $dateTraducere */
    private function traduceMesaj(
        string $cheie,
        array $dateTraducere,
        string $scope = 'CerereConcediu',
    ): string
    {
        $mesaj = $this->limba->translateLabel($cheie, 'messages', $scope);

        foreach ($dateTraducere as $cheieData => $valoare) {
            $mesaj = str_replace('{' . $cheieData . '}', $valoare, $mesaj);
        }

        return $mesaj;
    }
}
