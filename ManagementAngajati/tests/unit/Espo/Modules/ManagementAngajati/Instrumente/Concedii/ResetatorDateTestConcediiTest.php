<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use Closure;
use Espo\Entities\Attachment;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\ResetatorDateTestConcedii;
use Espo\ORM\BaseEntity;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use Espo\ORM\TransactionManager;
use PHPUnit\Framework\TestCase;

class ResetatorDateTestConcediiTest extends TestCase
{
    public function testStergeDateleContabileInOrdineaDependintelor(): void
    {
        $tipuri = [
            'RegistruConcediu',
            'AlocareConcediu',
            'CerereConcediu',
            'DreptConcediu',
            'ProfilConcediu',
        ];
        $depozite = [];

        foreach ($tipuri as $index => $tip) {
            $entitate = new BaseEntity($tip, ['attributes' => ['id' => []]]);
            $entitate->set('id', 'id-' . $index);
            $selectie = $this->createStub(RDBSelectBuilder::class);
            $selectie->method('order')->willReturnSelf();
            $selectie->method('forUpdate')->willReturnSelf();
            $selectie->method('find')->willReturn(new EntityCollection([$entitate]));
            $depozit = $this->createMock(RDBRepository::class);
            $depozit->method('count')->willReturn(1);
            $depozit->method('order')->willReturn($selectie);
            $depozit->expects(self::once())->method('deleteFromDb')->with('id-' . $index, true);
            $depozite[$tip] = $depozit;
        }

        $atasament = $this->createStub(Attachment::class);
        $atasament->method('getId')->willReturn('document-1');
        $selectieAtasamente = $this->createStub(RDBSelectBuilder::class);
        $selectieAtasamente->method('order')->willReturnSelf();
        $selectieAtasamente->method('forUpdate')->willReturnSelf();
        $selectieAtasamente->method('find')->willReturn(new EntityCollection([$atasament]));
        $depozitAtasamente = $this->createMock(RDBRepository::class);
        $depozitAtasamente->method('where')->with([
            'parentType' => 'CerereConcediu',
            'field' => 'documenteConcediu',
        ])->willReturn($selectieAtasamente);
        $depozitAtasamente->expects(self::once())
            ->method('deleteFromDb')->with('document-1', true);

        $tranzactie = $this->createMock(TransactionManager::class);
        $tranzactie->expects(self::once())->method('run')
            ->willReturnCallback(static fn (Closure $operatie): mixed => $operatie());
        $manager = $this->createMock(EntityManager::class);
        $manager->method('getTransactionManager')->willReturn($tranzactie);
        $manager->method('getRDBRepository')->willReturnCallback(
            static fn (string $tip): RDBRepository => $depozite[$tip]
        );
        $manager->method('getRDBRepositoryByClass')->with(Attachment::class)
            ->willReturn($depozitAtasamente);
        $manager->expects(self::exactly(6))->method('removeEntity');

        $rezultat = (new ResetatorDateTestConcedii($manager))->reseteaza();

        self::assertSame(1, $rezultat['sterse']['profiluri']);
        self::assertSame(1, $rezultat['sterse']['drepturi']);
        self::assertSame(1, $rezultat['sterse']['cereri']);
        self::assertSame(1, $rezultat['sterse']['alocari']);
        self::assertSame(1, $rezultat['sterse']['registru']);
    }
}
