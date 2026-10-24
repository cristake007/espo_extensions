<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\HasBody;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\ReguliPrezenta;
use PHPUnit\Framework\TestCase;

class ReguliPrezentaTest extends TestCase
{
    private ReguliPrezenta $reguliPrezenta;

    protected function setUp(): void
    {
        $this->reguliPrezenta = new ReguliPrezenta();
    }

    public function testRespingeDataViitoareCuMesajTraductibil(): void
    {
        try {
            $this->reguliPrezenta->valideazaDataPrezenta('2026-10-23', '2026-10-22');
            self::fail('Era așteptată o excepție BadRequest.');
        } catch (BadRequest $exceptie) {
            $this->verificaMesajTraductibil($exceptie, 'dataPrezentaViitoare');
        }
    }

    public function testRespingePrezentaDinainteaAngajarii(): void
    {
        try {
            $this->reguliPrezenta->valideazaDataAngajare('2026-10-15', '2026-10-14');
            self::fail('Era așteptată o excepție BadRequest.');
        } catch (BadRequest $exceptie) {
            $this->verificaMesajTraductibil($exceptie, 'dataAnterioaraAngajarii');
        }
    }

    public function testAcceptaPrezentaChiarDinDataAngajarii(): void
    {
        $this->reguliPrezenta->valideazaDataAngajare('2026-10-15', '2026-10-15');

        self::addToAssertionCount(1);
    }

    public function testRespingePrezentaCandProfilulNuAreDataAngajarii(): void
    {
        $this->expectException(BadRequest::class);

        $this->reguliPrezenta->valideazaDataAngajare(null, '2026-10-15');
    }

    public function testRespingeZiuaNelucratoareCuMesajTraductibil(): void
    {
        try {
            $this->reguliPrezenta->valideazaEligibilitatea(false, false, false);
            self::fail('Era așteptată o excepție BadRequest.');
        } catch (BadRequest $exceptie) {
            $this->verificaMesajTraductibil($exceptie, 'ziNelucratoare');
        }
    }

    public function testRespingeSarbatoareaLegalaCuMesajTraductibil(): void
    {
        try {
            $this->reguliPrezenta->valideazaEligibilitatea(true, true, false);
            self::fail('Era așteptată o excepție BadRequest.');
        } catch (BadRequest $exceptie) {
            $this->verificaMesajTraductibil($exceptie, 'ziSarbatoareLegala');
        }
    }

    public function testRespingeDuplicatulCuMesajTraductibil(): void
    {
        try {
            $this->reguliPrezenta->valideazaEligibilitatea(true, false, true);
            self::fail('Era așteptată o excepție Conflict.');
        } catch (Conflict $exceptie) {
            $this->verificaMesajTraductibil($exceptie, 'prezentaDuplicata');
        }
    }

    private function verificaMesajTraductibil(HasBody $exceptie, string $eticheta): void
    {
        $corp = $exceptie->getBody();

        self::assertNotNull($corp);

        $date = json_decode($corp, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame($eticheta, $date['messageTranslation']['label']);
        self::assertSame('Prezenta', $date['messageTranslation']['scope']);
    }
}
