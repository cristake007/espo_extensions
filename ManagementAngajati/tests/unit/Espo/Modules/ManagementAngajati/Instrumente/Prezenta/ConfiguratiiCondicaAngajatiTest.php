<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\ConfiguratiiCondicaAngajati;
use Espo\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

class ConfiguratiiCondicaAngajatiTest extends TestCase
{
    public function testCicSeAplicaInclusivLaCapeteleIntervalului(): void
    {
        $serviciu = $this->creeazaServiciu();
        $configuratie = $this->configuratieCic();

        self::assertFalse($serviciu->esteDataCic($configuratie, '2026-10-31'));
        self::assertTrue($serviciu->esteDataCic($configuratie, '2026-11-01'));
        self::assertTrue($serviciu->esteDataCic($configuratie, '2027-06-30'));
        self::assertFalse($serviciu->esteDataCic($configuratie, '2027-07-01'));
    }

    public function testCicSeAplicaFaraLimitaArtificialaPeUnIntervalDeDoiAni(): void
    {
        $serviciu = $this->creeazaServiciu();
        $configuratie = [
            'cicActiv' => true,
            'dataInceputCic' => '2026-11-01',
            'dataSfarsitCic' => '2028-10-31',
        ];

        self::assertTrue($serviciu->esteDataCic($configuratie, '2026-11-01'));
        self::assertTrue($serviciu->esteDataCic($configuratie, '2027-11-01'));
        self::assertTrue($serviciu->esteDataCic($configuratie, '2028-10-31'));
        self::assertFalse($serviciu->esteDataCic($configuratie, '2028-11-01'));
    }

    public function testDescrieStareaCicIndiferentDeExistentaUneiZileInCalendar(): void
    {
        $serviciu = $this->creeazaServiciu();
        $configuratie = $this->configuratieCic();

        self::assertSame([
            'activa' => true,
            'dataInceput' => '2026-11-01',
            'dataSfarsit' => '2027-06-30',
        ], $serviciu->descrieSituatieCic($configuratie, '2026-11-01'));
        self::assertFalse(
            $serviciu->descrieSituatieCic($configuratie, '2027-07-01')['activa']
        );
    }

    public function testRemoteCurentSeAplicaIndiferentDeData(): void
    {
        $serviciu = $this->creeazaServiciu(ConfiguratiiCondicaAngajati::MOD_REMOTE_CURENT);
        $configuratie = $this->configuratieCic() + [
            'remoteActiv' => true,
            'dataInceputRemote' => null,
            'dataSfarsitRemote' => null,
        ];

        self::assertTrue($serviciu->esteDataRemote($configuratie, '2020-01-01'));
        self::assertTrue($serviciu->esteDataRemote($configuratie, '2030-01-01'));
    }

    public function testRemotePeIntervalSeAplicaNumaiIntreDate(): void
    {
        $serviciu = $this->creeazaServiciu(ConfiguratiiCondicaAngajati::MOD_REMOTE_INTERVAL);
        $configuratie = $this->configuratieCic() + [
            'remoteActiv' => true,
            'dataInceputRemote' => '2026-11-01',
            'dataSfarsitRemote' => '2026-11-30',
        ];

        self::assertFalse($serviciu->esteDataRemote($configuratie, '2026-10-31'));
        self::assertTrue($serviciu->esteDataRemote($configuratie, '2026-11-01'));
        self::assertTrue($serviciu->esteDataRemote($configuratie, '2026-11-30'));
        self::assertFalse($serviciu->esteDataRemote($configuratie, '2026-12-01'));
    }

    public function testCitesteNumarulLunilorEditabileDinConfiguratiaNativa(): void
    {
        self::assertSame(3, $this->creeazaServiciu(numarLuniEditabile: 3)
            ->obtineNumarLuniAnterioareEditabile());
        self::assertSame(
            ConfiguratiiCondicaAngajati::LUNI_ANTERIOARE_EDITABILE_IMPLICIT,
            $this->creeazaServiciu(numarLuniEditabile: 121)
                ->obtineNumarLuniAnterioareEditabile()
        );
    }

    public function testExportulIncompletEsteInterzisImplicitSiPoateFiActivat(): void
    {
        self::assertFalse($this->creeazaServiciu()->permiteExportCondicaIncompleta());
        self::assertTrue(
            $this->creeazaServiciu(permiteExportIncomplet: true)
                ->permiteExportCondicaIncompleta()
        );
    }

    public function testSalveazaSetarileGeneralePrintrOSinguraScriere(): void
    {
        $valori = [];
        $scriitor = $this->createMock(ConfigWriter::class);
        $scriitor
            ->expects(self::exactly(3))
            ->method('set')
            ->willReturnCallback(function (string $cheie, mixed $valoare) use (&$valori, $scriitor): ConfigWriter {
                $valori[$cheie] = $valoare;

                return $scriitor;
            });
        $scriitor->expects(self::once())->method('save');

        $this->creeazaServiciu(scriitor: $scriitor)->salveazaSetariGenerale(
            ConfiguratiiCondicaAngajati::MOD_REMOTE_INTERVAL,
            4,
            true
        );

        self::assertSame([
            ConfiguratiiCondicaAngajati::CHEIE_MOD_REMOTE =>
                ConfiguratiiCondicaAngajati::MOD_REMOTE_INTERVAL,
            ConfiguratiiCondicaAngajati::CHEIE_LUNI_ANTERIOARE_EDITABILE => 4,
            ConfiguratiiCondicaAngajati::CHEIE_PERMITE_EXPORT_CONDICA_INCOMPLETA => true,
        ], $valori);
    }

    private function creeazaServiciu(
        string $modRemote = 'curent',
        ?ConfigWriter $scriitor = null,
        int $numarLuniEditabile = 1,
        bool $permiteExportIncomplet = false,
    ): ConfiguratiiCondicaAngajati
    {
        $configuratie = $this->createStub(Config::class);
        $configuratie->method('get')->willReturnCallback(
            static fn (string $cheie, mixed $implicit = null): mixed => match ($cheie) {
                ConfiguratiiCondicaAngajati::CHEIE_MOD_REMOTE => $modRemote,
                ConfiguratiiCondicaAngajati::CHEIE_LUNI_ANTERIOARE_EDITABILE =>
                    $numarLuniEditabile,
                ConfiguratiiCondicaAngajati::CHEIE_PERMITE_EXPORT_CONDICA_INCOMPLETA =>
                    $permiteExportIncomplet,
                default => $implicit,
            }
        );

        return new ConfiguratiiCondicaAngajati(
            $this->createStub(EntityManager::class),
            $configuratie,
            $scriitor ?? $this->createStub(ConfigWriter::class)
        );
    }

    /** @return array<string, bool|string|null> */
    private function configuratieCic(): array
    {
        return [
            'cicActiv' => true,
            'dataInceputCic' => '2026-11-01',
            'dataSfarsitCic' => '2027-06-30',
        ];
    }
}
