<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error\Body;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Throwable;

/** Salveaza formularul complet al setarilor condicii ca o singura operatie. */
class SalvatorSetariCondica
{
    public function __construct(
        private EntityManager $managerEntitati,
        private ConfiguratiiCondicaAngajati $configuratii,
        private SetariCondica $setariCondica,
        private User $utilizatorCurent,
    ) {}

    /**
     * @param list<object> $dateAngajati
     * @return array<string, mixed>
     */
    public function salveaza(
        string $modAplicareRemote,
        int $numarLuniAnterioareEditabile,
        array $dateAngajati,
    ): array
    {
        $modAnterior = $this->configuratii->obtineModAplicareRemote();
        $numarLuniAnterior = $this->configuratii->obtineNumarLuniAnterioareEditabile();
        $modSchimbat = $modAnterior !== $modAplicareRemote;
        $numarLuniSchimbat = $numarLuniAnterior !== $numarLuniAnterioareEditabile;

        if ($modSchimbat || $numarLuniSchimbat) {
            $this->configuratii->salveazaSetariGenerale(
                $modAplicareRemote,
                $numarLuniAnterioareEditabile
            );
        }

        try {
            $this->managerEntitati->getTransactionManager()->run(
                fn () => $this->salveazaAngajati($dateAngajati)
            );
        } catch (Throwable $exceptie) {
            if ($modSchimbat || $numarLuniSchimbat) {
                $this->configuratii->salveazaSetariGenerale(
                    $modAnterior,
                    $numarLuniAnterior
                );
            }

            throw $exceptie;
        }

        return $this->setariCondica->obtine();
    }

    /** @param list<object> $dateAngajati */
    private function salveazaAngajati(array $dateAngajati): void
    {
        $idProcesate = [];

        foreach ($dateAngajati as $dateAngajat) {
            $idAngajat = $this->sirSauNull($dateAngajat->angajatId ?? null);

            if (!$idAngajat || isset($idProcesate[$idAngajat])) {
                throw BadRequest::createWithBody(
                    'setariCondicaInvalide',
                    Body::create()->withMessageTranslation(
                        'setariCondicaInvalide',
                        'GestionareAngajati'
                    )
                );
            }

            $idProcesate[$idAngajat] = true;
            $configuratie = $this->managerEntitati
                ->getRDBRepository(ConfiguratiiCondicaAngajati::TIP_ENTITATE)
                ->where(['angajatId' => $idAngajat])
                ->forUpdate()
                ->findOne();
            $remoteActiv = ($dateAngajat->remoteActiv ?? false) === true;
            $cicActiv = ($dateAngajat->cicActiv ?? false) === true;

            if (!$configuratie && !$remoteActiv && !$cicActiv) {
                continue;
            }

            if (!$configuratie) {
                $configuratie = $this->managerEntitati->getNewEntity(
                    ConfiguratiiCondicaAngajati::TIP_ENTITATE
                );
                $configuratie->set('assignedUserId', $this->utilizatorCurent->getId());
            }

            $this->aplicaDate(
                $configuratie,
                $idAngajat,
                $remoteActiv,
                $cicActiv,
                $dateAngajat
            );
            $this->managerEntitati->saveEntity($configuratie);
        }
    }

    private function aplicaDate(
        Entity $configuratie,
        string $idAngajat,
        bool $remoteActiv,
        bool $cicActiv,
        object $dateAngajat,
    ): void {
        $configuratie->set([
            'angajatId' => $idAngajat,
            'remoteActiv' => $remoteActiv,
            'dataInceputRemote' => $this->sirSauNull($dateAngajat->dataInceputRemote ?? null),
            'dataSfarsitRemote' => $this->sirSauNull($dateAngajat->dataSfarsitRemote ?? null),
            'cicActiv' => $cicActiv,
            'dataInceputCic' => $this->sirSauNull($dateAngajat->dataInceputCic ?? null),
            'dataSfarsitCic' => $this->sirSauNull($dateAngajat->dataSfarsitCic ?? null),
        ]);
    }

    private function sirSauNull(mixed $valoare): ?string
    {
        return is_string($valoare) && $valoare !== '' ? $valoare : null;
    }
}
