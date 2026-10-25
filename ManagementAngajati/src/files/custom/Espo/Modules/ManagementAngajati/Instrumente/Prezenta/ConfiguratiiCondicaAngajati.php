<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error\Body;
use Espo\ORM\EntityManager;

class ConfiguratiiCondicaAngajati
{
    public const string TIP_ENTITATE = 'ConfiguratieCondicaAngajat';
    public const string MOD_REMOTE_CURENT = 'curent';
    public const string MOD_REMOTE_INTERVAL = 'interval';

    public const string CHEIE_MOD_REMOTE = 'managementAngajatiModAplicareRemote';
    public const string CHEIE_LUNI_ANTERIOARE_EDITABILE =
        'managementAngajatiNumarLuniAnterioareEditabile';
    public const string CHEIE_PERMITE_EXPORT_CONDICA_INCOMPLETA =
        'managementAngajatiPermiteExportCondicaIncompleta';
    public const int LUNI_ANTERIOARE_EDITABILE_IMPLICIT = 1;

    public function __construct(
        private EntityManager $managerEntitati,
        private Config $configuratie,
        private ConfigWriter $scriitorConfiguratie,
    ) {}

    /**
     * @return array{
     *     remoteActiv: bool,
     *     dataInceputRemote: ?string,
     *     dataSfarsitRemote: ?string,
     *     cicActiv: bool,
     *     dataInceputCic: ?string,
     *     dataSfarsitCic: ?string
     * }
     */
    public function obtinePentruAngajat(string $idAngajat): array
    {
        $entitate = $this->managerEntitati
            ->getRDBRepository(self::TIP_ENTITATE)
            ->where(['angajatId' => $idAngajat])
            ->findOne();

        if (!$entitate) {
            return [
                'remoteActiv' => false,
                'dataInceputRemote' => null,
                'dataSfarsitRemote' => null,
                'cicActiv' => false,
                'dataInceputCic' => null,
                'dataSfarsitCic' => null,
            ];
        }

        return [
            'remoteActiv' => (bool) $entitate->get('remoteActiv'),
            'dataInceputRemote' => $this->sirSauNull($entitate->get('dataInceputRemote')),
            'dataSfarsitRemote' => $this->sirSauNull($entitate->get('dataSfarsitRemote')),
            'cicActiv' => (bool) $entitate->get('cicActiv'),
            'dataInceputCic' => $this->sirSauNull($entitate->get('dataInceputCic')),
            'dataSfarsitCic' => $this->sirSauNull($entitate->get('dataSfarsitCic')),
        ];
    }

    /** @param array<string, bool|string|null> $configuratieAngajat */
    public function esteDataCic(array $configuratieAngajat, string $data): bool
    {
        return $configuratieAngajat['cicActiv'] === true &&
            is_string($configuratieAngajat['dataInceputCic']) &&
            is_string($configuratieAngajat['dataSfarsitCic']) &&
            $data >= $configuratieAngajat['dataInceputCic'] &&
            $data <= $configuratieAngajat['dataSfarsitCic'];
    }

    public function esteAngajatInCic(string $idAngajat, string $data): bool
    {
        return $this->esteDataCic($this->obtinePentruAngajat($idAngajat), $data);
    }

    /**
     * @return array{activa: bool, dataInceput: ?string, dataSfarsit: ?string}
     */
    public function obtineSituatieCic(string $idAngajat, string $data): array
    {
        return $this->descrieSituatieCic(
            $this->obtinePentruAngajat($idAngajat),
            $data
        );
    }

    /**
     * @param array<string, bool|string|null> $configuratieAngajat
     * @return array{activa: bool, dataInceput: ?string, dataSfarsit: ?string}
     */
    public function descrieSituatieCic(array $configuratieAngajat, string $data): array
    {
        return [
            'activa' => $this->esteDataCic($configuratieAngajat, $data),
            'dataInceput' => is_string($configuratieAngajat['dataInceputCic'] ?? null) ?
                $configuratieAngajat['dataInceputCic'] : null,
            'dataSfarsit' => is_string($configuratieAngajat['dataSfarsitCic'] ?? null) ?
                $configuratieAngajat['dataSfarsitCic'] : null,
        ];
    }

    /** @param array<string, bool|string|null> $configuratieAngajat */
    public function esteDataRemote(array $configuratieAngajat, string $data): bool
    {
        if ($configuratieAngajat['remoteActiv'] !== true) {
            return false;
        }

        if ($this->obtineModAplicareRemote() === self::MOD_REMOTE_CURENT) {
            return true;
        }

        return is_string($configuratieAngajat['dataInceputRemote']) &&
            is_string($configuratieAngajat['dataSfarsitRemote']) &&
            $data >= $configuratieAngajat['dataInceputRemote'] &&
            $data <= $configuratieAngajat['dataSfarsitRemote'];
    }

    public function obtineModAplicareRemote(): string
    {
        $mod = $this->configuratie->get(self::CHEIE_MOD_REMOTE, self::MOD_REMOTE_CURENT);

        return $mod === self::MOD_REMOTE_INTERVAL ?
            self::MOD_REMOTE_INTERVAL :
            self::MOD_REMOTE_CURENT;
    }

    public function obtineNumarLuniAnterioareEditabile(): int
    {
        $valoare = $this->configuratie->get(
            self::CHEIE_LUNI_ANTERIOARE_EDITABILE,
            self::LUNI_ANTERIOARE_EDITABILE_IMPLICIT
        );

        return is_int($valoare) && $valoare >= 0 && $valoare <= 120 ?
            $valoare :
            self::LUNI_ANTERIOARE_EDITABILE_IMPLICIT;
    }

    public function permiteExportCondicaIncompleta(): bool
    {
        return $this->configuratie->get(
            self::CHEIE_PERMITE_EXPORT_CONDICA_INCOMPLETA,
            false
        ) === true;
    }

    /** @throws BadRequest */
    public function salveazaSetariGenerale(
        string $mod,
        int $numarLuni,
        bool $permiteExportCondicaIncompleta,
    ): void
    {
        $this->valideazaModAplicareRemote($mod);
        $this->valideazaNumarLuniAnterioareEditabile($numarLuni);
        $this->scriitorConfiguratie->set(self::CHEIE_MOD_REMOTE, $mod);
        $this->scriitorConfiguratie->set(
            self::CHEIE_LUNI_ANTERIOARE_EDITABILE,
            $numarLuni
        );
        $this->scriitorConfiguratie->set(
            self::CHEIE_PERMITE_EXPORT_CONDICA_INCOMPLETA,
            $permiteExportCondicaIncompleta
        );
        $this->scriitorConfiguratie->save();
    }

    /** @throws BadRequest */
    private function valideazaModAplicareRemote(string $mod): void
    {
        if (!in_array($mod, [self::MOD_REMOTE_CURENT, self::MOD_REMOTE_INTERVAL], true)) {
            throw BadRequest::createWithBody(
                'modAplicareRemoteInvalid',
                Body::create()->withMessageTranslation(
                    'modAplicareRemoteInvalid',
                    'ConfiguratieCondicaAngajat'
                )
            );
        }
    }

    /** @throws BadRequest */
    private function valideazaNumarLuniAnterioareEditabile(int $numarLuni): void
    {
        if ($numarLuni < 0 || $numarLuni > 120) {
            throw BadRequest::createWithBody(
                'numarLuniEditabileInvalid',
                Body::create()->withMessageTranslation(
                    'numarLuniEditabileInvalid',
                    'GestionareAngajati'
                )
            );
        }
    }

    private function sirSauNull(mixed $valoare): ?string
    {
        return is_string($valoare) && $valoare !== '' ? $valoare : null;
    }
}
