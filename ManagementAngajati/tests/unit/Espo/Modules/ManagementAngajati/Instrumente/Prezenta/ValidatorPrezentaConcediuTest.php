<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\HasBody;
use Espo\Core\Utils\DateTime;
use Espo\Modules\ManagementAngajati\Instrumente\Angajati\EligibilitateAngajat;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\ConcediiAprobate;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\CicluLunarCondica;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\ConfiguratiiCondicaAngajati;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\ReguliPrezenta;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\SarbatoriLegale;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\ValidatorPrezenta;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Tools\WorkingTime\CalendarFactory;
use Espo\Tools\WorkingTime\CalendarUtilityFactory;
use PHPUnit\Framework\TestCase;

class ValidatorPrezentaConcediuTest extends TestCase
{
    public function testBlocheazaActualizareaInZiuaAcoperitaDeConcediuAprobat(): void
    {
        $configuratii = $this->createStub(ConfiguratiiCondicaAngajati::class);
        $configuratii->method('esteAngajatInCic')->willReturn(false);
        $concedii = $this->createMock(ConcediiAprobate::class);
        $concedii->expects(self::once())->method('esteInConcediu')
            ->with('angajat-1', '2026-10-23')
            ->willReturn(true);
        $validator = new ValidatorPrezenta(
            $this->createStub(EntityManager::class),
            $this->createStub(CalendarFactory::class),
            $this->createStub(CalendarUtilityFactory::class),
            $this->createStub(DateTime::class),
            $this->createStub(ReguliPrezenta::class),
            $configuratii,
            $this->createStub(CicluLunarCondica::class),
            $this->createStub(SarbatoriLegale::class),
            $this->createStub(EligibilitateAngajat::class),
            $concedii,
        );
        $prezenta = $this->createStub(Entity::class);
        $prezenta->method('get')->willReturnCallback(
            static fn (string $camp): ?string => match ($camp) {
                'assignedUserId' => 'angajat-1',
                'dataPrezenta' => '2026-10-23',
                default => null,
            }
        );

        try {
            $validator->valideazaLaActualizare($prezenta);
            self::fail('Actualizarea trebuia blocata in concediul aprobat.');
        } catch (Conflict $exceptie) {
            $this->verificaMesaj($exceptie);
        }
    }

    private function verificaMesaj(HasBody $exceptie): void
    {
        $corp = $exceptie->getBody();
        self::assertNotNull($corp);
        $date = json_decode($corp, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('ziAcoperitaDeConcediu', $date['messageTranslation']['label']);
        self::assertSame('Prezenta', $date['messageTranslation']['scope']);
    }
}
