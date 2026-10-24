<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\CalculatorZileConcediu;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\InstantaneuDocumenteConcediu;
use Espo\ORM\BaseEntity;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use PHPUnit\Framework\TestCase;

class InstantaneuDocumenteConcediuTest extends TestCase
{
    public function testSeparareaPeAniPastreazaReportulSiSoldurileDeLaAprobare(): void
    {
        $angajat = $this->utilizator('angajat-1', 'Ana', 'Popescu');
        $managerUnu = $this->utilizator('manager-1', 'Mihai', 'Unu');
        $managerDoi = $this->utilizator('manager-2', 'Maria', 'Doi');
        $cerere = new BaseEntity('CerereConcediu', ['attributes' => [
            'id' => [], 'assignedUserId' => [], 'dataInceput' => [], 'dataSfarsit' => [],
            'aprobatoriNecesari' => [],
        ]]);
        $cerere->set([
            'id' => 'cerere-1',
            'assignedUserId' => 'angajat-1',
            'dataInceput' => '2026-12-23',
            'dataSfarsit' => '2027-01-06',
            'aprobatoriNecesari' => ['manager-1', 'manager-2'],
        ]);
        $drept2026 = $this->drept('drept-2026', 2026, '2026-01-01', 21, 12, 8);
        $drept2027 = $this->drept('drept-2027', 2027, '2027-01-01', 21, 0, 0);
        $alocare = new BaseEntity('AlocareConcediu', ['attributes' => [
            'dreptId' => [], 'numarZile' => [],
        ]]);
        $alocare->set(['dreptId' => 'drept-2026', 'numarZile' => 8]);

        $depozitAlocari = $this->depozit([$alocare]);
        $depozitDrepturi = $this->depozit([$drept2026, $drept2027]);
        $manager = $this->createStub(EntityManager::class);
        $manager->method('getEntityById')->willReturnCallback(
            static fn (string $tip, string $id): ?User => match ($id) {
                'angajat-1' => $angajat,
                'manager-1' => $managerUnu,
                'manager-2' => $managerDoi,
                default => null,
            }
        );
        $manager->method('getRDBRepository')->willReturnCallback(
            static fn (string $tip): RDBRepository => match ($tip) {
                'AlocareConcediu' => $depozitAlocari,
                'DreptConcediu' => $depozitDrepturi,
            }
        );
        $calculator = $this->createStub(CalculatorZileConcediu::class);
        $calculator->method('calculeaza')->willReturn([
            'total' => 8,
            'peAni' => [2026 => 5, 2027 => 3],
            'date' => [
                '2026-12-23', '2026-12-24', '2026-12-28', '2026-12-29', '2026-12-30',
                '2027-01-04', '2027-01-05', '2027-01-06',
            ],
        ]);

        $instantaneu = (new InstantaneuDocumenteConcediu($manager, $calculator))
            ->creeaza($cerere);

        self::assertSame(['Mihai Unu', 'Maria Doi'], $instantaneu['aprobatori']);
        self::assertMatchesRegularExpression('/^[A-F0-9]{32}$/', $instantaneu['codVerificare']);
        self::assertCount(2, $instantaneu['segmente']);
        self::assertSame([
            'luna' => '2026-12',
            'dataInceput' => '2026-12-23',
            'dataSfarsit' => '2026-12-30',
            'an' => 2026,
            'numarLuna' => 12,
            'zileSolicitate' => 5,
            'zileAnuale' => 21,
            'zileConsumate' => 12,
            'zileReportate' => 0,
            'soldInainte' => 9,
            'soldDupa' => 4,
        ], $instantaneu['segmente'][0]);
        self::assertSame(4, $instantaneu['segmente'][1]['zileReportate']);
        self::assertSame(25, $instantaneu['segmente'][1]['soldInainte']);
        self::assertSame(22, $instantaneu['segmente'][1]['soldDupa']);
        self::assertSame(0, $instantaneu['segmente'][1]['zileConsumate']);
        self::assertSame(21, $instantaneu['segmente'][1]['zileAnuale']);

        $drept2027->set('dataAcordare', '2027-03-12');
        $inainteDeAcordare = (new InstantaneuDocumenteConcediu($manager, $calculator))
            ->creeaza($cerere);

        self::assertSame(0, $inainteDeAcordare['segmente'][1]['zileAnuale']);
        self::assertSame(4, $inainteDeAcordare['segmente'][1]['zileReportate']);
        self::assertSame(4, $inainteDeAcordare['segmente'][1]['soldInainte']);
        self::assertSame(1, $inainteDeAcordare['segmente'][1]['soldDupa']);

        $instantaneuVechi = $instantaneu;
        $instantaneuVechi['versiune'] = 2;
        $instantaneuVechi['segmente'][1]['zileAnuale'] = 21;
        $instantaneuVechi['segmente'][1]['zileReportate'] = 16;
        $instantaneuVechi['segmente'][1]['soldInainte'] = 16;
        $instantaneuVechi['segmente'][1]['soldDupa'] = 13;
        $actualizat = (new InstantaneuDocumenteConcediu($manager, $calculator))
            ->pregatesteInstantaneuVechiPentruDocument($instantaneuVechi);

        self::assertSame(3, $actualizat['versiune']);
        self::assertSame(21, $actualizat['segmente'][0]['zileAnuale']);
        self::assertSame(0, $actualizat['segmente'][1]['zileAnuale']);
        self::assertSame(16, $actualizat['segmente'][1]['soldInainte']);
        self::assertSame(13, $actualizat['segmente'][1]['soldDupa']);
    }

    private function utilizator(string $id, string $prenume, string $nume): User
    {
        $utilizator = $this->createStub(User::class);
        $utilizator->method('getId')->willReturn($id);
        $utilizator->method('getName')->willReturn($prenume . ' ' . $nume);
        $utilizator->method('get')->willReturnCallback(
            static fn (string $camp): mixed => match ($camp) {
                'firstName' => $prenume,
                'lastName' => $nume,
                'name' => $prenume . ' ' . $nume,
                'userName' => $id,
                default => null,
            }
        );
        return $utilizator;
    }

    private function drept(
        string $id,
        int $an,
        string $data,
        int $acordate,
        int $consumate,
        int $rezervate
    ): BaseEntity {
        $drept = new BaseEntity('DreptConcediu', ['attributes' => [
            'id' => [], 'anOrigine' => [], 'dataAcordare' => [], 'zileAcordate' => [],
            'zileConsumate' => [], 'zileRezervate' => [],
        ]]);
        $drept->set([
            'id' => $id,
            'anOrigine' => $an,
            'dataAcordare' => $data,
            'zileAcordate' => $acordate,
            'zileConsumate' => $consumate,
            'zileRezervate' => $rezervate,
        ]);
        return $drept;
    }

    /** @param list<BaseEntity> $entitati */
    private function depozit(array $entitati): RDBRepository
    {
        $selectie = $this->createStub(RDBSelectBuilder::class);
        $selectie->method('where')->willReturnSelf();
        $selectie->method('order')->willReturnSelf();
        $selectie->method('find')->willReturn(new EntityCollection($entitati));
        $depozit = $this->createStub(RDBRepository::class);
        $depozit->method('where')->willReturn($selectie);
        return $depozit;
    }
}
