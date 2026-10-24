<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Api;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Api\Request;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Api\ObtineCalendarPrezentaPersonala;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\CalendarPrezentaPersonala;
use PHPUnit\Framework\TestCase;

class ObtineCalendarPrezentaPersonalaTest extends TestCase
{
    public function testRespingeCerereaCandRolulNuPermiteCitireaPrezentaului(): void
    {
        $acl = $this->createMock(Acl::class);
        $acl
            ->expects(self::once())
            ->method('checkScope')
            ->with('Prezenta', Table::ACTION_READ)
            ->willReturn(false);
        $calendar = $this->createStub(CalendarPrezentaPersonala::class);
        $cerere = $this->createStub(Request::class);

        $actiune = new ObtineCalendarPrezentaPersonala(
            $acl,
            $this->createStub(User::class),
            $calendar
        );

        $this->expectException(Forbidden::class);
        $actiune->process($cerere);
    }

    public function testReturneazaCalendarulUtilizatoruluiCandCitireaEstePermisa(): void
    {
        $acl = $this->createStub(Acl::class);
        $acl->method('checkScope')->willReturn(true);
        $utilizator = $this->createStub(User::class);
        $utilizator->method('isRegular')->willReturn(true);
        $calendar = $this->createMock(CalendarPrezentaPersonala::class);
        $calendar
            ->expects(self::once())
            ->method('obtine')
            ->with($utilizator, '2026-10')
            ->willReturn([
                'luna' => '2026-10',
                'dataCurenta' => '2026-10-22',
                'zile' => [],
            ]);
        $cerere = $this->createStub(Request::class);
        $cerere->method('getQueryParam')->willReturn('2026-10');

        $raspuns = (new ObtineCalendarPrezentaPersonala($acl, $utilizator, $calendar))
            ->process($cerere);

        self::assertSame('application/json', $raspuns->getHeader('Content-Type'));
        self::assertSame(
            ['luna' => '2026-10', 'dataCurenta' => '2026-10-22', 'zile' => []],
            json_decode((string) $raspuns->getBody(), true, flags: JSON_THROW_ON_ERROR)
        );
    }

    public function testAdministratorulNuEsteConsideratAngajatPentruPrezentaulPersonal(): void
    {
        $acl = $this->createStub(Acl::class);
        $acl->method('checkScope')->willReturn(true);
        $administrator = $this->createStub(User::class);
        $administrator->method('isRegular')->willReturn(false);
        $calendar = $this->createStub(CalendarPrezentaPersonala::class);
        $cerere = $this->createStub(Request::class);
        $actiune = new ObtineCalendarPrezentaPersonala($acl, $administrator, $calendar);

        try {
            $actiune->process($cerere);
            self::fail('Administratorul nu trebuie să primească un calendar de prezență personală.');
        } catch (Forbidden $exceptie) {
            $corp = $exceptie->getBody();

            self::assertNotNull($corp);
            $date = json_decode($corp, true, flags: JSON_THROW_ON_ERROR);
            self::assertSame(
                'prezentaDoarPentruAngajati',
                $date['messageTranslation']['label']
            );
            self::assertSame('Prezenta', $date['messageTranslation']['scope']);
        }
    }
}
