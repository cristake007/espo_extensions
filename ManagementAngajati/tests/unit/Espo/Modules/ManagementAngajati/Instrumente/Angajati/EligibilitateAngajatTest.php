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
}
