<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Closure;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Field\Date;
use Espo\Core\Field\DateTime as DataOraCamp;
use Espo\Core\Utils\DateTime;
use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\CicluLunarCondica;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\CondicaPrezentaLunara;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\InchizatorLunaCondica;
use Espo\ORM\EntityManager;
use Espo\ORM\Entity;
use Espo\ORM\TransactionManager;
use PHPUnit\Framework\TestCase;

class InchizatorLunaCondicaTest extends TestCase
{
    public function testInchideSiIngheataLunaCompletataIntegral(): void
    {
        $tranzactie = $this->createMock(TransactionManager::class);
        $tranzactie
            ->expects(self::once())
            ->method('run')
            ->willReturnCallback(static fn (Closure $operatie): mixed => $operatie());
        $stare = $this->createMock(Entity::class);
        $stare
            ->expects(self::once())
            ->method('set')
            ->with(self::callback(static fn (array $date): bool =>
                $date['luna'] === '2026-09' &&
                $date['stare'] === CicluLunarCondica::STARE_INCHISA &&
                $date['revizie'] === 1 &&
                $date['instantaneu']['zile'][0]['celule'][0]['poateFiCompletata'] === false &&
                $date['instantaneu']['zile'][0]['celule'][0]['poateFiCorectata'] === true
            ));
        $manager = $this->createMock(EntityManager::class);
        $manager->method('getTransactionManager')->willReturn($tranzactie);
        $manager
            ->expects(self::once())
            ->method('getNewEntity')
            ->with(CicluLunarCondica::TIP_ENTITATE)
            ->willReturn($stare);
        $manager->expects(self::once())->method('saveEntity')->with($stare);
        $condica = $this->createMock(CondicaPrezentaLunara::class);
        $condica
            ->expects(self::once())
            ->method('obtineDinDateCurente')
            ->with('2026-09')
            ->willReturn([
                'luna' => '2026-09',
                'statistici' => [
                    'esteCompleta' => true,
                    'poateFiInchisa' => true,
                ],
                'zile' => [[
                    'data' => '2026-09-01',
                    'celule' => [[
                        'esteZiLucratoare' => true,
                        'areDataAngajare' => true,
                        'esteInainteDeAngajare' => false,
                        'esteViitoare' => false,
                        'esteCic' => false,
                        'poateFiCompletata' => true,
                    ]],
                ]],
            ]);
        $ciclu = $this->createMock(CicluLunarCondica::class);
        $ciclu->expects(self::once())->method('valideazaLuna')->with('2026-09');
        $ciclu->expects(self::once())->method('obtine')->with('2026-09', true)->willReturn(null);
        $ciclu
            ->expects(self::once())
            ->method('descrie')
            ->with('2026-09')
            ->willReturn(['stare' => CicluLunarCondica::STARE_INCHISA]);
        $dataOra = $this->createStub(DateTime::class);
        $dataOra->method('getToday')->willReturn(Date::fromString('2026-10-23'));
        $dataOra->method('getNow')->willReturn(new DataOraCamp('2026-10-23 10:30:00'));
        $utilizator = $this->createStub(User::class);
        $utilizator->method('getId')->willReturn('manager-1');

        $rezultat = (new InchizatorLunaCondica(
            $manager,
            $condica,
            $ciclu,
            $dataOra,
            $utilizator,
        ))->inchide('2026-09');

        self::assertSame(CicluLunarCondica::STARE_INCHISA, $rezultat['cicluLunar']['stare']);
    }

    public function testRespingeInchidereaCandLunaNuEsteCompletataIntegral(): void
    {
        $tranzactie = $this->createMock(TransactionManager::class);
        $tranzactie
            ->expects(self::once())
            ->method('run')
            ->willReturnCallback(static fn (Closure $operatie): mixed => $operatie());
        $manager = $this->createMock(EntityManager::class);
        $manager->method('getTransactionManager')->willReturn($tranzactie);
        $manager->expects(self::never())->method('getNewEntity');
        $condica = $this->createMock(CondicaPrezentaLunara::class);
        $condica
            ->expects(self::once())
            ->method('obtineDinDateCurente')
            ->with('2026-09')
            ->willReturn([
                'luna' => '2026-09',
                'statistici' => ['esteCompleta' => false, 'poateFiInchisa' => false],
            ]);
        $ciclu = $this->createMock(CicluLunarCondica::class);
        $ciclu->expects(self::once())->method('valideazaLuna')->with('2026-09');
        $ciclu->expects(self::once())->method('obtine')->with('2026-09', true)->willReturn(null);
        $dataOra = $this->createStub(DateTime::class);
        $dataOra->method('getToday')->willReturn(Date::fromString('2026-10-23'));

        $serviciu = new InchizatorLunaCondica(
            $manager,
            $condica,
            $ciclu,
            $dataOra,
            $this->createStub(User::class)
        );

        try {
            $serviciu->inchide('2026-09');
            self::fail('O lună incompletă nu trebuie închisă.');
        } catch (Conflict $exceptie) {
            $corp = json_decode(
                (string) $exceptie->getBody(),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            self::assertSame(
                'lunaCondicaIncompleta',
                $corp['messageTranslation']['label']
            );
        }
    }
}
