<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Api;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Api\Request;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\ManagementAngajati\Api\CorecteazaLunaCondica;
use Espo\Modules\ManagementAngajati\Api\InchideLunaCondica;
use Espo\Modules\ManagementAngajati\Api\MarcheazaPrezentaCondica;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\CorectorLunaCondica;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\InchizatorLunaCondica;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\MarcatorPrezentaCondica;
use PHPUnit\Framework\TestCase;

class CicluLunarCondicaTest extends TestCase
{
    public function testInchidereaNecesitaPermisiuneaMostenitaPentruCondica(): void
    {
        $acl = $this->createMock(Acl::class);
        $acl
            ->expects(self::once())
            ->method('checkScope')
            ->with('CondicaPrezenta')
            ->willReturn(false);
        $inchizator = $this->createMock(InchizatorLunaCondica::class);
        $inchizator->expects(self::never())->method('inchide');

        $this->expectException(Forbidden::class);
        (new InchideLunaCondica($acl, $inchizator))->process(
            $this->createStub(Request::class)
        );
    }

    public function testManagerulPoateInchideLunaCuAccesLaCondicaSiEditarePrezenta(): void
    {
        $acl = $this->createMock(Acl::class);
        $acl
            ->expects(self::exactly(2))
            ->method('checkScope')
            ->willReturnCallback(
                static fn (string $scope, ?string $actiune = null): bool =>
                    $scope === 'CondicaPrezenta' ||
                    ($scope === 'Prezenta' && $actiune === Table::ACTION_EDIT)
            );
        $inchizator = $this->createMock(InchizatorLunaCondica::class);
        $inchizator
            ->expects(self::once())
            ->method('inchide')
            ->with('2026-09')
            ->willReturn(['luna' => '2026-09', 'cicluLunar' => ['esteInchisa' => true]]);
        $cerere = $this->createStub(Request::class);
        $cerere->method('getParsedBody')->willReturn((object) ['luna' => '2026-09']);

        $raspuns = (new InchideLunaCondica($acl, $inchizator))->process($cerere);
        $date = json_decode((string) $raspuns->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertTrue($date['cicluLunar']['esteInchisa']);
    }

    public function testCorectiaNecesitaPermisiuneaMostenitaPentruCondica(): void
    {
        $acl = $this->createMock(Acl::class);
        $acl
            ->expects(self::once())
            ->method('checkScope')
            ->with('CondicaPrezenta')
            ->willReturn(false);
        $corector = $this->createMock(CorectorLunaCondica::class);
        $corector->expects(self::never())->method('corecteaza');

        $this->expectException(Forbidden::class);
        (new CorecteazaLunaCondica($acl, $corector))->process(
            $this->createStub(Request::class)
        );
    }

    public function testManagerulTrimiteMotivulCorectieiPrinCondicaMostenita(): void
    {
        $acl = $this->createMock(Acl::class);
        $acl
            ->expects(self::once())
            ->method('checkScope')
            ->with('CondicaPrezenta')
            ->willReturn(true);
        $corector = $this->createMock(CorectorLunaCondica::class);
        $corector
            ->expects(self::once())
            ->method('corecteaza')
            ->with('2026-09', 'angajat-1', '2026-09-15', 'D', 'Delegație confirmată')
            ->willReturn(['luna' => '2026-09']);
        $cerere = $this->createStub(Request::class);
        $cerere->method('getParsedBody')->willReturn((object) [
            'luna' => '2026-09',
            'idAngajat' => 'angajat-1',
            'data' => '2026-09-15',
            'cod' => 'D',
            'motiv' => 'Delegație confirmată',
        ]);

        $raspuns = (new CorecteazaLunaCondica($acl, $corector))->process($cerere);

        self::assertSame(
            ['luna' => '2026-09'],
            json_decode((string) $raspuns->getBody(), true, flags: JSON_THROW_ON_ERROR)
        );
    }

    public function testMarcareaNecesitaPermisiuneaMostenitaPentruCondica(): void
    {
        $acl = $this->createMock(Acl::class);
        $acl
            ->expects(self::once())
            ->method('checkScope')
            ->with('CondicaPrezenta')
            ->willReturn(false);
        $marcator = $this->createMock(MarcatorPrezentaCondica::class);
        $marcator->expects(self::never())->method('marcheaza');

        $this->expectException(Forbidden::class);
        (new MarcheazaPrezentaCondica($acl, $marcator))->process(
            $this->createStub(Request::class)
        );
    }

    public function testMarcareaTrimiteIdulAngajatuluiPrinCondicaMostenita(): void
    {
        $acl = $this->createMock(Acl::class);
        $acl
            ->expects(self::once())
            ->method('checkScope')
            ->with('CondicaPrezenta')
            ->willReturn(true);
        $marcator = $this->createMock(MarcatorPrezentaCondica::class);
        $marcator
            ->expects(self::once())
            ->method('marcheaza')
            ->with('angajat-1', '2026-09-15', 'D')
            ->willReturn(['idPrezenta' => 'prezenta-1', 'aFostCreata' => true]);
        $cerere = $this->createStub(Request::class);
        $cerere->method('getParsedBody')->willReturn((object) [
            'idAngajat' => 'angajat-1',
            'data' => '2026-09-15',
            'cod' => 'D',
        ]);

        $raspuns = (new MarcheazaPrezentaCondica($acl, $marcator))->process($cerere);

        self::assertSame(
            ['idPrezenta' => 'prezenta-1', 'aFostCreata' => true],
            json_decode((string) $raspuns->getBody(), true, flags: JSON_THROW_ON_ERROR)
        );
    }
}
