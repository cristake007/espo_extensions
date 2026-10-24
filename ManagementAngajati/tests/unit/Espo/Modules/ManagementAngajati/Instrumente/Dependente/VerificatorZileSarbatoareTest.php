<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Dependente;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Metadata;
use Espo\Entities\Extension;
use Espo\Modules\ManagementAngajati\Instrumente\Dependente\VerificatorZileSarbatoare;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use PHPUnit\Framework\TestCase;

class VerificatorZileSarbatoareTest extends TestCase
{
    public function testDependentaAbsentaNuEsteConsiderataCompatibila(): void
    {
        self::assertFalse($this->creeazaVerificator(null, true)->esteInstalataCompatibil());
    }

    public function testVersiuneaMaiVecheNuEsteConsiderataCompatibila(): void
    {
        self::assertFalse($this->creeazaVerificator('0.9.4', true)->esteInstalataCompatibil());
    }

    public function testVersiuneaMinimaCuStructuraNecesaraEsteCompatibila(): void
    {
        self::assertTrue($this->creeazaVerificator('0.9.5', true)->esteInstalataCompatibil());
        self::assertTrue($this->creeazaVerificator('1.0.0', true)->esteInstalataCompatibil());
    }

    public function testExtensiaFaraStructuraNecesaraNuEsteCompatibila(): void
    {
        self::assertFalse($this->creeazaVerificator('0.9.5', false)->esteInstalataCompatibil());
    }

    public function testVersiuneaPreaVecheBlocheazaOperatiileDePrezenta(): void
    {
        $this->expectException(BadRequest::class);

        $this->creeazaVerificator('0.9.4', true)->verificaDisponibilitateSauArunca();
    }

    public function testLipsaStructuriiBlocheazaOperatiileDePrezenta(): void
    {
        $this->expectException(BadRequest::class);

        $this->creeazaVerificator(null, false)->verificaDisponibilitateSauArunca();
    }

    private function creeazaVerificator(
        ?string $versiune,
        bool $structuraDisponibila,
    ): VerificatorZileSarbatoare {
        $extensie = null;

        if ($versiune !== null) {
            $extensie = $this->createStub(Extension::class);
            $extensie->method('getVersion')->willReturn($versiune);
        }

        $selector = $this->createStub(RDBSelectBuilder::class);
        $selector->method('findOne')->willReturn($extensie);
        $depozit = $this->createStub(RDBRepository::class);
        $depozit->method('where')->willReturn($selector);
        $managerEntitati = $this->createStub(EntityManager::class);
        $managerEntitati->method('getRDBRepositoryByClass')->willReturn($depozit);
        $metadate = $this->createStub(Metadata::class);
        $metadate->method('get')->willReturn($structuraDisponibila ? ['type' => 'date'] : null);

        return new VerificatorZileSarbatoare($managerEntitati, $metadate);
    }
}
