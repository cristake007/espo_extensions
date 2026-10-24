<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Api;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Api\Request;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\ManagementAngajati\Api\TrimiteReminderePrezenta;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\TrimitatorReminderePrezenta;
use PHPUnit\Framework\TestCase;

class TrimiteReminderePrezentaTest extends TestCase
{
    public function testRespingeUtilizatorulFaraAccesLaCondica(): void
    {
        $acl = $this->createMock(Acl::class);
        $acl->expects(self::once())->method('checkScope')->with('CondicaPrezenta')->willReturn(false);
        $trimitator = $this->createMock(TrimitatorReminderePrezenta::class);
        $trimitator->expects(self::never())->method('trimite');

        $this->expectException(Forbidden::class);
        (new TrimiteReminderePrezenta($acl, $trimitator))->process(
            $this->createStub(Request::class)
        );
    }

    public function testTrimiteLunaCatreServiciuCuAclNativ(): void
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
        $trimitator = $this->createMock(TrimitatorReminderePrezenta::class);
        $trimitator
            ->expects(self::once())
            ->method('trimite')
            ->with('2026-10')
            ->willReturn([
                'lunaSolicitata' => '2026-10',
                'luniVerificate' => ['2026-10', '2026-09'],
                'notificariCreate' => 1,
                'notificariActualizate' => 0,
                'angajatiFaraLipsuri' => 1,
                'angajatiFaraDreptCreare' => 0,
            ]);
        $cerere = $this->createStub(Request::class);
        $cerere->method('getParsedBody')->willReturn((object) ['luna' => '2026-10']);

        $raspuns = (new TrimiteReminderePrezenta($acl, $trimitator))->process($cerere);
        $date = json_decode((string) $raspuns->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(1, $date['notificariCreate']);
    }
}
