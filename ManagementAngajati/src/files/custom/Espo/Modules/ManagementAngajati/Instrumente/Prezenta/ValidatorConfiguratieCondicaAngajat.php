<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error\Body;
use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Angajati\EligibilitateAngajat;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class ValidatorConfiguratieCondicaAngajat
{
    private const string TIP_ENTITATE_PREZENTA = 'Prezenta';
    private const string SCOPE_TRADUCERI = 'ConfiguratieCondicaAngajat';

    public function __construct(
        private EntityManager $managerEntitati,
        private ReguliConfiguratieCondicaAngajat $reguli,
        private ConfiguratiiCondicaAngajati $configuratiiCondica,
        private EligibilitateAngajat $eligibilitateAngajat,
    ) {}

    /**
     * @throws BadRequest
     * @throws Conflict
     */
    public function valideaza(Entity $configuratie): void
    {
        $idAngajat = (string) $configuratie->get('angajatId');
        $angajat = $this->obtineAngajatBlocat($idAngajat);
        $this->verificaUnicitatea($configuratie, $idAngajat);

        $cicActiv = (bool) $configuratie->get('cicActiv');
        $remoteActiv = (bool) $configuratie->get('remoteActiv');

        if (!$cicActiv) {
            $configuratie->set([
                'dataInceputCic' => null,
                'dataSfarsitCic' => null,
            ]);
        }

        if (!$remoteActiv) {
            $configuratie->set([
                'dataInceputRemote' => null,
                'dataSfarsitRemote' => null,
            ]);
        }

        $dataInceputCic = $this->sirSauNull($configuratie->get('dataInceputCic'));
        $dataSfarsitCic = $this->sirSauNull($configuratie->get('dataSfarsitCic'));

        $this->reguli->valideazaPerioadaCic(
            $cicActiv,
            $dataInceputCic,
            $dataSfarsitCic,
            $this->eligibilitateAngajat->obtineDataAngajare($angajat),
        );
        $this->reguli->valideazaPerioadaRemote(
            $remoteActiv,
            $this->configuratiiCondica->obtineModAplicareRemote(),
            $this->sirSauNull($configuratie->get('dataInceputRemote')),
            $this->sirSauNull($configuratie->get('dataSfarsitRemote')),
        );

        if ($cicActiv && $dataInceputCic && $dataSfarsitCic) {
            $this->reguli->valideazaFaraPrezenteConflictuale(
                $this->obtineDatePrezenteConflictuale(
                    $idAngajat,
                    $dataInceputCic,
                    $dataSfarsitCic
                )
            );
        }

        $numeAngajat = trim($angajat->getName() ?? '');
        $configuratie->set(
            'name',
            $numeAngajat !== '' ? $numeAngajat : $idAngajat
        );
    }

    /** @throws BadRequest */
    private function obtineAngajatBlocat(string $idAngajat): User
    {
        $angajat = $this->managerEntitati
            ->getRDBRepositoryByClass(User::class)
            ->where([
                'id' => $idAngajat,
                'type' => User::TYPE_REGULAR,
                'isActive' => true,
            ])
            ->forUpdate()
            ->findOne();

        if (!$angajat instanceof User) {
            throw BadRequest::createWithBody(
                'angajatConfiguratieInvalid',
                Body::create()->withMessageTranslation(
                    'angajatConfiguratieInvalid',
                    self::SCOPE_TRADUCERI
                )
            );
        }

        return $angajat;
    }

    /** @throws Conflict */
    private function verificaUnicitatea(Entity $configuratie, string $idAngajat): void
    {
        $existenta = $this->managerEntitati
            ->getRDBRepository(ConfiguratiiCondicaAngajati::TIP_ENTITATE)
            ->where(['angajatId' => $idAngajat])
            ->findOne();

        if (
            $existenta &&
            (!$configuratie->hasId() || $existenta->getId() !== $configuratie->getId())
        ) {
            throw Conflict::createWithBody(
                'configuratiePrezentaDuplicata',
                Body::create()->withMessageTranslation(
                    'configuratiePrezentaDuplicata',
                    self::SCOPE_TRADUCERI
                )
            );
        }
    }

    /** @return list<string> */
    private function obtineDatePrezenteConflictuale(
        string $idAngajat,
        string $dataInceput,
        string $dataSfarsit,
    ): array {
        $rezultat = [];
        $prezente = $this->managerEntitati
            ->getRDBRepository(self::TIP_ENTITATE_PREZENTA)
            ->where([
                'assignedUserId' => $idAngajat,
                'dataPrezenta>=' => $dataInceput,
                'dataPrezenta<=' => $dataSfarsit,
            ])
            ->order('dataPrezenta')
            ->find();

        foreach ($prezente as $prezenta) {
            $data = (string) $prezenta->get('dataPrezenta');

            if ($data !== '') {
                $rezultat[] = $data;
            }
        }

        return $rezultat;
    }

    private function sirSauNull(mixed $valoare): ?string
    {
        return is_string($valoare) && $valoare !== '' ? $valoare : null;
    }
}
