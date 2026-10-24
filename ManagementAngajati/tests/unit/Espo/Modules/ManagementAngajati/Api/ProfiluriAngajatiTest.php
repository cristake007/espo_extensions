<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\ManagementAngajati\Api\ObtineProfiluriAngajati;
use Espo\Modules\ManagementAngajati\Api\SalveazaProfiluriAngajati;
use Espo\Modules\ManagementAngajati\Instrumente\Angajati\ProfiluriAngajati;
use PHPUnit\Framework\TestCase;

class ProfiluriAngajatiTest extends TestCase
{
    public function testAngajatulFaraPermisiuneNuPoateCitiProfilurile(): void
    {
        $acl = $this->createStub(Acl::class);
        $acl->method('checkScope')->willReturn(false);
        $profiluri = $this->createMock(ProfiluriAngajati::class);
        $profiluri->expects(self::never())->method('obtine');

        $this->expectException(Forbidden::class);
        (new ObtineProfiluriAngajati($acl, $profiluri))
            ->process($this->createStub(Request::class));
    }

    public function testManagerulPrimesteProfilurile(): void
    {
        $acl = $this->createStub(Acl::class);
        $acl->method('checkScope')->willReturn(true);
        $profiluri = $this->createMock(ProfiluriAngajati::class);
        $profiluri->expects(self::once())->method('obtine')->willReturn([
            'angajati' => [],
            'calendareLucru' => [],
        ]);

        $raspuns = (new ObtineProfiluriAngajati($acl, $profiluri))
            ->process($this->createStub(Request::class));

        self::assertSame(
            ['angajati' => [], 'calendareLucru' => []],
            json_decode((string) $raspuns->getBody(), true, flags: JSON_THROW_ON_ERROR)
        );
    }

    public function testManagerulSalveazaProfilurilePrintrOSinguraOperatie(): void
    {
        $acl = $this->createStub(Acl::class);
        $acl->method('checkScope')->willReturn(true);
        $dateAngajat = (object) [
            'angajatId' => 'angajat-1',
            'dataAngajare' => '2026-10-15',
            'calendarLucruId' => 'calendar-1',
        ];
        $profiluri = $this->createMock(ProfiluriAngajati::class);
        $profiluri
            ->expects(self::once())
            ->method('salveaza')
            ->with([$dateAngajat])
            ->willReturn(['angajati' => [], 'calendareLucru' => []]);
        $cerere = $this->createStub(Request::class);
        $cerere->method('getParsedBody')->willReturn((object) [
            'angajati' => [$dateAngajat],
        ]);

        (new SalveazaProfiluriAngajati($acl, $profiluri))->process($cerere);
    }

    public function testRespingeFormatulInvalidLaSalvare(): void
    {
        $acl = $this->createStub(Acl::class);
        $acl->method('checkScope')->willReturn(true);
        $cerere = $this->createStub(Request::class);
        $cerere->method('getParsedBody')->willReturn((object) [
            'angajati' => ['invalid'],
        ]);

        $this->expectException(BadRequest::class);
        (new SalveazaProfiluriAngajati(
            $acl,
            $this->createStub(ProfiluriAngajati::class)
        ))->process($cerere);
    }
}
