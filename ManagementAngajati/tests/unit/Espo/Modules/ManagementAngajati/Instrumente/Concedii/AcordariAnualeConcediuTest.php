<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use Espo\Core\Utils\DateTime;
use Espo\Core\Utils\SystemUser;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\AcordariAnualeConcediu;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\SetariConcedii;
use Espo\ORM\BaseEntity;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use Espo\ORM\EntityCollection;
use Espo\ORM\TransactionManager;
use PHPUnit\Framework\TestCase;

class AcordariAnualeConcediuTest extends TestCase
{
    public function testPregatesteNumaiDrepturileAnilorViitoriSolicitati(): void
    {
        $profil = new BaseEntity('ProfilConcediu', ['attributes' => [
            'id' => [], 'assignedUserId' => [], 'assignedUserName' => [],
            'zileAnuale' => [], 'dataUrmatoareiAcordari' => [],
        ]]);
        $profil->set([
            'id' => 'profil-1',
            'assignedUserId' => 'angajat-1',
            'assignedUserName' => 'Angajat Test',
            'zileAnuale' => 21,
            'dataUrmatoareiAcordari' => '2027-01-01',
        ]);
        $selectie = $this->createStub(RDBSelectBuilder::class);
        $selectie->method('forUpdate')->willReturnSelf();
        $selectie->method('findOne')->willReturn(null);
        $depozit = $this->createStub(RDBRepository::class);
        $depozit->method('where')->willReturn($selectie);
        $salvate = [];
        $manager = $this->createStub(EntityManager::class);
        $manager->method('getRDBRepository')->willReturn($depozit);
        $manager->method('getNewEntity')->willReturnCallback(
            static fn (): BaseEntity => new BaseEntity('DreptConcediu', ['attributes' => [
                'name' => [], 'profilId' => [], 'assignedUserId' => [], 'assignedUserName' => [],
                'anOrigine' => [], 'tip' => [], 'zileAcordate' => [], 'zileRezervate' => [],
                'zileConsumate' => [], 'zileConsumateInitial' => [], 'dataAcordare' => [],
            ]])
        );
        $manager->method('saveEntity')->willReturnCallback(
            static function (Entity $entitate) use (&$salvate): Entity {
                $salvate[] = $entitate;
                return $entitate;
            }
        );

        $setari = $this->createStub(SetariConcedii::class);
        $setari->method('esteAcordareaAnualaAutomataActiva')->willReturn(true);
        $serviciu = new AcordariAnualeConcediu(
            $manager,
            new DateTime(timeZone: 'UTC'),
            $this->createStub(SystemUser::class),
            $setari,
        );
        $serviciu->pregatestePentruAni($profil, [2026, 2027, 2028, 2028]);

        self::assertCount(2, $salvate);
        self::assertSame([2027, 2028], array_map(
            static fn (Entity $drept): int => (int) $drept->get('anOrigine'),
            $salvate
        ));
        self::assertSame(['2027-01-01', '2028-01-01'], array_map(
            static fn (Entity $drept): string => (string) $drept->get('dataAcordare'),
            $salvate
        ));
        self::assertSame([21, 21], array_map(
            static fn (Entity $drept): int => (int) $drept->get('zileAcordate'),
            $salvate
        ));
    }

    public function testNuPregatesteSiNuProceseazaDrepturiCandAcordareaEsteDezactivata(): void
    {
        $profil = new BaseEntity('ProfilConcediu', ['attributes' => [
            'id' => [], 'dataUrmatoareiAcordari' => [],
        ]]);
        $profil->set([
            'id' => 'profil-1',
            'dataUrmatoareiAcordari' => '2027-01-01',
        ]);
        $manager = $this->createMock(EntityManager::class);
        $manager->expects(self::never())->method('getRDBRepository');
        $setari = $this->createStub(SetariConcedii::class);
        $setari->method('esteAcordareaAnualaAutomataActiva')->willReturn(false);
        $serviciu = new AcordariAnualeConcediu(
            $manager,
            new DateTime(timeZone: 'UTC'),
            $this->createStub(SystemUser::class),
            $setari,
        );

        $serviciu->pregatestePentruAni($profil, [2027]);
        $serviciu->acordaScadente();
    }

    public function testRecupereazaScadenteleRatateFaraSaDublezeAcordarea(): void
    {
        $anCurent = (int) date('Y');
        $anInitial = $anCurent - 2;
        $profil = new BaseEntity('ProfilConcediu', ['attributes' => [
            'id' => [], 'assignedUserId' => [], 'assignedUserName' => [],
            'zileAnuale' => [], 'dataUrmatoareiAcordari' => [], 'initializat' => [],
        ]]);
        $profil->set([
            'id' => 'profil-1',
            'assignedUserId' => 'angajat-1',
            'assignedUserName' => 'Angajat Test',
            'zileAnuale' => 21,
            'dataUrmatoareiAcordari' => $anInitial . '-01-01',
            'initializat' => true,
        ]);
        $drepturi = [];
        $registru = [];

        $depozitProfil = $this->createStub(RDBRepository::class);
        $depozitProfil->method('where')->willReturnCallback(
            function (array $conditii) use ($profil): RDBSelectBuilder {
                $esteScadent = !isset($conditii['dataUrmatoareiAcordari<=']) ||
                    (string) $profil->get('dataUrmatoareiAcordari') <=
                    (string) $conditii['dataUrmatoareiAcordari<='];

                return $this->selectie($esteScadent ? $profil : null, $esteScadent ? [$profil] : []);
            }
        );
        $depozitDrepturi = $this->createStub(RDBRepository::class);
        $depozitDrepturi->method('where')->willReturnCallback(
            function (array $conditii) use (&$drepturi): RDBSelectBuilder {
                $gasit = null;
                foreach ($drepturi as $drept) {
                    if ((int) $drept->get('anOrigine') === (int) ($conditii['anOrigine'] ?? 0)) {
                        $gasit = $drept;
                        break;
                    }
                }

                return $this->selectie($gasit);
            }
        );
        $depozitRegistru = $this->createStub(RDBRepository::class);
        $depozitRegistru->method('where')->willReturnCallback(
            function (array $conditii) use (&$registru): RDBSelectBuilder {
                $gasit = null;
                foreach ($registru as $inregistrare) {
                    if ($inregistrare->get('cheieOperatie') === ($conditii['cheieOperatie'] ?? null)) {
                        $gasit = $inregistrare;
                        break;
                    }
                }

                return $this->selectie($gasit);
            }
        );
        $tranzactie = $this->createStub(TransactionManager::class);
        $tranzactie->method('run')->willReturnCallback(
            static fn (\Closure $operatie): mixed => $operatie()
        );
        $manager = $this->createStub(EntityManager::class);
        $manager->method('getTransactionManager')->willReturn($tranzactie);
        $manager->method('getRDBRepository')->willReturnCallback(
            static fn (string $tip): RDBRepository => match ($tip) {
                'ProfilConcediu' => $depozitProfil,
                'DreptConcediu' => $depozitDrepturi,
                'RegistruConcediu' => $depozitRegistru,
            }
        );
        $manager->method('getNewEntity')->willReturnCallback(
            static function (string $tip): BaseEntity {
                $atribute = $tip === 'DreptConcediu' ? [
                    'id', 'name', 'profilId', 'assignedUserId', 'assignedUserName',
                    'anOrigine', 'tip', 'zileAcordate', 'zileRezervate',
                    'zileConsumate', 'zileConsumateInitial', 'dataAcordare',
                ] : [
                    'id', 'name', 'profilId', 'dreptId', 'tip', 'numarZile', 'motiv',
                    'cheieOperatie', 'dataEfectiva', 'assignedUserId', 'actorId',
                ];

                return new BaseEntity($tip, ['attributes' => array_fill_keys($atribute, [])]);
            }
        );
        $manager->method('saveEntity')->willReturnCallback(
            static function (Entity $entitate) use (&$drepturi, &$registru): Entity {
                if ($entitate->getEntityType() === 'DreptConcediu' && !$entitate->hasId()) {
                    $entitate->set('id', 'drept-' . (count($drepturi) + 1));
                    $drepturi[] = $entitate;
                }
                if ($entitate->getEntityType() === 'RegistruConcediu' && !$entitate->hasId()) {
                    $entitate->set('id', 'registru-' . (count($registru) + 1));
                    $registru[] = $entitate;
                }

                return $entitate;
            }
        );
        $setari = $this->createStub(SetariConcedii::class);
        $setari->method('esteAcordareaAnualaAutomataActiva')->willReturn(true);
        $utilizatorSistem = $this->createStub(SystemUser::class);
        $utilizatorSistem->method('getId')->willReturn('system');
        $serviciu = new AcordariAnualeConcediu(
            $manager,
            new DateTime(timeZone: 'UTC'),
            $utilizatorSistem,
            $setari,
        );

        $serviciu->acordaScadente();
        $serviciu->acordaScadente();

        self::assertSame($anCurent + 1 . '-01-01', $profil->get('dataUrmatoareiAcordari'));
        self::assertSame(
            range($anInitial, $anCurent),
            array_map(static fn (Entity $drept): int => (int) $drept->get('anOrigine'), $drepturi)
        );
        self::assertCount(3, $registru);
        self::assertSame(
            array_map(
                static fn (int $an): string => 'acordare-anuala:profil-1:' . $an,
                range($anInitial, $anCurent)
            ),
            array_map(
                static fn (Entity $inregistrare): string =>
                    (string) $inregistrare->get('cheieOperatie'),
                $registru
            )
        );
    }

    /** @param list<Entity> $entitati */
    private function selectie(?Entity $entitate, array $entitati = []): RDBSelectBuilder
    {
        $selectie = $this->createStub(RDBSelectBuilder::class);
        $selectie->method('forUpdate')->willReturnSelf();
        $selectie->method('findOne')->willReturn($entitate);
        $selectie->method('find')->willReturn(new EntityCollection($entitati));

        return $selectie;
    }
}
