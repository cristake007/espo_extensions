<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Dependente;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Language\LanguageFactory;
use Espo\Entities\Notification;
use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Dependente\NotificatorDependentaZileSarbatoare;
use Espo\Modules\ManagementAngajati\Instrumente\Dependente\VerificatorZileSarbatoare;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use Espo\ORM\Repository\Repository;
use PHPUnit\Framework\TestCase;

class NotificatorDependentaZileSarbatoareTest extends TestCase
{
    public function testNuNotificaAtunciCandDependentaEsteCompatibila(): void
    {
        $verificator = $this->createStub(VerificatorZileSarbatoare::class);
        $verificator->method('esteInstalataCompatibil')->willReturn(true);
        $managerEntitati = $this->createMock(EntityManager::class);
        $managerEntitati->expects(self::never())->method('getRDBRepositoryByClass');

        $notificator = new NotificatorDependentaZileSarbatoare(
            $managerEntitati,
            $verificator,
            $this->createStub(LanguageFactory::class),
            $this->createStub(Config::class),
        );

        self::assertSame(0, $notificator->notificaDacaEsteNecesara());
    }

    public function testCreeazaOSinguraNotificarePentruAdministrator(): void
    {
        $notificare = $this->createStub(Notification::class);
        $managerEntitati = $this->creeazaManagerEntitati($notificare, null);
        $managerEntitati->expects(self::once())->method('saveEntity')->with($notificare);

        self::assertSame(1, $this->creeazaNotificator($managerEntitati)->notificaDacaEsteNecesara());
    }

    public function testNuDublaNotificareaExistenta(): void
    {
        $notificareExistenta = $this->createStub(Notification::class);
        $managerEntitati = $this->creeazaManagerEntitati(
            $this->createStub(Notification::class),
            $notificareExistenta,
        );
        $managerEntitati->expects(self::never())->method('saveEntity');

        self::assertSame(0, $this->creeazaNotificator($managerEntitati)->notificaDacaEsteNecesara());
    }

    private function creeazaNotificator(EntityManager $managerEntitati): NotificatorDependentaZileSarbatoare
    {
        $verificator = $this->createStub(VerificatorZileSarbatoare::class);
        $verificator->method('esteInstalataCompatibil')->willReturn(false);
        $limba = $this->createStub(Language::class);
        $limba->method('translateLabel')->willReturn('Dependența lipsește.');
        $fabricaLimbi = $this->createStub(LanguageFactory::class);
        $fabricaLimbi->method('create')->willReturn($limba);

        return new NotificatorDependentaZileSarbatoare(
            $managerEntitati,
            $verificator,
            $fabricaLimbi,
            $this->createStub(Config::class),
        );
    }

    private function creeazaManagerEntitati(
        Notification $notificareNoua,
        ?Notification $notificareExistenta,
    ): EntityManager {
        $administrator = $this->createStub(User::class);
        $administrator->method('getId')->willReturn('admin-id');

        $selectorUtilizatori = $this->createStub(RDBSelectBuilder::class);
        $selectorUtilizatori
            ->method('find')
            ->willReturn(new EntityCollection([$administrator]));
        $depozitUtilizatori = $this->createStub(RDBRepository::class);
        $depozitUtilizatori->method('where')->willReturn($selectorUtilizatori);

        $selectorNotificari = $this->createStub(RDBSelectBuilder::class);
        $selectorNotificari->method('findOne')->willReturn($notificareExistenta);
        $depozitNotificari = $this->createStub(RDBRepository::class);
        $depozitNotificari->method('where')->willReturn($selectorNotificari);
        $depozitNotificari->method('getNew')->willReturn($notificareNoua);

        $depozitPreferinte = $this->createStub(Repository::class);
        $depozitPreferinte->method('getById')->willReturn(null);
        $managerEntitati = $this->createMock(EntityManager::class);
        $managerEntitati
            ->method('getRDBRepositoryByClass')
            ->willReturnCallback(
                static fn (string $clasa): RDBRepository => $clasa === User::class ?
                    $depozitUtilizatori : $depozitNotificari
            );
        $managerEntitati->method('getRepositoryByClass')->willReturn($depozitPreferinte);

        return $managerEntitati;
    }
}
