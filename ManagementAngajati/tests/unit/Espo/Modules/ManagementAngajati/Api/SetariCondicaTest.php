<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\ManagementAngajati\Api\ObtineSetariCondica;
use Espo\Modules\ManagementAngajati\Api\SalveazaSetariCondica;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\ConfiguratiiCondicaAngajati;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\SalvatorSetariCondica;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\SetariCondica;
use PHPUnit\Framework\TestCase;

class SetariCondicaTest extends TestCase
{
    public function testAngajatulFaraPermisiuneaComunaNuPoateCitiSetarile(): void
    {
        $acl = $this->createMock(Acl::class);
        $acl
            ->expects(self::once())
            ->method('checkScope')
            ->with('GestionareAngajati')
            ->willReturn(false);
        $actiune = new ObtineSetariCondica($acl, $this->createStub(SetariCondica::class));

        $this->expectException(Forbidden::class);
        $actiune->process($this->createStub(Request::class));
    }

    public function testManagerulPrimesteListaAgregataDeSetari(): void
    {
        $acl = $this->createStub(Acl::class);
        $acl->method('checkScope')->willReturn(true);
        $setari = $this->createMock(SetariCondica::class);
        $setari
            ->expects(self::once())
            ->method('obtine')
            ->willReturn([
                'modAplicareRemote' => 'curent',
                'numarLuniAnterioareEditabile' => 1,
                'permiteExportCondicaIncompleta' => false,
                'angajati' => [],
            ]);
        $actiune = new ObtineSetariCondica($acl, $setari);

        $raspuns = $actiune->process($this->createStub(Request::class));

        self::assertSame(
            [
                'modAplicareRemote' => 'curent',
                'numarLuniAnterioareEditabile' => 1,
                'permiteExportCondicaIncompleta' => false,
                'angajati' => [],
            ],
            json_decode((string) $raspuns->getBody(), true, flags: JSON_THROW_ON_ERROR)
        );
    }

    public function testManagerulPoateSalvaToateSetarilePrintrOOperatie(): void
    {
        $acl = $this->createStub(Acl::class);
        $acl->method('checkScope')->willReturn(true);
        $salvator = $this->createMock(SalvatorSetariCondica::class);
        $dateAngajat = (object) [
            'angajatId' => 'angajat-1',
            'remoteActiv' => false,
            'cicActiv' => false,
        ];
        $salvator
            ->expects(self::once())
            ->method('salveaza')
            ->with(
                ConfiguratiiCondicaAngajati::MOD_REMOTE_INTERVAL,
                2,
                true,
                [$dateAngajat]
            )
            ->willReturn([
                'modAplicareRemote' => 'interval',
                'numarLuniAnterioareEditabile' => 2,
                'permiteExportCondicaIncompleta' => true,
                'angajati' => [],
            ]);
        $cerere = $this->createStub(Request::class);
        $cerere
            ->method('getParsedBody')
            ->willReturn((object) [
                'modAplicareRemote' => 'interval',
                'numarLuniAnterioareEditabile' => 2,
                'permiteExportCondicaIncompleta' => true,
                'angajati' => [$dateAngajat],
            ]);
        $actiune = new SalveazaSetariCondica($acl, $salvator);

        $raspuns = $actiune->process($cerere);

        self::assertSame(
            [
                'modAplicareRemote' => 'interval',
                'numarLuniAnterioareEditabile' => 2,
                'permiteExportCondicaIncompleta' => true,
                'angajati' => [],
            ],
            json_decode((string) $raspuns->getBody(), true, flags: JSON_THROW_ON_ERROR)
        );
    }

    public function testAngajatulFaraPermisiuneaComunaNuPoateSalvaSetarile(): void
    {
        $acl = $this->createStub(Acl::class);
        $acl->method('checkScope')->willReturn(false);
        $salvator = $this->createMock(SalvatorSetariCondica::class);
        $salvator->expects(self::never())->method('salveaza');
        $actiune = new SalveazaSetariCondica($acl, $salvator);

        $this->expectException(Forbidden::class);
        $actiune->process($this->createStub(Request::class));
    }

    public function testRespingeListaDeAngajatiCuFormatInvalid(): void
    {
        $acl = $this->createStub(Acl::class);
        $acl->method('checkScope')->willReturn(true);
        $salvator = $this->createMock(SalvatorSetariCondica::class);
        $salvator->expects(self::never())->method('salveaza');
        $cerere = $this->createStub(Request::class);
        $cerere->method('getParsedBody')->willReturn((object) [
            'modAplicareRemote' => 'curent',
            'angajati' => ['valoare-invalida'],
        ]);

        $this->expectException(BadRequest::class);
        (new SalveazaSetariCondica($acl, $salvator))->process($cerere);
    }

    public function testRespingePermisiuneaDeExportCareNuEsteBooleana(): void
    {
        $acl = $this->createStub(Acl::class);
        $acl->method('checkScope')->willReturn(true);
        $salvator = $this->createMock(SalvatorSetariCondica::class);
        $salvator->expects(self::never())->method('salveaza');
        $cerere = $this->createStub(Request::class);
        $cerere->method('getParsedBody')->willReturn((object) [
            'modAplicareRemote' => 'curent',
            'numarLuniAnterioareEditabile' => 1,
            'permiteExportCondicaIncompleta' => 'da',
            'angajati' => [],
        ]);

        $this->expectException(BadRequest::class);
        (new SalveazaSetariCondica($acl, $salvator))->process($cerere);
    }
}
