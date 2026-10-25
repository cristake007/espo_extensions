<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Espo\Core\Acl;
use Espo\Core\AclManager;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Language\LanguageFactory;
use Espo\Entities\Notification;
use Espo\Entities\Preferences;
use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\CicluLunarCondica;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\CondicaPrezentaLunara;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\TrimitatorReminderePrezenta;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TrimitatorReminderePrezentaTest extends TestCase
{
    public function testIgnoraLunileEditabileAnterioarePrimeiAngajari(): void
    {
        $condica = $this->createMock(CondicaPrezentaLunara::class);
        $condica
            ->expects(self::once())
            ->method('obtineLunaMinimaDisponibila')
            ->willReturn('2026-10');
        $condica
            ->expects(self::once())
            ->method('obtineDinDateCurente')
            ->with('2026-10')
            ->willReturn(['angajati' => [], 'zile' => []]);
        $ciclu = $this->createMock(CicluLunarCondica::class);
        $ciclu->expects(self::once())->method('valideazaLuna')->with('2026-10');
        $ciclu
            ->expects(self::once())
            ->method('esteInFereastraPersonalaEditabila')
            ->with('2026-10')
            ->willReturn(true);
        $ciclu
            ->expects(self::once())
            ->method('obtineLuniDeschiseDinFereastraPersonala')
            ->willReturn(['2026-10', '2026-09', '2026-08']);
        $manager = $this->createMock(EntityManager::class);
        $manager->expects(self::never())->method('getEntityById');

        $rezultat = (new TrimitatorReminderePrezenta(
            $manager,
            $condica,
            $ciclu,
            $this->createStub(AclManager::class),
            $this->createStub(LanguageFactory::class),
            $this->createStub(Config::class),
        ))->trimite('2026-10');

        self::assertSame(['2026-10'], $rezultat['luniVerificate']);
        self::assertSame(0, $rezultat['notificariCreate']);
        self::assertSame(0, $rezultat['notificariActualizate']);
    }

    #[DataProvider('cazuriNotificare')]
    public function testCreeazaSauActualizeazaOAgregareSiIgnoraAngajatulFaraLipsuri(
        string $stareNotificare,
    ): void
    {
        $condica = $this->createMock(CondicaPrezentaLunara::class);
        $condica
            ->expects(self::exactly(2))
            ->method('obtineDinDateCurente')
            ->willReturnMap([
                ['2026-10', [
                    'angajati' => [
                        ['idAngajat' => 'angajat-1'],
                        ['idAngajat' => 'angajat-2'],
                    ],
                    'zile' => [
                        ['celule' => [
                            ['idAngajat' => 'angajat-1', 'esteLipsa' => true],
                            ['idAngajat' => 'angajat-2', 'esteLipsa' => false],
                        ]],
                        ['celule' => [
                            ['idAngajat' => 'angajat-1', 'esteLipsa' => true],
                            ['idAngajat' => 'angajat-2', 'esteLipsa' => false],
                        ]],
                    ],
                ]],
                ['2026-09', [
                    'angajati' => [
                        ['idAngajat' => 'angajat-1'],
                        ['idAngajat' => 'angajat-2'],
                    ],
                    'zile' => [[
                        'celule' => [
                            ['idAngajat' => 'angajat-1', 'esteLipsa' => true],
                            ['idAngajat' => 'angajat-2', 'esteLipsa' => false],
                        ],
                    ]],
                ]],
            ]);
        $ciclu = $this->createMock(CicluLunarCondica::class);
        $ciclu->expects(self::once())->method('valideazaLuna')->with('2026-10');
        $ciclu
            ->expects(self::once())
            ->method('esteInFereastraPersonalaEditabila')
            ->with('2026-10')
            ->willReturn(true);
        $ciclu
            ->expects(self::once())
            ->method('obtineLuniDeschiseDinFereastraPersonala')
            ->willReturn(['2026-10', '2026-09']);
        $angajat = $this->createStub(User::class);
        $angajat->method('getId')->willReturn('angajat-1');
        $angajat->method('isActive')->willReturn(true);
        $angajat->method('isRegular')->willReturn(true);
        $aclAngajat = $this->createStub(Acl::class);
        $aclAngajat->method('checkScope')->willReturn(true);
        $managerAcl = $this->createStub(AclManager::class);
        $managerAcl->method('createUserAcl')->willReturn($aclAngajat);
        $notificare = $this->createMock(Notification::class);
        $notificare->method('getData')->willReturn((object) [
            'managementAngajatiReminderPrezenta' => true,
            'luna' => '2026-10',
            'numarZileLipsa' => 2,
        ]);
        $notificare->method('setType')->willReturnSelf();
        $notificare
            ->expects(self::once())
            ->method('setMessage')
            ->with(self::callback(static fn (string $mesaj): bool =>
                str_contains($mesaj, '#Prezenta/list?luna=2026-10') &&
                str_contains($mesaj, '#Prezenta/list?luna=2026-09')
            ))
            ->willReturnSelf();
        $notificare->method('setUserId')->willReturnSelf();
        $notificare
            ->expects(self::once())
            ->method('setActionId')
            ->with(self::callback(static fn (string $id): bool => strlen($id) === 32))
            ->willReturnSelf();
        $notificare->method('setRead')->willReturnSelf();
        $notificare
            ->expects(self::once())
            ->method('setData')
            ->with(self::callback(static fn (array $date): bool =>
                $date['luniLipsa'] === ['2026-10' => 2, '2026-09' => 1] &&
                $date['numarZileLipsa'] === 3
            ))
            ->willReturnSelf();
        $selectieNotificare = $this->createStub(RDBSelectBuilder::class);
        $selectieNotificare
            ->method('findOne')
            ->willReturn($stareNotificare === 'existenta' ? $notificare : null);
        $selectieNotificare->method('find')->willReturn(
            new EntityCollection($stareNotificare === 'veche' ? [$notificare] : [])
        );
        $depozitNotificari = $this->createMock(RDBRepository::class);
        $depozitNotificari->method('where')->willReturn($selectieNotificare);
        $depozitNotificari
            ->expects(self::exactly($stareNotificare === 'noua' ? 1 : 0))
            ->method('getNew')
            ->willReturn($notificare);
        $preferinte = $this->createStub(Preferences::class);
        $manager = $this->createMock(EntityManager::class);
        $manager->method('getEntityById')->willReturnMap([
            [User::ENTITY_TYPE, 'angajat-1', $angajat],
            [Preferences::ENTITY_TYPE, 'angajat-1', $preferinte],
        ]);
        $manager->method('getRDBRepositoryByClass')->willReturn($depozitNotificari);
        $manager->expects(self::once())->method('saveEntity')->with($notificare);
        $limba = $this->createStub(Language::class);
        $limba->method('translateLabel')->willReturnMap([
            ['10', 'luni', 'Prezenta', 'October'],
            ['09', 'luni', 'Prezenta', 'September'],
            [
                'reminderPrezentaFereastra',
                'messages',
                'CondicaPrezenta',
                'You have missing attendance in the following months:',
            ],
            [
                'reminderPrezentaLuna',
                'messages',
                'CondicaPrezenta',
                '- {numar} days in {luna} — [Open month]({ruta})',
            ],
        ]);
        $fabricaLimbi = $this->createStub(LanguageFactory::class);
        $fabricaLimbi->method('create')->willReturn($limba);

        $rezultat = (new TrimitatorReminderePrezenta(
            $manager,
            $condica,
            $ciclu,
            $managerAcl,
            $fabricaLimbi,
            $this->createStub(Config::class),
        ))->trimite('2026-10');

        self::assertSame($stareNotificare === 'noua' ? 1 : 0, $rezultat['notificariCreate']);
        self::assertSame($stareNotificare === 'noua' ? 0 : 1, $rezultat['notificariActualizate']);
        self::assertSame(1, $rezultat['angajatiFaraLipsuri']);
        self::assertSame(['2026-10', '2026-09'], $rezultat['luniVerificate']);
    }

    /** @return iterable<string, array{string}> */
    public static function cazuriNotificare(): iterable
    {
        yield 'creare' => ['noua'];
        yield 'actualizare fara duplicat' => ['existenta'];
        yield 'migrare notificare lunara anterioara' => ['veche'];
    }
}
