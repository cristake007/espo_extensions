<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Field\Date;
use Espo\Core\Utils\DateTime;
use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\CicluLunarCondica;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\ConfiguratiiCondicaAngajati;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use PHPUnit\Framework\TestCase;

class CicluLunarCondicaTest extends TestCase
{
    public function testPermiteLunaCurentaSiNumarulConfiguratDeLuniAnterioare(): void
    {
        $serviciu = $this->creeazaServiciu(null, 1);

        self::assertTrue($serviciu->poateFiModificataObisnuit('2026-10'));
        self::assertTrue($serviciu->poateFiModificataObisnuit('2026-09'));
        self::assertFalse($serviciu->poateFiModificataObisnuit('2026-08'));
        self::assertSame('2026-09', $serviciu->obtineLunaMinimaEditabila());

        $this->expectException(BadRequest::class);
        $serviciu->valideazaModificareObisnuita('2026-08-31');
    }

    public function testBlocheazaCrudulObisnuitPentruLunaInchisa(): void
    {
        $stare = $this->createStub(Entity::class);
        $stare->method('get')->willReturnMap([
            ['stare', CicluLunarCondica::STARE_INCHISA],
        ]);
        $serviciu = $this->creeazaServiciu($stare, 12);

        self::assertFalse($serviciu->poateFiModificataObisnuit('2026-09'));

        $this->expectException(Conflict::class);
        $serviciu->valideazaModificareObisnuita('2026-09-15');
    }

    public function testManagerulPoateCompletaOLunaDeschisaDinAfaraFerestreiPersonale(): void
    {
        $serviciu = $this->creeazaServiciu(null, 1);

        self::assertFalse($serviciu->poateFiModificataObisnuit('2026-04'));
        self::assertTrue($serviciu->poateFiModificataManagerial('2026-04'));
        $serviciu->valideazaModificareManageriala('2026-04-15');
        self::addToAssertionCount(1);
    }

    public function testRemindereleRamanLimitateLaFereastraPersonalaEditabila(): void
    {
        $serviciu = $this->creeazaServiciu(null, 1);

        self::assertTrue($serviciu->esteInFereastraPersonalaEditabila('2026-10'));
        self::assertTrue($serviciu->esteInFereastraPersonalaEditabila('2026-09'));
        self::assertFalse($serviciu->esteInFereastraPersonalaEditabila('2026-08'));
    }

    public function testReturneazaToateLunileDeschiseDinFereastraPentruReminder(): void
    {
        $serviciu = $this->creeazaServiciu(null, 2);

        self::assertSame(
            ['2026-10', '2026-09', '2026-08'],
            $serviciu->obtineLuniDeschiseDinFereastraPersonala()
        );
    }

    public function testManagerulNuPoateMarcaDirectOLunaInchisa(): void
    {
        $stare = $this->createStub(Entity::class);
        $stare->method('get')->willReturnMap([
            ['stare', CicluLunarCondica::STARE_INCHISA],
        ]);
        $serviciu = $this->creeazaServiciu($stare, 1);

        self::assertFalse($serviciu->poateFiModificataManagerial('2026-04'));

        $this->expectException(Conflict::class);
        $serviciu->valideazaModificareManageriala('2026-04-15');
    }

    public function testAdministratorulPastreazaComportamentulNativNerestrictionat(): void
    {
        $utilizator = $this->createStub(User::class);
        $utilizator->method('isAdmin')->willReturn(true);
        $serviciu = $this->creeazaServiciu(null, 0, $utilizator);

        self::assertTrue($serviciu->poateFiModificataObisnuit('2020-01'));
        $serviciu->valideazaModificareObisnuita('2020-01-01');
        self::assertTrue(true);
    }

    private function creeazaServiciu(
        ?Entity $stare,
        int $numarLuni,
        ?User $utilizator = null,
    ): CicluLunarCondica {
        $depozit = $this->createStub(RDBRepository::class);
        $interogare = $this->createStub(RDBSelectBuilder::class);
        $interogare->method('forUpdate')->willReturnSelf();
        $interogare->method('findOne')->willReturn($stare);
        $depozit->method('where')->willReturn($interogare);
        $manager = $this->createStub(EntityManager::class);
        $manager->method('getRDBRepository')->willReturn($depozit);
        $configuratii = $this->createStub(ConfiguratiiCondicaAngajati::class);
        $configuratii
            ->method('obtineNumarLuniAnterioareEditabile')
            ->willReturn($numarLuni);
        $dataOra = $this->createStub(DateTime::class);
        $dataOra->method('getToday')->willReturn(Date::fromString('2026-10-22'));
        if ($utilizator === null) {
            $utilizator = $this->createStub(User::class);
            $utilizator->method('isAdmin')->willReturn(false);
        }

        return new CicluLunarCondica($manager, $configuratii, $dataOra, $utilizator);
    }
}
