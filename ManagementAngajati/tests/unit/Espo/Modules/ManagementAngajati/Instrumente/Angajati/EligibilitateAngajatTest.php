<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Angajati;

use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Angajati\EligibilitateAngajat;
use PHPUnit\Framework\TestCase;

class EligibilitateAngajatTest extends TestCase
{
    public function testIdentificaZiuaDinainteaAngajarii(): void
    {
        $angajat = $this->createStub(User::class);
        $angajat->method('get')->willReturn('2026-10-15');
        $eligibilitate = new EligibilitateAngajat();

        self::assertTrue($eligibilitate->esteInainteDeAngajare($angajat, '2026-10-14'));
        self::assertFalse($eligibilitate->esteInainteDeAngajare($angajat, '2026-10-15'));
    }

    public function testNuInventeazaDataAngajariiCandLipseste(): void
    {
        $angajat = $this->createStub(User::class);
        $angajat->method('get')->willReturn(null);
        $eligibilitate = new EligibilitateAngajat();

        self::assertNull($eligibilitate->obtineDataAngajare($angajat));
        self::assertFalse($eligibilitate->esteInainteDeAngajare($angajat, '2026-10-01'));
    }

    public function testIncludeAngajatulDacaEsteAngajatInTimpulLunii(): void
    {
        $angajat = $this->createStub(User::class);
        $angajat->method('get')->willReturn('2026-03-15');

        self::assertTrue(
            (new EligibilitateAngajat())->participaLaCondicaLunii($angajat, '2026-03')
        );
    }

    public function testExcludeAngajatulDinLunileAnterioareAngajarii(): void
    {
        $angajat = $this->createStub(User::class);
        $angajat->method('get')->willReturn('2026-04-01');

        self::assertFalse(
            (new EligibilitateAngajat())->participaLaCondicaLunii($angajat, '2026-03')
        );
    }

    public function testPastreazaProfilulNeconfiguratVizibilPentruAImpiedicaInchidereaLunii(): void
    {
        $angajat = $this->createStub(User::class);
        $angajat->method('get')->willReturn(null);

        self::assertTrue(
            (new EligibilitateAngajat())->participaLaCondicaLunii($angajat, '2026-03')
        );
    }

    public function testObtineCeaMaiVecheLunaDeAngajare(): void
    {
        $angajatUnu = $this->createStub(User::class);
        $angajatUnu->method('get')->willReturn('2020-06-15');
        $angajatDoi = $this->createStub(User::class);
        $angajatDoi->method('get')->willReturn('2019-11-04');

        self::assertSame(
            '2019-11',
            (new EligibilitateAngajat())->obtineLunaMinimaDisponibila(
                [$angajatUnu, $angajatDoi],
                '2026-10'
            )
        );
    }

    public function testFolosesteLunaCurentaCandNuExistaAngajati(): void
    {
        self::assertSame(
            '2026-10',
            (new EligibilitateAngajat())->obtineLunaMinimaDisponibila([], '2026-10')
        );
    }

    public function testNuInventeazaLimitaCandUnAngajatNuAreDataConfigurata(): void
    {
        $angajatConfigurat = $this->createStub(User::class);
        $angajatConfigurat->method('get')->willReturn('2020-06-15');
        $angajatNeconfigurat = $this->createStub(User::class);
        $angajatNeconfigurat->method('get')->willReturn(null);

        self::assertNull(
            (new EligibilitateAngajat())->obtineLunaMinimaDisponibila(
                [$angajatConfigurat, $angajatNeconfigurat],
                '2026-10'
            )
        );
    }
}
