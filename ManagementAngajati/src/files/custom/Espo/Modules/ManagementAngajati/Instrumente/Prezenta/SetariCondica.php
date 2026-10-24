<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Angajati\OrdonatorAngajati;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class SetariCondica
{
    public function __construct(
        private EntityManager $managerEntitati,
        private ConfiguratiiCondicaAngajati $configuratii,
        private OrdonatorAngajati $ordonatorAngajati,
    ) {}

    /**
     * @return array{
     *     modAplicareRemote: string,
     *     numarLuniAnterioareEditabile: int,
     *     angajati: list<array{
     *         idAngajat: string,
     *         numeAngajat: string,
     *         dataAngajare: ?string,
     *         areCalendarLucru: bool,
     *         idConfiguratie: ?string,
     *         remoteActiv: bool,
     *         dataInceputRemote: ?string,
     *         dataSfarsitRemote: ?string,
     *         cicActiv: bool,
     *         dataInceputCic: ?string,
     *         dataSfarsitCic: ?string
     *     }>
     * }
     */
    public function obtine(): array
    {
        $configuratiiDupaAngajat = [];
        $configuratii = $this->managerEntitati
            ->getRDBRepository(ConfiguratiiCondicaAngajati::TIP_ENTITATE)
            ->find();

        foreach ($configuratii as $configuratie) {
            $idAngajat = $this->sirSauNull($configuratie->get('angajatId'));

            if ($idAngajat) {
                $configuratiiDupaAngajat[$idAngajat] = $configuratie;
            }
        }

        $angajati = $this->ordonatorAngajati->ordoneaza(
            $this->managerEntitati
                ->getRDBRepositoryByClass(User::class)
                ->where([
                    'type' => User::TYPE_REGULAR,
                    'isActive' => true,
                ])
                ->find()
        );
        $rezultat = [];

        foreach ($angajati as $angajat) {
            $idAngajat = $angajat->getId();
            $configuratie = $configuratiiDupaAngajat[$idAngajat] ?? null;
            $nume = trim($angajat->getName() ?? '');

            $rezultat[] = [
                'idAngajat' => $idAngajat,
                'numeAngajat' => $nume !== '' ? $nume : (string) $angajat->get('userName'),
                'dataAngajare' => $this->sirSauNull($angajat->get('dataAngajare')),
                'areCalendarLucru' => (string) $angajat->get('workingTimeCalendarId') !== '',
                ...$this->pregatesteConfiguratie($configuratie),
            ];
        }

        return [
            'modAplicareRemote' => $this->configuratii->obtineModAplicareRemote(),
            'numarLuniAnterioareEditabile' =>
                $this->configuratii->obtineNumarLuniAnterioareEditabile(),
            'angajati' => $rezultat,
        ];
    }

    /**
     * @return array{
     *     idConfiguratie: ?string,
     *     remoteActiv: bool,
     *     dataInceputRemote: ?string,
     *     dataSfarsitRemote: ?string,
     *     cicActiv: bool,
     *     dataInceputCic: ?string,
     *     dataSfarsitCic: ?string
     * }
     */
    private function pregatesteConfiguratie(?Entity $configuratie): array
    {
        return [
            'idConfiguratie' => $configuratie?->getId(),
            'remoteActiv' => (bool) $configuratie?->get('remoteActiv'),
            'dataInceputRemote' => $this->sirSauNull($configuratie?->get('dataInceputRemote')),
            'dataSfarsitRemote' => $this->sirSauNull($configuratie?->get('dataSfarsitRemote')),
            'cicActiv' => (bool) $configuratie?->get('cicActiv'),
            'dataInceputCic' => $this->sirSauNull($configuratie?->get('dataInceputCic')),
            'dataSfarsitCic' => $this->sirSauNull($configuratie?->get('dataSfarsitCic')),
        ];
    }

    private function sirSauNull(mixed $valoare): ?string
    {
        return is_string($valoare) && $valoare !== '' ? $valoare : null;
    }
}
