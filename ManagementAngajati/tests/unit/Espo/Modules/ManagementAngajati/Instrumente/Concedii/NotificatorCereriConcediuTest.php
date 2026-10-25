<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Language\LanguageFactory;
use Espo\Entities\Notification;
use Espo\Entities\Preferences;
use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\NotificatorCereriConcediu;
use Espo\ORM\BaseEntity;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use Espo\ORM\Repository\Repository;
use PHPUnit\Framework\TestCase;

class NotificatorCereriConcediuTest extends TestCase
{
    public function testCerereaNouaEsteNotificataTuturorAprobatorilor(): void
    {
        $cerere = $this->cerere(['manager-1', 'manager-2'], []);
        $managerUnu = $this->utilizator('manager-1');
        $managerDoi = $this->utilizator('manager-2');
        $mesaje = [];
        $serviciu = $this->serviciu(
            [$managerUnu, $managerDoi],
            ['manager-1', 'manager-2'],
            $mesaje,
        );

        $serviciu->notificaCerereNoua($cerere);

        self::assertCount(2, $mesaje);
        self::assertStringContainsString('notificareCerereNoua', $mesaje[0]);
        self::assertStringContainsString('#AdministrareConcedii', $mesaje[0]);
        self::assertStringContainsString('Angajat Test', $mesaje[0]);
    }

    public function testAprobareaPartialaNotificaNumaiManagerulCareMaiTrebuieSaDecida(): void
    {
        $cerere = $this->cerere(['manager-1', 'manager-2'], ['manager-1']);
        $managerDoi = $this->utilizator('manager-2');
        $mesaje = [];
        $serviciu = $this->serviciu([$managerDoi], ['manager-2'], $mesaje);

        $serviciu->notificaAprobarePartiala($cerere, 'Manager Unu');

        self::assertCount(1, $mesaje);
        self::assertStringContainsString('notificareAprobarePartiala', $mesaje[0]);
        self::assertStringContainsString('Manager Unu', $mesaje[0]);
        self::assertStringContainsString('#AdministrareConcedii', $mesaje[0]);
    }

    public function testDeciziaFinalaEsteNotificataAngajatului(): void
    {
        $cerere = $this->cerere(['manager-1'], ['manager-1']);
        $angajat = $this->utilizator('angajat-1');
        $mesaje = [];
        $serviciu = $this->serviciu([$angajat], ['angajat-1'], $mesaje, 2, true);

        $serviciu->notificaDecizieCerere($cerere, true);
        $serviciu->notificaDecizieCerere($cerere, false);

        self::assertCount(2, $mesaje);
        self::assertStringContainsString('notificareCerereAprobata', $mesaje[0]);
        self::assertStringContainsString('notificareCerereRespinsa', $mesaje[1]);
        self::assertStringContainsString('#CerereConcediu', $mesaje[0]);
    }

    /**
     * @param list<User> $destinatari
     * @param list<string> $idAsteptate
     * @param list<string> $mesaje
     */
    private function serviciu(
        array $destinatari,
        array $idAsteptate,
        array &$mesaje,
        ?int $numarNotificari = null,
        bool $idScalar = false,
    ): NotificatorCereriConcediu {
        $selectieUtilizatori = $this->createStub(RDBSelectBuilder::class);
        $selectieUtilizatori->method('findOne')->willReturn($destinatari[0] ?? null);
        $selectieUtilizatori->method('find')->willReturn(new EntityCollection($destinatari));
        $depozitUtilizatori = $this->createStub(RDBRepository::class);
        $depozitUtilizatori->method('where')->willReturnCallback(
            function (array $conditii) use ($idAsteptate, $idScalar, $selectieUtilizatori): RDBSelectBuilder {
                self::assertSame(
                    ['id' => $idScalar ? $idAsteptate[0] : $idAsteptate, 'isActive' => true],
                    $conditii
                );

                return $selectieUtilizatori;
            }
        );

        $numarNotificari ??= count($destinatari);
        $notificari = [];
        for ($index = 0; $index < $numarNotificari; $index++) {
            $notificare = $this->createStub(Notification::class);
            $notificare->method('setType')->willReturnSelf();
            $notificare->method('setUserId')->willReturnSelf();
            $notificare->method('setActionId')->willReturnSelf();
            $notificare->method('setRead')->willReturnSelf();
            $notificare->method('setData')->willReturnSelf();
            $notificare
                ->method('setMessage')
                ->willReturnCallback(function (string $mesaj) use (&$mesaje, $notificare): Notification {
                    $mesaje[] = $mesaj;

                    return $notificare;
                });
            $notificari[] = $notificare;
        }

        $depozitNotificari = $this->createStub(RDBRepository::class);
        $depozitNotificari->method('getNew')->willReturnOnConsecutiveCalls(...$notificari);
        $preferinte = $this->createStub(Preferences::class);
        $depozitPreferinte = $this->createStub(Repository::class);
        $depozitPreferinte->method('getById')->willReturn($preferinte);
        $managerEntitati = $this->createMock(EntityManager::class);
        $managerEntitati->method('getRDBRepositoryByClass')->willReturnCallback(
            static fn (string $clasa): RDBRepository => $clasa === User::class
                ? $depozitUtilizatori
                : $depozitNotificari
        );
        $managerEntitati->method('getRepositoryByClass')->willReturn($depozitPreferinte);
        $managerEntitati
            ->expects(self::exactly($numarNotificari))
            ->method('saveEntity')
            ->with(self::isInstanceOf(Notification::class));

        $limba = $this->createStub(Language::class);
        $limba->method('translateLabel')->willReturnCallback(
            static fn (string $cheie): string => $cheie .
                ' {manager} {angajat} {inceput} {sfarsit} {ruta}'
        );
        $fabricaLimbi = $this->createStub(LanguageFactory::class);
        $fabricaLimbi->method('create')->willReturn($limba);

        return new NotificatorCereriConcediu(
            $managerEntitati,
            $fabricaLimbi,
            $this->createStub(Config::class),
        );
    }

    /** @param list<string> $necesari @param list<string> $confirmati */
    private function cerere(array $necesari, array $confirmati): Entity
    {
        $cerere = new BaseEntity('CerereConcediu', ['attributes' => array_fill_keys([
            'id',
            'assignedUserId',
            'assignedUserName',
            'dataInceput',
            'dataSfarsit',
            'aprobatoriNecesari',
            'aprobatoriConfirmati',
            'modifiedAt',
        ], [])]);
        $cerere->set([
            'id' => 'cerere-1',
            'assignedUserId' => 'angajat-1',
            'assignedUserName' => 'Angajat Test',
            'dataInceput' => '2026-11-02',
            'dataSfarsit' => '2026-11-06',
            'aprobatoriNecesari' => $necesari,
            'aprobatoriConfirmati' => $confirmati,
            'modifiedAt' => '2026-10-24 12:00:00',
        ]);

        return $cerere;
    }

    private function utilizator(string $id): User
    {
        $utilizator = $this->createStub(User::class);
        $utilizator->method('getId')->willReturn($id);
        $utilizator->method('isActive')->willReturn(true);

        return $utilizator;
    }
}
