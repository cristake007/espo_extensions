<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use Espo\Core\Acl;
use Espo\Core\FileStorage\Manager as ManagerStocare;
use Espo\Entities\Attachment;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\DocumenteConcediu;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\GeneratorDocumentConcediu;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\InstantaneuDocumenteConcediu;
use Espo\ORM\BaseEntity;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use PHPUnit\Framework\TestCase;

class DocumenteConcediuTest extends TestCase
{
    public function testStergereaCereriiAnulateFolosesteAttachmentNativ(): void
    {
        $cerere = new BaseEntity('CerereConcediu', ['attributes' => ['id' => []]]);
        $cerere->set('id', 'cerere-1');
        $atasamentUnu = $this->createStub(Attachment::class);
        $atasamentDoi = $this->createStub(Attachment::class);
        $selectie = $this->createStub(RDBSelectBuilder::class);
        $selectie->method('find')->willReturn(
            new EntityCollection([$atasamentUnu, $atasamentDoi])
        );
        $depozit = $this->createMock(RDBRepository::class);
        $depozit->expects(self::once())->method('where')->with([
            'parentType' => 'CerereConcediu',
            'parentId' => 'cerere-1',
            'field' => 'documenteConcediu',
        ])->willReturn($selectie);
        $manager = $this->createMock(EntityManager::class);
        $manager->method('getRDBRepositoryByClass')->with(Attachment::class)
            ->willReturn($depozit);
        $manager->expects(self::exactly(2))->method('removeEntity');

        $serviciu = new DocumenteConcediu(
            $manager,
            $this->createStub(ManagerStocare::class),
            $this->createStub(Acl::class),
            $this->createStub(GeneratorDocumentConcediu::class),
            $this->createStub(InstantaneuDocumenteConcediu::class),
        );

        $serviciu->stergePentruCerere($cerere);
    }
}
