<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use Closure;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Utils\DateTime;
use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\CorectorProgramAcordareConcediu;
use Espo\ORM\BaseEntity;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use Espo\ORM\TransactionManager;
use PHPUnit\Framework\TestCase;

class CorectorProgramAcordareConcediuTest extends TestCase
{
    public function testMutaDreptulViitorNeutilizatSiInregistreazaCorectia(): void
    {
        $an = (int) date('Y') + 1;
        $dataVeche = sprintf('%d-03-01', $an);
        $dataNoua = sprintf('%d-01-01', $an);
        $profil = $this->profil($dataVeche);
        $drept = $this->drept($an, $dataVeche);
        [$serviciu, $salvate] = $this->serviciu($profil, [$drept], []);

        $rezultat = $serviciu->corecteaza(
            'angajat-1',
            $dataNoua,
            'Corectare data contractuala'
        );

        self::assertSame($dataVeche, $rezultat['dataVeche']);
        self::assertSame($dataNoua, $rezultat['dataNoua']);
        self::assertSame(1, $rezultat['drepturiActualizate']);
        self::assertSame($dataNoua, $profil->get('dataUrmatoareiAcordari'));
        self::assertSame($dataNoua, $drept->get('dataAcordare'));
        self::assertSame(21, $drept->get('zileAcordate'));

        $registru = array_values(array_filter(
            $salvate(),
            static fn (Entity $entitate): bool => $entitate->getEntityType() === 'RegistruConcediu'
        ));
        self::assertCount(1, $registru);
        self::assertSame('CorectieProgramAcordare', $registru[0]->get('tip'));
        self::assertStringContainsString($dataVeche . ' → ' . $dataNoua, $registru[0]->get('motiv'));
    }

    public function testBlocheazaModificareaCandDreptulViitorAreAlocareActiva(): void
    {
        $an = (int) date('Y') + 1;
        $dataVeche = sprintf('%d-03-01', $an);
        $dataNoua = sprintf('%d-01-01', $an);
        $profil = $this->profil($dataVeche);
        $drept = $this->drept($an, $dataVeche);
        $alocare = new BaseEntity('AlocareConcediu', ['attributes' => [
            'cerereId' => [], 'stare' => [],
        ]]);
        $alocare->set(['cerereId' => 'cerere-1', 'stare' => 'Rezervata']);
        [$serviciu] = $this->serviciu($profil, [$drept], [$alocare]);

        $previzualizare = $serviciu->previzualizeaza('angajat-1', $dataNoua);
        self::assertFalse($previzualizare['permis']);
        self::assertSame('programAcordareFolositDeCereri', $previzualizare['codBlocare']);
        self::assertSame('cerere-1', $previzualizare['cereriBlocante'][0]['id']);

        $this->expectException(Conflict::class);
        $this->expectExceptionMessage('programAcordareFolositDeCereri');

        try {
            $serviciu->corecteaza('angajat-1', $dataNoua, 'Corectare data contractuala');
        } finally {
            self::assertSame($dataVeche, $profil->get('dataUrmatoareiAcordari'));
            self::assertSame($dataVeche, $drept->get('dataAcordare'));
        }
    }

    public function testBlocheazaDataCareNuEsteInViitor(): void
    {
        $manager = $this->createStub(EntityManager::class);
        $utilizator = $this->createStub(User::class);
        $serviciu = new CorectorProgramAcordareConcediu(
            $manager,
            new DateTime(timeZone: 'UTC'),
            $utilizator,
        );

        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage('dataAcordareTrebuieViitoare');

        $serviciu->corecteaza('angajat-1', date('Y-m-d'), 'Data gresita');
    }

    private function profil(string $data): BaseEntity
    {
        $profil = new BaseEntity('ProfilConcediu', ['attributes' => [
            'id' => [], 'assignedUserId' => [], 'assignedUserName' => [], 'initializat' => [],
            'zileAnuale' => [], 'dataUrmatoareiAcordari' => [],
        ]]);
        $profil->set([
            'id' => 'profil-1',
            'assignedUserId' => 'angajat-1',
            'assignedUserName' => 'Angajat Test',
            'initializat' => true,
            'zileAnuale' => 21,
            'dataUrmatoareiAcordari' => $data,
        ]);

        return $profil;
    }

    private function drept(int $an, string $data): BaseEntity
    {
        $drept = new BaseEntity('DreptConcediu', ['attributes' => [
            'id' => [], 'anOrigine' => [], 'dataAcordare' => [], 'zileAcordate' => [],
            'zileRezervate' => [], 'zileConsumate' => [],
        ]]);
        $drept->set([
            'id' => 'drept-1',
            'anOrigine' => $an,
            'dataAcordare' => $data,
            'zileAcordate' => 21,
            'zileRezervate' => 0,
            'zileConsumate' => 0,
        ]);

        return $drept;
    }

    /**
     * @param list<Entity> $drepturi
     * @param list<Entity> $alocari
     * @return array{CorectorProgramAcordareConcediu, Closure(): list<Entity>}
     */
    private function serviciu(BaseEntity $profil, array $drepturi, array $alocari): array
    {
        $depozitProfil = $this->depozit([$profil], $profil);
        $depozitDrept = $this->depozit($drepturi);
        $depozitAlocare = $this->depozit($alocari);
        $tranzactie = $this->createStub(TransactionManager::class);
        $tranzactie->method('run')->willReturnCallback(
            static fn (Closure $operatie): mixed => $operatie()
        );
        $salvate = [];
        $manager = $this->createStub(EntityManager::class);
        $manager->method('getTransactionManager')->willReturn($tranzactie);
        $manager->method('getRDBRepository')->willReturnCallback(
            static fn (string $tip): RDBRepository => match ($tip) {
                'ProfilConcediu' => $depozitProfil,
                'DreptConcediu' => $depozitDrept,
                'AlocareConcediu' => $depozitAlocare,
            }
        );
        $manager->method('getNewEntity')->willReturnCallback(
            static fn (): BaseEntity => new BaseEntity('RegistruConcediu', ['attributes' => [
                'name' => [], 'profilId' => [], 'tip' => [], 'numarZile' => [], 'motiv' => [],
                'cheieOperatie' => [], 'dataEfectiva' => [], 'assignedUserId' => [], 'actorId' => [],
            ]])
        );
        $manager->method('saveEntity')->willReturnCallback(
            static function (Entity $entitate) use (&$salvate): Entity {
                $salvate[] = $entitate;
                return $entitate;
            }
        );
        $utilizator = $this->createStub(User::class);
        $utilizator->method('getId')->willReturn('manager-1');

        return [
            new CorectorProgramAcordareConcediu(
                $manager,
                new DateTime(timeZone: 'UTC'),
                $utilizator,
            ),
            static function () use (&$salvate): array {
                return $salvate;
            },
        ];
    }

    /** @param list<Entity> $entitati */
    private function depozit(array $entitati, ?Entity $una = null): RDBRepository
    {
        $selectie = $this->createStub(RDBSelectBuilder::class);
        $selectie->method('order')->willReturnSelf();
        $selectie->method('forUpdate')->willReturnSelf();
        $selectie->method('find')->willReturn(new EntityCollection($entitati));
        $selectie->method('findOne')->willReturn($una);
        $depozit = $this->createStub(RDBRepository::class);
        $depozit->method('where')->willReturn($selectie);

        return $depozit;
    }
}
