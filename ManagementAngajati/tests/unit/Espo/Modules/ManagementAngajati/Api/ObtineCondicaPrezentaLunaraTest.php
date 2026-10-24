<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Api;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Api\Request;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\ManagementAngajati\Api\ObtineCondicaPrezentaLunara;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\CondicaPrezentaLunara;
use PHPUnit\Framework\TestCase;

class ObtineCondicaPrezentaLunaraTest extends TestCase
{
    public function testRespingeUtilizatorulFaraPermisiuneaPaginiiManageriale(): void
    {
        $acl = $this->createMock(Acl::class);
        $acl
            ->expects(self::once())
            ->method('checkScope')
            ->with('CondicaPrezenta')
            ->willReturn(false);
        $condica = $this->createMock(CondicaPrezentaLunara::class);
        $condica->expects(self::never())->method('obtine');

        $this->expectException(Forbidden::class);
        (new ObtineCondicaPrezentaLunara($acl, $condica))
            ->process($this->createStub(Request::class));
    }

    public function testRespingeManagerulFaraDreptDeCitirePePrezenta(): void
    {
        $acl = $this->createMock(Acl::class);
        $acl
            ->expects(self::exactly(2))
            ->method('checkScope')
            ->willReturnCallback(
                static fn (string $scope, ?string $actiune = null): bool =>
                    $scope === 'CondicaPrezenta' && $actiune === null
            );
        $condica = $this->createMock(CondicaPrezentaLunara::class);
        $condica->expects(self::never())->method('obtine');

        $this->expectException(Forbidden::class);
        (new ObtineCondicaPrezentaLunara($acl, $condica))
            ->process($this->createStub(Request::class));
    }

    public function testReturneazaProiectiaLunaraCandAmbelePermisiuniSuntAcordate(): void
    {
        $acl = $this->createMock(Acl::class);
        $acl
            ->expects(self::exactly(2))
            ->method('checkScope')
            ->willReturnCallback(
                static fn (string $scope, ?string $actiune = null): bool =>
                    $scope === 'CondicaPrezenta' ||
                    ($scope === 'Prezenta' && $actiune === Table::ACTION_READ)
            );
        $condica = $this->createMock(CondicaPrezentaLunara::class);
        $condica
            ->expects(self::once())
            ->method('obtine')
            ->with('2026-10')
            ->willReturn([
                'luna' => '2026-10',
                'angajati' => [],
                'zile' => [],
                'statistici' => [],
            ]);
        $cerere = $this->createMock(Request::class);
        $cerere
            ->expects(self::once())
            ->method('getQueryParam')
            ->with('luna')
            ->willReturn('2026-10');

        $raspuns = (new ObtineCondicaPrezentaLunara($acl, $condica))->process($cerere);

        self::assertSame(
            [
                'luna' => '2026-10',
                'angajati' => [],
                'zile' => [],
                'statistici' => [],
            ],
            json_decode((string) $raspuns->getBody(), true, flags: JSON_THROW_ON_ERROR)
        );
    }
}
