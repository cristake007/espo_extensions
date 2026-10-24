<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Utils\DateTime;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/** Corecteaza numai programul viitor de acordare, fara efecte retroactive. */
class CorectorProgramAcordareConcediu
{
    public function __construct(
        private EntityManager $managerEntitati,
        private DateTime $dataOra,
        private User $utilizator,
    ) {}

    /**
     * @return array{
     *   permis: bool,
     *   codBlocare: ?string,
     *   dataVeche: string,
     *   dataNoua: string,
     *   drepturiAfectate: int,
     *   cereriBlocante: list<array{id: string, nume: string, stare: string, dataInceput: string, dataSfarsit: string}>
     * }
     */
    public function previzualizeaza(string $idAngajat, string $dataNoua): array
    {
        $azi = $this->dataOra->getToday()->toString();
        $profil = $this->managerEntitati->getRDBRepository('ProfilConcediu')
            ->where(['assignedUserId' => $idAngajat, 'initializat' => true])
            ->findOne();

        if (!$profil || !$this->data($dataNoua)) {
            throw BadRequest::createWithBody(
                'corectieProgramAcordareInvalida',
                Body::create()->withMessageTranslation(
                    'corectieProgramAcordareInvalida',
                    'ProfilConcediu'
                )
            );
        }

        $dataVeche = (string) $profil->get('dataUrmatoareiAcordari');
        $codBlocare = null;
        if ($dataNoua <= $azi) {
            $codBlocare = 'dataAcordareTrebuieViitoare';
        } elseif ($dataVeche === $dataNoua) {
            $codBlocare = 'dataAcordareNeschimbata';
        } elseif (substr($dataVeche, 0, 4) !== substr($dataNoua, 0, 4)) {
            $codBlocare = 'anAcordareDiferit';
        }

        $drepturi = $this->drepturiViitoare($profil, $azi, false);
        $cereriBlocante = [];
        $areSoldUtilizat = false;
        foreach ($drepturi as $drept) {
            array_push($cereriBlocante, ...$this->cereriBlocante($drept));
            $areSoldUtilizat = $areSoldUtilizat ||
                (int) $drept->get('zileRezervate') > 0 ||
                (int) $drept->get('zileConsumate') > 0;
        }
        $cereriBlocante = array_values(array_reduce(
            $cereriBlocante,
            static function (array $unice, array $cerere): array {
                $unice[$cerere['id']] = $cerere;
                return $unice;
            },
            []
        ));

        if ($codBlocare === null && ($cereriBlocante !== [] || $areSoldUtilizat)) {
            $codBlocare = 'programAcordareFolositDeCereri';
        }

        return [
            'permis' => $codBlocare === null,
            'codBlocare' => $codBlocare,
            'dataVeche' => $dataVeche,
            'dataNoua' => $dataNoua,
            'drepturiAfectate' => count($drepturi),
            'cereriBlocante' => $cereriBlocante,
        ];
    }

    /** @return array{dataVeche: string, dataNoua: string, drepturiActualizate: int} */
    public function corecteaza(string $idAngajat, string $dataNoua, string $motiv): array
    {
        $motiv = trim($motiv);
        $data = $this->data($dataNoua);
        $azi = $this->dataOra->getToday()->toString();

        if ($idAngajat === '' || !$data || mb_strlen($motiv) < 3) {
            throw BadRequest::createWithBody(
                'corectieProgramAcordareInvalida',
                Body::create()->withMessageTranslation(
                    'corectieProgramAcordareInvalida',
                    'ProfilConcediu'
                )
            );
        }
        if ($dataNoua <= $azi) {
            throw BadRequest::createWithBody(
                'dataAcordareTrebuieViitoare',
                Body::create()->withMessageTranslation(
                    'dataAcordareTrebuieViitoare',
                    'ProfilConcediu'
                )
            );
        }

        return $this->managerEntitati->getTransactionManager()->run(
            fn (): array => $this->corecteazaBlocat($idAngajat, $dataNoua, $motiv, $azi)
        );
    }

    /** @return array{dataVeche: string, dataNoua: string, drepturiActualizate: int} */
    private function corecteazaBlocat(
        string $idAngajat,
        string $dataNoua,
        string $motiv,
        string $azi,
    ): array {
        $profil = $this->managerEntitati->getRDBRepository('ProfilConcediu')
            ->where(['assignedUserId' => $idAngajat, 'initializat' => true])
            ->forUpdate()
            ->findOne();

        if (!$profil) {
            throw Conflict::createWithBody(
                'profilConcediuNeinitializat',
                Body::create()->withMessageTranslation('profilConcediuNeinitializat', 'ProfilConcediu')
            );
        }

        $dataVeche = (string) $profil->get('dataUrmatoareiAcordari');
        if ($dataVeche === $dataNoua) {
            throw BadRequest::createWithBody(
                'dataAcordareNeschimbata',
                Body::create()->withMessageTranslation('dataAcordareNeschimbata', 'ProfilConcediu')
            );
        }
        if (substr($dataVeche, 0, 4) !== substr($dataNoua, 0, 4)) {
            throw Conflict::createWithBody(
                'anAcordareDiferit',
                Body::create()->withMessageTranslation('anAcordareDiferit', 'ProfilConcediu')
            );
        }

        $drepturiViitoare = $this->drepturiViitoare($profil, $azi, true);

        foreach ($drepturiViitoare as $drept) {
            if ($this->cereriBlocante($drept) !== [] ||
                (int) $drept->get('zileRezervate') > 0 ||
                (int) $drept->get('zileConsumate') > 0) {
                throw Conflict::createWithBody(
                    'programAcordareFolositDeCereri',
                    Body::create()->withMessageTranslation(
                        'programAcordareFolositDeCereri',
                        'ProfilConcediu'
                    )
                );
            }
        }

        $lunaZi = substr($dataNoua, 4);
        foreach ($drepturiViitoare as $drept) {
            $dataDrept = sprintf('%04d%s', (int) $drept->get('anOrigine'), $lunaZi);
            if (!$this->data($dataDrept)) {
                throw Conflict::createWithBody(
                    'programAcordareDataInvalida',
                    Body::create()->withMessageTranslation(
                        'programAcordareDataInvalida',
                        'ProfilConcediu'
                    )
                );
            }
            $drept->set([
                'dataAcordare' => $dataDrept,
                'zileAcordate' => (int) $profil->get('zileAnuale'),
            ]);
            $this->managerEntitati->saveEntity($drept);
        }

        $profil->set('dataUrmatoareiAcordari', $dataNoua);
        $this->managerEntitati->saveEntity($profil);
        $this->inregistreaza($profil, $dataVeche, $dataNoua, $motiv);

        return [
            'dataVeche' => $dataVeche,
            'dataNoua' => $dataNoua,
            'drepturiActualizate' => count($drepturiViitoare),
        ];
    }

    /** @return list<Entity> */
    private function drepturiViitoare(Entity $profil, string $azi, bool $blocheaza): array
    {
        $interogare = $this->managerEntitati->getRDBRepository('DreptConcediu')
            ->where(['profilId' => $profil->getId(), 'tip' => 'Anual'])
            ->order('anOrigine', 'ASC');
        if ($blocheaza) {
            $interogare = $interogare->forUpdate();
        }

        $rezultat = [];
        foreach ($interogare->find() as $drept) {
            if ((string) $drept->get('dataAcordare') > $azi) {
                $rezultat[] = $drept;
            }
        }

        return $rezultat;
    }

    /** @return list<array{id: string, nume: string, stare: string, dataInceput: string, dataSfarsit: string}> */
    private function cereriBlocante(Entity $drept): array
    {
        $rezultat = [];
        foreach ($this->managerEntitati->getRDBRepository('AlocareConcediu')
            ->where(['dreptId' => $drept->getId()])
            ->find() as $alocare) {
            if (!in_array($alocare->get('stare'), ['Rezervata', 'Consumata'], true)) {
                continue;
            }
            $cerere = $this->managerEntitati->getEntityById(
                'CerereConcediu',
                (string) $alocare->get('cerereId')
            );
            if (!$cerere || !$cerere->getId()) {
                $idCerere = (string) $alocare->get('cerereId');
                $rezultat[] = [
                    'id' => $idCerere,
                    'nume' => $idCerere,
                    'stare' => (string) $alocare->get('stare'),
                    'dataInceput' => '',
                    'dataSfarsit' => '',
                ];
                continue;
            }
            $rezultat[] = [
                'id' => $cerere->getId(),
                'nume' => (string) ($cerere->get('name') ?? $cerere->getId()),
                'stare' => (string) $cerere->get('stare'),
                'dataInceput' => (string) $cerere->get('dataInceput'),
                'dataSfarsit' => (string) $cerere->get('dataSfarsit'),
            ];
        }

        return $rezultat;
    }

    private function inregistreaza(
        Entity $profil,
        string $dataVeche,
        string $dataNoua,
        string $motiv,
    ): void {
        $registru = $this->managerEntitati->getNewEntity('RegistruConcediu');
        $registru->set([
            'name' => 'Corectie program acordare · ' . $profil->get('assignedUserName'),
            'profilId' => $profil->getId(),
            'tip' => 'CorectieProgramAcordare',
            'numarZile' => 0,
            'motiv' => sprintf('%s (%s → %s)', $motiv, $dataVeche, $dataNoua),
            'cheieOperatie' => sprintf(
                'corectie-program:%s:%s',
                $profil->getId(),
                bin2hex(random_bytes(8))
            ),
            'dataEfectiva' => $this->dataOra->getToday()->toString(),
            'assignedUserId' => $profil->get('assignedUserId'),
            'actorId' => $this->utilizator->getId(),
        ]);
        $this->managerEntitati->saveEntity($registru);
    }

    private function data(string $valoare): ?DateTimeImmutable
    {
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valoare);

        return $data !== false && $data->format('Y-m-d') === $valoare ? $data : null;
    }
}
