<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Core\Utils\DateTime;
use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Angajati\EligibilitateAngajat;
use Espo\Modules\ManagementAngajati\Instrumente\Angajati\OrdonatorAngajati;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\AccesAngajatiCondica;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\CalculatorCompletitudineCondica;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\CalendarPrezentaPersonala;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\CicluLunarCondica;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\CondicaPrezentaLunara;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\NormalizatorInstantaneuCondica;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use Espo\Tools\WorkingTime\CalendarFactory;
use PHPUnit\Framework\TestCase;

class CondicaPrezentaLunaraLimitaTest extends TestCase
{
    public function testRespingeLunaAnterioaraPrimuluiAngajatAccesibil(): void
    {
        $angajat = $this->createStub(User::class);
        $angajat->method('get')->willReturnCallback(
            static fn (string $camp): mixed => $camp === 'dataAngajare' ? '2026-04-15' : null
        );
        $selectie = $this->createStub(RDBSelectBuilder::class);
        $selectie->method('find')->willReturn(new EntityCollection([$angajat]));
        $depozit = $this->createStub(RDBRepository::class);
        $depozit->method('where')->willReturn($selectie);
        $managerEntitati = $this->createStub(EntityManager::class);
        $managerEntitati->method('getRDBRepositoryByClass')->willReturn($depozit);
        $acces = $this->createStub(AccesAngajatiCondica::class);
        $acces->method('poateCiti')->willReturn(true);
        $ordonator = $this->createStub(OrdonatorAngajati::class);
        $ordonator->method('ordoneaza')->willReturn([$angajat]);
        $ciclu = $this->createMock(CicluLunarCondica::class);
        $ciclu->expects(self::once())->method('valideazaLuna')->with('2026-03');
        $ciclu->expects(self::never())->method('obtine');
        $serviciu = new CondicaPrezentaLunara(
            $managerEntitati,
            $this->createStub(SelectBuilderFactory::class),
            $this->createStub(CalendarFactory::class),
            $this->createStub(CalendarPrezentaPersonala::class),
            $this->createStub(Acl::class),
            $acces,
            new DateTime(timeZone: 'UTC'),
            $ciclu,
            $this->createStub(NormalizatorInstantaneuCondica::class),
            new EligibilitateAngajat(),
            $this->createStub(CalculatorCompletitudineCondica::class),
            $ordonator,
        );

        $this->expectException(BadRequest::class);
        $serviciu->obtine('2026-03');
    }
}
