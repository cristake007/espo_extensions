<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use Espo\Modules\ManagementAngajati\Instrumente\Concedii\ConcediiAprobate;
use Espo\ORM\BaseEntity;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use PHPUnit\Framework\TestCase;

class ConcediiAprobateTest extends TestCase
{
    public function testProiecteazaDoarIntersectiaCereriiCuLunaSolicitata(): void
    {
        $cerere = new BaseEntity('CerereConcediu', ['attributes' => [
            'id' => [], 'dataInceput' => [], 'dataSfarsit' => [],
        ]]);
        $cerere->set([
            'id' => 'cerere-1',
            'dataInceput' => '2026-12-29',
            'dataSfarsit' => '2027-01-08',
        ]);
        $selectie = $this->createStub(RDBSelectBuilder::class);
        $selectie->method('find')->willReturn(new EntityCollection([$cerere]));
        $depozit = $this->createMock(RDBRepository::class);
        $depozit->expects(self::once())->method('where')->with([
            'assignedUserId' => 'angajat-1',
            'stare' => ['Aprobata', 'AnulareSolicitata'],
            'dataInceput<=' => '2027-01-31',
            'dataSfarsit>=' => '2027-01-01',
        ])->willReturn($selectie);
        $manager = $this->createStub(EntityManager::class);
        $manager->method('getRDBRepository')->willReturn($depozit);

        $rezultat = (new ConcediiAprobate($manager))->obtinePentruAngajatIntre(
            'angajat-1',
            '2027-01-01',
            '2027-01-31'
        );

        self::assertSame([
            '2027-01-01',
            '2027-01-02',
            '2027-01-03',
            '2027-01-04',
            '2027-01-05',
            '2027-01-06',
            '2027-01-07',
            '2027-01-08',
        ], array_keys($rezultat));
        self::assertSame(['cerere-1'], array_values(array_unique($rezultat)));
    }
}
