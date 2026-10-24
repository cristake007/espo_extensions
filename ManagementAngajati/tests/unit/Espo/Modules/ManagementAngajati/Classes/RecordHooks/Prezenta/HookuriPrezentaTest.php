<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Classes\RecordHooks\Prezenta;

use Espo\Core\Record\CreateParams;
use Espo\Core\Record\UpdateParams;
use Espo\Modules\ManagementAngajati\Classes\RecordHooks\Prezenta\InainteDeActualizare;
use Espo\Modules\ManagementAngajati\Classes\RecordHooks\Prezenta\InainteDeCreare;
use Espo\Modules\ManagementAngajati\Classes\RecordHooks\Prezenta\StabilesteNumele;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\DenumirePrezenta;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\ValidatorPrezenta;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HookuriPrezentaTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function furnizorNume(): array
    {
        return [
            'nume afisat disponibil' => ['Ana Popescu', 'Ana Popescu · 2026-10-20'],
            'foloseste id ca rezerva' => ['', 'user-1 · 2026-10-20'],
        ];
    }

    #[DataProvider('furnizorNume')]
    public function testHookulTimpuriuCompleteazaNumeleTehnicObligatoriu(
        string $numeAngajat,
        string $numeAsteptat,
    ): void {
        $inregistrare = $this->createMock(Entity::class);
        $inregistrare
            ->method('get')
            ->willReturnMap([
                ['assignedUserName', $numeAngajat],
                ['assignedUserId', 'user-1'],
                ['dataPrezenta', '2026-10-20'],
            ]);
        $inregistrare
            ->expects(self::once())
            ->method('set')
            ->with('name', $numeAsteptat);

        $managerEntitati = $this->createStub(EntityManager::class);
        $managerEntitati->method('getEntityById')->willReturn(null);

        (new StabilesteNumele(new DenumirePrezenta($managerEntitati)))
            ->process($inregistrare, new CreateParams());
    }

    public function testHookulDeCreareDeleagaValidatoruluiBusiness(): void
    {
        $inregistrare = $this->createStub(Entity::class);
        $validatorPrezenta = $this->createMock(ValidatorPrezenta::class);
        $validatorPrezenta
            ->expects(self::once())
            ->method('valideazaLaCreare')
            ->with($inregistrare);

        (new InainteDeCreare($validatorPrezenta))->process($inregistrare, new CreateParams());
    }

    public function testHookulDeActualizareDeleagaValidatoruluiCicluluiLunar(): void
    {
        $inregistrare = $this->createStub(Entity::class);
        $validatorPrezenta = $this->createMock(ValidatorPrezenta::class);
        $validatorPrezenta
            ->expects(self::once())
            ->method('valideazaLaActualizare')
            ->with($inregistrare);

        (new InainteDeActualizare($validatorPrezenta))->process(
            $inregistrare,
            UpdateParams::create()
        );
    }
}
