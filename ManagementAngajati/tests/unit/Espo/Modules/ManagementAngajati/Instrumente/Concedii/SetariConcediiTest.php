<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use Espo\Core\AclManager;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\SetariConcedii;
use Espo\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

class SetariConcediiTest extends TestCase
{
    public function testAcordareaAnualaAutomataEsteActivaImplicit(): void
    {
        $configuratie = $this->createStub(Config::class);
        $configuratie->method('get')->willReturnCallback(
            static fn (string $cheie, mixed $implicit = null): mixed => $implicit
        );

        self::assertTrue($this->creeazaServiciu($configuratie)
            ->esteAcordareaAnualaAutomataActiva());
    }

    public function testAcordareaAnualaAutomataRespectaDezactivareaSalvata(): void
    {
        $configuratie = $this->createStub(Config::class);
        $configuratie->method('get')->willReturnCallback(
            static fn (string $cheie, mixed $implicit = null): mixed =>
                $cheie === SetariConcedii::CHEIE_ACORDARE_ANUALA_AUTOMATA ? false : $implicit
        );

        self::assertFalse($this->creeazaServiciu($configuratie)
            ->esteAcordareaAnualaAutomataActiva());
    }

    private function creeazaServiciu(Config $configuratie): SetariConcedii
    {
        return new SetariConcedii(
            $configuratie,
            $this->createStub(ConfigWriter::class),
            $this->createStub(EntityManager::class),
            $this->createStub(AclManager::class),
        );
    }
}
