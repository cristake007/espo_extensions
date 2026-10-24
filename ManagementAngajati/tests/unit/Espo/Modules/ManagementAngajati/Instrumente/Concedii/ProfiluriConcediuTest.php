<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use Closure;
use Espo\Core\Utils\DateTime;
use Espo\Core\Utils\Language;
use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Angajati\OrdonatorAngajati;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\AcordariAnualeConcediu;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\ProfiluriConcediu;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\SolduriConcediu;
use Espo\ORM\BaseEntity;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use Espo\ORM\TransactionManager;
use PHPUnit\Framework\TestCase;

class ProfiluriConcediuTest extends TestCase
{
    public function testInitializareaCu21ConfigurateSi5DisponibileNuInventeaza16Consumate(): void
    {
        $anAcordare = (int) date('Y') + 1;
        $dataAcordare = $anAcordare . '-03-01';
        [$profiluri, $drepturi, $registru] =
            $this->initializeazaProfil(21, 5, 0, $dataAcordare);

        self::assertCount(1, $profiluri);
        self::assertSame(21, $profiluri[0]->get('zileAnuale'));
        self::assertSame($dataAcordare, $profiluri[0]->get('dataUrmatoareiAcordari'));
        self::assertCount(1, $drepturi);
        $drept = $drepturi[0];

        self::assertSame($anAcordare - 1, $drept->get('anOrigine'));
        self::assertSame('Anual', $drept->get('tip'));
        self::assertSame(5, $drept->get('zileAcordate'));
        self::assertSame(0, $drept->get('zileRezervate'));
        self::assertSame(0, $drept->get('zileConsumate'));
        self::assertSame(0, $drept->get('zileConsumateInitial'));
        self::assertSame(($anAcordare - 1) . '-03-01', $drept->get('dataAcordare'));

        self::assertCount(1, $registru);
        self::assertSame(5, $registru[0]->get('numarZile'));

        $sold = $this->calculeazaSold([$drept]);
        self::assertSame(5, $sold['disponibile']);
        self::assertSame(0, $sold['rezervate']);
        self::assertSame(0, $sold['consumate']);
        self::assertSame(5, $sold['peAni'][0]['acordate']);
        self::assertSame(0, $sold['peAni'][0]['consumate']);
    }

    public function testReportulInitialRamaneTransaSeparataSiNuDevineConsum(): void
    {
        $dataAcordare = ((int) date('Y') + 1) . '-03-01';
        [, $drepturi] = $this->initializeazaProfil(21, 5, 3, $dataAcordare);

        self::assertCount(2, $drepturi);
        self::assertSame(['Anual', 'ReportInitial'], array_map(
            static fn (Entity $drept): string => (string) $drept->get('tip'),
            $drepturi
        ));
        self::assertSame([5, 3], array_map(
            static fn (Entity $drept): int => (int) $drept->get('zileAcordate'),
            $drepturi
        ));

        $sold = $this->calculeazaSold($drepturi);
        self::assertSame(8, $sold['disponibile']);
        self::assertSame(0, $sold['rezervate']);
        self::assertSame(0, $sold['consumate']);
    }

    /**
     * @return array{list<Entity>, list<Entity>, list<Entity>}
     */
    private function initializeazaProfil(
        int $zileAnuale,
        int $disponibile,
        int $reportate,
        string $dataUrmatoareiAcordari,
    ): array {
        $angajat = $this->createStub(User::class);
        $angajat->method('getId')->willReturn('angajat-1');
        $angajat->method('getName')->willReturn('Angajat Test');

        $selectieProfil = $this->selectie([]);
        $depozitProfil = $this->createStub(RDBRepository::class);
        $depozitProfil->method('where')->willReturn($selectieProfil);

        $selectieAngajat = $this->selectie([$angajat]);
        $depozitAngajat = $this->createStub(RDBRepository::class);
        $depozitAngajat->method('where')->willReturn($selectieAngajat);

        $tranzactie = $this->createStub(TransactionManager::class);
        $tranzactie->method('run')->willReturnCallback(
            static fn (Closure $operatie): mixed => $operatie()
        );

        $salvate = [];
        $manager = $this->createStub(EntityManager::class);
        $manager->method('getTransactionManager')->willReturn($tranzactie);
        $manager->method('getRDBRepository')->willReturn($depozitProfil);
        $manager->method('getRDBRepositoryByClass')->willReturn($depozitAngajat);
        $manager->method('getNewEntity')->willReturnCallback(
            function (string $tip) use (&$salvate): BaseEntity {
                $atribute = match ($tip) {
                    'ProfilConcediu' => [
                        'id', 'name', 'assignedUserId', 'assignedUserName', 'zileAnuale',
                        'dataUrmatoareiAcordari', 'initializat',
                    ],
                    'DreptConcediu' => [
                        'id', 'name', 'profilId', 'assignedUserId', 'assignedUserName',
                        'anOrigine', 'tip', 'zileAcordate', 'zileRezervate',
                        'zileConsumate', 'zileConsumateInitial', 'dataAcordare',
                    ],
                    'RegistruConcediu' => [
                        'id', 'name', 'profilId', 'dreptId', 'tip', 'numarZile', 'motiv',
                        'cheieOperatie', 'dataEfectiva', 'assignedUserId', 'actorId',
                    ],
                };
                $entitate = new BaseEntity($tip, ['attributes' => array_fill_keys($atribute, [])]);
                $entitate->set('id', strtolower($tip) . '-' . (count($salvate[$tip] ?? []) + 1));

                return $entitate;
            }
        );
        $manager->method('saveEntity')->willReturnCallback(
            static function (Entity $entitate) use (&$salvate): Entity {
                $salvate[$entitate->getEntityType()][] = $entitate;

                return $entitate;
            }
        );

        $actor = $this->createStub(User::class);
        $actor->method('getId')->willReturn('manager-1');
        $solduri = $this->createStub(SolduriConcediu::class);
        $solduri->method('sumar')->willReturn([
            'disponibile' => $disponibile + $reportate,
            'rezervate' => 0,
            'consumate' => 0,
            'peAni' => [],
        ]);

        $serviciu = new ProfiluriConcediu(
            $manager,
            $this->createStub(OrdonatorAngajati::class),
            $solduri,
            $actor,
            new DateTime(timeZone: 'UTC'),
        );
        $serviciu->initializeaza([
            'idAngajat' => 'angajat-1',
            'zileAnuale' => $zileAnuale,
            'zileDisponibileAnCurent' => $disponibile,
            'zileReportateAnAnterior' => $reportate,
            'dataUrmatoareiAcordari' => $dataUrmatoareiAcordari,
        ]);

        return [
            $salvate['ProfilConcediu'] ?? [],
            $salvate['DreptConcediu'] ?? [],
            $salvate['RegistruConcediu'] ?? [],
        ];
    }

    /**
     * @param list<Entity> $drepturi
     * @return array{disponibile: int, rezervate: int, consumate: int, peAni: list<array<string, int|string>>}
     */
    private function calculeazaSold(array $drepturi): array
    {
        $depozitDrepturi = $this->createStub(RDBRepository::class);
        $depozitDrepturi->method('where')->willReturn($this->selectie($drepturi));
        $depozitAlocari = $this->createStub(RDBRepository::class);
        $depozitAlocari->method('where')->willReturn($this->selectie([]));

        $manager = $this->createStub(EntityManager::class);
        $manager->method('getRDBRepository')->willReturnCallback(
            static fn (string $tip): RDBRepository => match ($tip) {
                'DreptConcediu' => $depozitDrepturi,
                'AlocareConcediu' => $depozitAlocari,
            }
        );

        $serviciu = new SolduriConcediu(
            $manager,
            $this->createStub(User::class),
            $this->createStub(Language::class),
            new DateTime(timeZone: 'UTC'),
            $this->createStub(AcordariAnualeConcediu::class),
        );

        return $serviciu->sumar('angajat-1');
    }

    /** @param list<Entity> $entitati */
    private function selectie(array $entitati): RDBSelectBuilder
    {
        $selectie = $this->createStub(RDBSelectBuilder::class);
        $selectie->method('order')->willReturnSelf();
        $selectie->method('forUpdate')->willReturnSelf();
        $selectie->method('findOne')->willReturn($entitati[0] ?? null);
        $selectie->method('find')->willReturn(new EntityCollection($entitati));

        return $selectie;
    }
}
