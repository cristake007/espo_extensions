<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use Closure;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Utils\DateTime;
use Espo\Core\Utils\Language;
use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\AcordariAnualeConcediu;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\SolduriConcediu;
use Espo\ORM\BaseEntity;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use Espo\ORM\TransactionManager;
use PHPUnit\Framework\TestCase;

class SolduriConcediuTest extends TestCase
{
    public function testDocumentulDinDecembriePornesteDeLa21Cu12ConsumateSiRamaneCu4(): void
    {
        [$serviciu, $profil, $cerere, $drept2026] = $this->creeazaScenariuDocumente(21, 21);
        $drept2026->set([
            'zileConsumate' => 12,
            'zileConsumateInitial' => 12,
        ]);

        $inainte = $serviciu->sumar('angajat-1');
        self::assertSame(9, $inainte['disponibile']);
        self::assertSame(12, $inainte['consumate']);

        $serviciu->rezerva($cerere, $profil, array_fill(0, 5, '2026-12-24'));

        self::assertSame(5, $drept2026->get('zileRezervate'));
        self::assertSame(4, $this->disponibile($drept2026));
    }

    public function testCerereaDin2027ConsumaMaiIntaiReportulRamasDin2026(): void
    {
        [$serviciu, $profil, $cerere, $drept2026, $drept2027] =
            $this->creeazaScenariuDocumente(4, 21);

        $serviciu->rezerva($cerere, $profil, [
            '2027-01-04',
            '2027-01-05',
            '2027-01-08',
        ]);

        self::assertSame(3, $drept2026->get('zileRezervate'));
        self::assertSame(0, $drept2027->get('zileRezervate'));
        self::assertSame(1, $this->disponibile($drept2026));
        self::assertSame(21, $this->disponibile($drept2027));
        self::assertSame(22, $this->disponibile($drept2026) + $this->disponibile($drept2027));
    }

    public function testCerereaDin2026NuPoateImprumutaDreptulDin2027(): void
    {
        [$serviciu, $profil, $cerere, , $drept2027] = $this->creeazaScenariuDocumente(4, 21);

        try {
            $serviciu->rezerva($cerere, $profil, array_fill(0, 5, '2026-12-24'));
            self::fail('Cererea trebuia respinsa pentru sold insuficient in 2026.');
        } catch (Conflict $exceptie) {
            self::assertSame('soldConcediuInsuficient', $exceptie->getMessage());
        }

        self::assertSame(0, $drept2027->get('zileRezervate'));
    }

    public function testDreptulAnualNuPoateFiFolositInainteDeDataAcordarii(): void
    {
        [$serviciu, $profil, $cerere, $drept2026, $drept2027] =
            $this->creeazaScenariuDocumente(0, 21);
        $drept2027->set('dataAcordare', '2027-06-01');

        $this->expectException(Conflict::class);
        $this->expectExceptionMessage('soldConcediuInsuficient');

        try {
            $serviciu->rezerva($cerere, $profil, ['2027-01-04']);
        } finally {
            self::assertSame(0, $drept2026->get('zileRezervate'));
            self::assertSame(0, $drept2027->get('zileRezervate'));
        }
    }

    public function testCronologiaCererilorReconstruiesteSoldulDinRegistru(): void
    {
        $profil = new BaseEntity('ProfilConcediu', ['attributes' => ['id' => []]]);
        $profil->set('id', 'profil-1');
        $cerereUnu = $this->creeazaCerereCronologie(
            'cerere-1',
            '2026-11-02',
            '2026-11-04',
            3,
            'Aprobata',
            'Odihnă'
        );
        $cerereDoi = $this->creeazaCerereCronologie(
            'cerere-2',
            '2027-01-11',
            '2027-01-12',
            2,
            'Anulata',
            null
        );
        $inregistrari = [
            $this->creeazaInregistrareCronologie('registru-1', 'Initializare', 10, null),
            $this->creeazaInregistrareCronologie('registru-2', 'Rezervare', -3, 'cerere-1'),
            $this->creeazaInregistrareCronologie('registru-3', 'Consumare', -3, 'cerere-1'),
            $this->creeazaInregistrareCronologie('registru-4', 'AcordareAnuala', 5, null),
            $this->creeazaInregistrareCronologie('registru-5', 'Rezervare', -1, 'cerere-2'),
            $this->creeazaInregistrareCronologie('registru-6', 'Rezervare', -1, 'cerere-2'),
            $this->creeazaInregistrareCronologie('registru-7', 'Eliberare', 2, 'cerere-2'),
        ];
        $depozitRegistru = $this->createStub(RDBRepository::class);
        $depozitRegistru->method('where')->willReturn($this->creeazaSelectie($inregistrari));
        $manager = $this->createStub(EntityManager::class);
        $manager->method('getRDBRepository')->with('RegistruConcediu')
            ->willReturn($depozitRegistru);
        $serviciu = new SolduriConcediu(
            $manager,
            $this->createStub(User::class),
            $this->createStub(Language::class),
            new DateTime(timeZone: 'UTC'),
            $this->createStub(AcordariAnualeConcediu::class),
        );

        $cronologie = $serviciu->cronologieCereri($profil, [$cerereDoi, $cerereUnu]);

        self::assertCount(4, $cronologie);
        self::assertTrue($cronologie[0]['esteInitializare']);
        self::assertNull($cronologie[0]['idCerere']);
        self::assertSame(10, $cronologie[0]['soldDupa']);
        self::assertSame('cerere-1', $cronologie[1]['idCerere']);
        self::assertSame(10, $cronologie[1]['soldInainte']);
        self::assertSame(7, $cronologie[1]['soldDupa']);
        self::assertSame('cerere-2', $cronologie[2]['idCerere']);
        self::assertSame(12, $cronologie[2]['soldInainte']);
        self::assertSame(10, $cronologie[2]['soldDupa']);
        self::assertSame(2, $cronologie[2]['zileRezervate']);
        self::assertSame(0, $cronologie[2]['zileRestituite']);
        self::assertSame(-2, $cronologie[2]['impactFinal']);
        self::assertSame('Anulata', $cronologie[2]['stare']);
        self::assertSame('Restituire', $cronologie[3]['tipEveniment']);
        self::assertSame('cerere-2', $cronologie[3]['idCerere']);
        self::assertSame(10, $cronologie[3]['soldInainte']);
        self::assertSame(12, $cronologie[3]['soldDupa']);
        self::assertSame(2, $cronologie[3]['zileRestituite']);
        self::assertSame(2, $cronologie[3]['impactFinal']);
    }

    public function testCorectiaNegativaNuPoateDepasiSoldulDisponibil(): void
    {
        $profil = new BaseEntity('ProfilConcediu', ['attributes' => [
            'id' => [], 'assignedUserId' => [], 'initializat' => [],
        ]]);
        $profil->set(['id' => 'profil-1', 'assignedUserId' => 'angajat-1', 'initializat' => true]);
        $drept = new BaseEntity('DreptConcediu', ['attributes' => [
            'id' => [], 'zileAcordate' => [], 'zileRezervate' => [], 'zileConsumate' => [],
        ]]);
        $drept->set([
            'id' => 'drept-1', 'zileAcordate' => 5, 'zileRezervate' => 2, 'zileConsumate' => 1,
        ]);

        $selectieProfil = $this->creeazaSelectie([$profil]);
        $selectieDrept = $this->creeazaSelectie([$drept]);
        $depozitProfil = $this->createStub(RDBRepository::class);
        $depozitProfil->method('where')->willReturn($selectieProfil);
        $depozitDrept = $this->createStub(RDBRepository::class);
        $depozitDrept->method('where')->willReturn($selectieDrept);
        $tranzactie = $this->createStub(TransactionManager::class);
        $tranzactie->method('run')->willReturnCallback(
            static fn (Closure $operatie): mixed => $operatie()
        );
        $manager = $this->createStub(EntityManager::class);
        $manager->method('getTransactionManager')->willReturn($tranzactie);
        $manager->method('getRDBRepository')->willReturnCallback(
            static fn (string $tip): RDBRepository => match ($tip) {
                'ProfilConcediu' => $depozitProfil,
                'DreptConcediu' => $depozitDrept,
            }
        );
        $limba = $this->createStub(Language::class);
        $limba->method('translateLabel')->willReturn(
            'Nu se pot scădea {scadere} zile; soldul disponibil este {disponibile}.'
        );

        $serviciu = new SolduriConcediu(
            $manager,
            $this->createStub(User::class),
            $limba,
            new DateTime(timeZone: 'UTC'),
            $this->createStub(AcordariAnualeConcediu::class),
        );

        $this->expectException(Conflict::class);
        $this->expectExceptionMessage('corectieSoldPesteDisponibil');
        $serviciu->corecteaza('angajat-1', -3, 'Corecție justificată');
    }

    public function testAnulareaAprobataRestaureazaExactAlocareaConsumata(): void
    {
        $profil = new BaseEntity('ProfilConcediu', ['attributes' => [
            'id' => [], 'name' => [], 'assignedUserId' => [],
        ]]);
        $profil->set([
            'id' => 'profil-1',
            'name' => 'Concediu · Angajat',
            'assignedUserId' => 'angajat-1',
        ]);
        $cerere = new BaseEntity('CerereConcediu', ['attributes' => ['id' => [], 'name' => []]]);
        $cerere->set(['id' => 'cerere-1', 'name' => 'Cerere aprobată']);
        $drept = $this->creeazaDrept('drept-2026', 2026, 21, '2026-01-01');
        $drept->set(['zileConsumate' => 17, 'zileConsumateInitial' => 12]);
        $alocare = new BaseEntity('AlocareConcediu', ['attributes' => [
            'id' => [], 'dreptId' => [], 'numarZile' => [], 'stare' => [],
        ]]);
        $alocare->set([
            'id' => 'alocare-1',
            'dreptId' => 'drept-2026',
            'numarZile' => 5,
            'stare' => 'Consumata',
        ]);

        $depozitAlocare = $this->createStub(RDBRepository::class);
        $depozitAlocare->method('where')->willReturn($this->creeazaSelectie([$alocare]));
        $depozitDrept = $this->createStub(RDBRepository::class);
        $depozitDrept->method('where')->willReturn($this->creeazaSelectie([$drept]));
        $manager = $this->createStub(EntityManager::class);
        $manager->method('getRDBRepository')->willReturnCallback(
            static fn (string $tip): RDBRepository => match ($tip) {
                'AlocareConcediu' => $depozitAlocare,
                'DreptConcediu' => $depozitDrept,
            }
        );
        $manager->method('getNewEntity')->willReturnCallback(
            static fn (string $tip): BaseEntity => new BaseEntity($tip, ['attributes' => [
                'name' => [], 'profilId' => [], 'cerereId' => [], 'dreptId' => [],
                'tip' => [], 'numarZile' => [], 'motiv' => [], 'cheieOperatie' => [],
                'dataEfectiva' => [], 'assignedUserId' => [], 'actorId' => [],
            ]])
        );
        $utilizator = $this->createStub(User::class);
        $utilizator->method('getId')->willReturn('manager-1');
        $serviciu = new SolduriConcediu(
            $manager,
            $utilizator,
            $this->createStub(Language::class),
            new DateTime(timeZone: 'UTC'),
            $this->createStub(AcordariAnualeConcediu::class),
        );

        $serviciu->restaureaza($cerere, $profil, 'Planurile s-au schimbat');

        self::assertSame(12, $drept->get('zileConsumate'));
        self::assertSame('Eliberata', $alocare->get('stare'));
    }

    /** @param list<BaseEntity> $entitati */
    private function creeazaSelectie(array $entitati): RDBSelectBuilder
    {
        $selectie = $this->createStub(RDBSelectBuilder::class);
        $selectie->method('order')->willReturnSelf();
        $selectie->method('forUpdate')->willReturnSelf();
        $selectie->method('findOne')->willReturn($entitati[0] ?? null);
        $selectie->method('find')->willReturn(new EntityCollection($entitati));

        return $selectie;
    }

    /**
     * @return array{SolduriConcediu, BaseEntity, BaseEntity, BaseEntity, BaseEntity}
     */
    private function creeazaScenariuDocumente(int $report2026, int $drept2027): array
    {
        $profil = new BaseEntity('ProfilConcediu', ['attributes' => [
            'id' => [], 'name' => [], 'assignedUserId' => [], 'dataUrmatoareiAcordari' => [],
        ]]);
        $profil->set([
            'id' => 'profil-1',
            'name' => 'Concediu · Angajat',
            'assignedUserId' => 'angajat-1',
            'dataUrmatoareiAcordari' => '2027-01-01',
        ]);
        $cerere = new BaseEntity('CerereConcediu', ['attributes' => ['id' => [], 'name' => []]]);
        $cerere->set(['id' => 'cerere-1', 'name' => 'Cerere test']);
        $dreptVechi = $this->creeazaDrept('drept-2026', 2026, $report2026, '2026-01-01');
        $dreptNou = $this->creeazaDrept('drept-2027', 2027, $drept2027, '2027-01-01');

        $depozitDrept = $this->createStub(RDBRepository::class);
        $depozitDrept->method('where')->willReturn(
            $this->creeazaSelectie([$dreptVechi, $dreptNou])
        );
        $manager = $this->createStub(EntityManager::class);
        $depozitAlocare = $this->createStub(RDBRepository::class);
        $depozitAlocare->method('where')->willReturn($this->creeazaSelectie([]));
        $manager->method('getRDBRepository')->willReturnCallback(
            static fn (string $tip): RDBRepository => match ($tip) {
                'DreptConcediu' => $depozitDrept,
                'AlocareConcediu' => $depozitAlocare,
            }
        );
        $manager->method('getNewEntity')->willReturnCallback(
            static fn (string $tip): BaseEntity => new BaseEntity($tip, ['attributes' => [
                'name' => [], 'cerereId' => [], 'dreptId' => [], 'anConsum' => [],
                'numarZile' => [], 'stare' => [], 'profilId' => [], 'tip' => [],
                'motiv' => [], 'cheieOperatie' => [], 'dataEfectiva' => [],
                'assignedUserId' => [], 'actorId' => [],
            ]])
        );
        $limba = $this->createStub(Language::class);
        $limba->method('translateLabel')->willReturn(
            'Sold insuficient pentru anul {an}: {necesare} necesare, {disponibile} disponibile.'
        );
        $utilizator = $this->createStub(User::class);
        $utilizator->method('getId')->willReturn('manager-1');

        return [
            new SolduriConcediu(
                $manager,
                $utilizator,
                $limba,
                new DateTime(timeZone: 'UTC'),
                $this->createStub(AcordariAnualeConcediu::class),
            ),
            $profil,
            $cerere,
            $dreptVechi,
            $dreptNou,
        ];
    }

    private function creeazaDrept(string $id, int $an, int $acordate, string $data): BaseEntity
    {
        $drept = new BaseEntity('DreptConcediu', ['attributes' => [
            'id' => [], 'name' => [], 'anOrigine' => [], 'zileAcordate' => [],
            'zileRezervate' => [], 'zileConsumate' => [], 'dataAcordare' => [],
            'zileConsumateInitial' => [],
        ]]);
        $drept->set([
            'id' => $id,
            'name' => "Drept $an",
            'anOrigine' => $an,
            'zileAcordate' => $acordate,
            'zileRezervate' => 0,
            'zileConsumate' => 0,
            'zileConsumateInitial' => 0,
            'dataAcordare' => $data,
        ]);

        return $drept;
    }

    private function creeazaCerereCronologie(
        string $id,
        string $dataInceput,
        string $dataSfarsit,
        int $numarZile,
        string $stare,
        ?string $descriere
    ): BaseEntity {
        $cerere = new BaseEntity('CerereConcediu', ['attributes' => [
            'id' => [], 'dataInceput' => [], 'dataSfarsit' => [], 'numarZile' => [],
            'stare' => [], 'descriere' => [],
        ]]);
        $cerere->set([
            'id' => $id,
            'dataInceput' => $dataInceput,
            'dataSfarsit' => $dataSfarsit,
            'numarZile' => $numarZile,
            'stare' => $stare,
            'descriere' => $descriere,
        ]);

        return $cerere;
    }

    private function creeazaInregistrareCronologie(
        string $id,
        string $tip,
        int $numarZile,
        ?string $idCerere
    ): BaseEntity {
        $inregistrare = new BaseEntity('RegistruConcediu', ['attributes' => [
            'id' => [], 'tip' => [], 'numarZile' => [], 'cerereId' => [],
            'dataEfectiva' => [], 'createdAt' => [],
        ]]);
        $inregistrare->set([
            'id' => $id,
            'tip' => $tip,
            'numarZile' => $numarZile,
            'cerereId' => $idCerere,
            'dataEfectiva' => '2026-10-24',
            'createdAt' => '2026-10-24 10:00:00',
        ]);

        return $inregistrare;
    }

    private function disponibile(BaseEntity $drept): int
    {
        return (int) $drept->get('zileAcordate') -
            (int) $drept->get('zileRezervate') -
            (int) $drept->get('zileConsumate');
    }
}
