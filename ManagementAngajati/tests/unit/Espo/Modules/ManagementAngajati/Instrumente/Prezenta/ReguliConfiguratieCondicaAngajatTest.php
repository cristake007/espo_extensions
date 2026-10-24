<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\HasBody;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\ConfiguratiiCondicaAngajati;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\ReguliConfiguratieCondicaAngajat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReguliConfiguratieCondicaAngajatTest extends TestCase
{
    private ReguliConfiguratieCondicaAngajat $reguli;

    protected function setUp(): void
    {
        $this->reguli = new ReguliConfiguratieCondicaAngajat();
    }

    /** @return array<string, array{?string, ?string, ?string, string}> */
    public static function furnizorPerioadeCicInvalide(): array
    {
        return [
            'lipseste inceputul' => [null, '2027-06-30', '2020-01-01', 'perioadaCicObligatorie'],
            'lipseste sfarsitul' => ['2026-11-01', null, '2020-01-01', 'perioadaCicObligatorie'],
            'format invalid' => ['01.11.2026', '2027-06-30', '2020-01-01', 'perioadaCicInvalida'],
            'ordine inversa' => ['2027-06-30', '2026-11-01', '2020-01-01', 'perioadaCicInvalida'],
            'data angajare lipsa' => ['2026-11-01', '2027-06-30', null, 'dataAngajareLipsaPentruCic'],
            'inceput inainte de angajare' => ['2026-10-21', '2027-06-30', '2026-10-22', 'perioadaCicInainteDeAngajare'],
        ];
    }

    #[DataProvider('furnizorPerioadeCicInvalide')]
    public function testRespingePerioadaCicInvalida(
        ?string $dataInceput,
        ?string $dataSfarsit,
        ?string $dataAngajare,
        string $mesaj,
    ): void {
        try {
            $this->reguli->valideazaPerioadaCic(
                true,
                $dataInceput,
                $dataSfarsit,
                $dataAngajare,
            );
            self::fail('Perioada CIC invalidă trebuia respinsă.');
        } catch (BadRequest $exceptie) {
            $this->verificaMesaj($exceptie, $mesaj);
        }
    }

    public function testAcceptaPerioadaCicInclusivaValida(): void
    {
        $this->reguli->valideazaPerioadaCic(
            true,
            '2026-11-01',
            '2027-06-30',
            '2020-01-01',
        );
        self::assertTrue(true);
    }

    public function testCicInactivNuCerePerioada(): void
    {
        $this->reguli->valideazaPerioadaCic(false, null, null, null);
        self::assertTrue(true);
    }

    public function testPermitePerioadaCicIstoricaDupaDataAngajarii(): void
    {
        $this->reguli->valideazaPerioadaCic(
            true,
            '2026-10-01',
            '2027-06-30',
            '2020-01-01',
        );

        self::assertTrue(true);
    }

    public function testRemoteCurentNuCerePerioada(): void
    {
        $this->reguli->valideazaPerioadaRemote(
            true,
            ConfiguratiiCondicaAngajati::MOD_REMOTE_CURENT,
            null,
            null
        );
        self::assertTrue(true);
    }

    public function testRemotePeIntervalCereAmbeleDate(): void
    {
        try {
            $this->reguli->valideazaPerioadaRemote(
                true,
                ConfiguratiiCondicaAngajati::MOD_REMOTE_INTERVAL,
                '2026-11-01',
                null
            );
            self::fail('Intervalul Remote incomplet trebuia respins.');
        } catch (BadRequest $exceptie) {
            $this->verificaMesaj($exceptie, 'perioadaRemoteObligatorie');
        }
    }

    public function testPrezenteleExistenteOprescSalvareaCicCuMesajSimplu(): void
    {
        try {
            $this->reguli->valideazaFaraPrezenteConflictuale([
                '2026-11-03',
                '2026-11-04',
            ]);
            self::fail('Prezențele conflictuale trebuiau să oprească salvarea.');
        } catch (Conflict $exceptie) {
            $this->verificaMesaj($exceptie, 'conflictePerioadaCic');
            $corp = json_decode(
                (string) $exceptie->getBody(),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            self::assertSame([], $corp['messageTranslation']['data']);
        }
    }

    private function verificaMesaj(HasBody $exceptie, string $eticheta): void
    {
        $corp = $exceptie->getBody();
        self::assertNotNull($corp);
        $date = json_decode($corp, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($eticheta, $date['messageTranslation']['label']);
        self::assertSame(
            'ConfiguratieCondicaAngajat',
            $date['messageTranslation']['scope']
        );
    }
}
