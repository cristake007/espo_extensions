<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Closure;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Entities\Notification;
use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\ConfiguratiiCondicaAngajati;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\ResetatorDateTestCondica;
use Espo\ORM\BaseEntity;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use Espo\ORM\TransactionManager;
use PHPUnit\Framework\TestCase;

class ResetatorDateTestCondicaTest extends TestCase
{
    public function testReseteazaDateleExtensieiSiPastreazaCalendarulDeLucru(): void
    {
        $prezenta = $this->creeazaEntitate('Prezenta', 'prezenta-1');
        $stare = $this->creeazaEntitate('StareLunaCondica', 'stare-1');
        $configuratieAngajat = $this->creeazaEntitate(
            'ConfiguratieCondicaAngajat',
            'configuratie-1'
        );
        $notificare = new Notification(Notification::ENTITY_TYPE, [
            'attributes' => ['id' => [], 'data' => []],
        ]);
        $notificare->set('id', 'notificare-1');
        $notificare->setData((object) [
            'managementAngajatiReminderPrezenta' => true,
            'luna' => '2026-10',
            'numarZileLipsa' => 2,
        ]);
        $angajat = $this->createMock(User::class);
        $angajat->method('get')->with('dataAngajare')->willReturn('2020-01-01');
        $angajat->expects(self::once())->method('set')->with('dataAngajare', null);
        $depozitPrezenta = $this->creeazaDepozit($prezenta);
        $depozitStare = $this->creeazaDepozit($stare, true);
        $depozitConfiguratii = $this->creeazaDepozit($configuratieAngajat);
        $depozitNotificari = $this->creeazaDepozit($notificare);
        $depozitNotificari
            ->method('where')
            ->willReturn($this->creeazaSelectie([$notificare]));
        $depozitCalendare = $this->createStub(RDBRepository::class);
        $depozitCalendare->method('count')->willReturn(3);
        $selectieDateAngajare = $this->creeazaSelectie([$angajat]);
        $selectieResetUtilizatori = $this->creeazaSelectie([$angajat]);
        $depozitUtilizatori = $this->createStub(RDBRepository::class);
        $depozitUtilizatori
            ->method('where')
            ->willReturnCallback(
                static fn (array $unde): RDBSelectBuilder =>
                    array_key_exists('dataAngajare!=', $unde) ?
                        $selectieDateAngajare :
                        $selectieResetUtilizatori
            );
        $tranzactie = $this->createMock(TransactionManager::class);
        $tranzactie
            ->expects(self::once())
            ->method('run')
            ->willReturnCallback(static fn (Closure $operatie): mixed => $operatie());
        $manager = $this->createMock(EntityManager::class);
        $manager->method('getTransactionManager')->willReturn($tranzactie);
        $manager
            ->method('getRDBRepository')
            ->willReturnCallback(static fn (string $tip): RDBRepository => match ($tip) {
                'Prezenta' => $depozitPrezenta,
                'StareLunaCondica' => $depozitStare,
                'ConfiguratieCondicaAngajat' => $depozitConfiguratii,
                'Notification' => $depozitNotificari,
                'WorkingTimeCalendar' => $depozitCalendare,
            });
        $manager
            ->method('getRDBRepositoryByClass')
            ->willReturnCallback(static fn (string $clasa): RDBRepository => match ($clasa) {
                User::class => $depozitUtilizatori,
                Notification::class => $depozitNotificari,
            });
        $manager->expects(self::exactly(4))->method('removeEntity');
        $manager->expects(self::once())->method('saveEntity')->with($angajat);
        $configuratie = $this->createStub(Config::class);
        $configuratie->method('has')->willReturn(true);
        $configuratie->method('get')->willReturnMap([
            [
                ConfiguratiiCondicaAngajati::CHEIE_MOD_REMOTE,
                ConfiguratiiCondicaAngajati::MOD_REMOTE_CURENT,
                ConfiguratiiCondicaAngajati::MOD_REMOTE_INTERVAL,
            ],
            [
                ConfiguratiiCondicaAngajati::CHEIE_LUNI_ANTERIOARE_EDITABILE,
                ConfiguratiiCondicaAngajati::LUNI_ANTERIOARE_EDITABILE_IMPLICIT,
                3,
            ],
        ]);
        $scriitor = $this->createMock(ConfigWriter::class);
        $scriitor->expects(self::exactly(2))->method('remove');
        $scriitor->expects(self::once())->method('save');

        $rezultat = (new ResetatorDateTestCondica(
            $manager,
            $configuratie,
            $scriitor,
        ))->reseteaza();

        self::assertSame(1, $rezultat['sterse']['prezente']);
        self::assertSame(1, $rezultat['sterse']['stariLuna']);
        self::assertSame(1, $rezultat['sterse']['remindere']);
        self::assertSame(1, $rezultat['sterse']['configuratiiCicRemote']);
        self::assertSame(1, $rezultat['sterse']['dateAngajare']);
        self::assertSame(3, $rezultat['sterse']['calendareLucruPastrate']);
    }

    private function creeazaEntitate(string $tip, string $id): Entity
    {
        $entitate = new BaseEntity($tip, ['attributes' => ['id' => []]]);
        $entitate->set('id', $id);

        return $entitate;
    }

    private function creeazaDepozit(Entity $entitate, bool $areStareInchisa = false): RDBRepository
    {
        $depozit = $this->createMock(RDBRepository::class);
        $depozit->method('find')->willReturn(new EntityCollection([$entitate]));
        $depozit->expects(self::once())->method('deleteFromDb')->with($entitate->getId(), true);

        if ($areStareInchisa) {
            $depozit->method('where')->willReturn($this->creeazaSelectie([$entitate]));
        }

        return $depozit;
    }

    /** @param list<Entity> $entitati */
    private function creeazaSelectie(array $entitati): RDBSelectBuilder
    {
        $selectie = $this->createStub(RDBSelectBuilder::class);
        $selectie->method('order')->willReturnSelf();
        $selectie->method('forUpdate')->willReturnSelf();
        $selectie->method('find')->willReturn(new EntityCollection($entitati));

        return $selectie;
    }
}
