<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\CalculatorCompletitudineCondica;
use PHPUnit\Framework\TestCase;

class CalculatorCompletitudineCondicaTest extends TestCase
{
    public function testCalculeazaCompletitudineaSiPermiteInchidereaLuniiTrecute(): void
    {
        $angajati = [
            $this->angajat('angajat-1'),
            $this->angajat('angajat-2'),
        ];
        $zile = [
            $this->zi([
                $this->celula('angajat-1', false),
                $this->celula('angajat-2', false, cod: 'CIC'),
            ]),
        ];

        $rezultat = (new CalculatorCompletitudineCondica())->calculeaza(
            $angajati,
            $zile,
            2,
            true,
        );

        self::assertSame(2, $rezultat['zileScadente']);
        self::assertSame(2, $rezultat['zileCompletate']);
        self::assertSame(100, $rezultat['procentCompletare']);
        self::assertTrue($rezultat['esteCompleta']);
        self::assertTrue($rezultat['poateFiInchisa']);
        self::assertSame([], $rezultat['motiveBlocareInchidere']);
        self::assertSame(1, $angajati[0]['zileCompletate']);
        self::assertSame(1, $angajati[1]['zileCompletate']);
    }

    public function testExcludeZileleDinainteaAngajariiSiZileleNelucratoare(): void
    {
        $angajati = [$this->angajat('angajat-1')];
        $zile = [
            $this->zi([
                $this->celula('angajat-1', false, inainteDeAngajare: true),
            ]),
            $this->zi([
                $this->celula('angajat-1', false, esteZiLucratoare: false),
            ]),
            $this->zi([$this->celula('angajat-1', false)]),
        ];

        $rezultat = (new CalculatorCompletitudineCondica())->calculeaza(
            $angajati,
            $zile,
            1,
            true,
        );

        self::assertSame(1, $rezultat['zileScadente']);
        self::assertSame(1, $rezultat['zileCompletate']);
        self::assertTrue($rezultat['poateFiInchisa']);
    }

    public function testRaporteazaToateBlocajeleFaraAConfundaProcentulCuInchiderea(): void
    {
        $angajati = [
            $this->angajat('angajat-1'),
            $this->angajat('angajat-2', false),
        ];
        $zile = [
            $this->zi([
                $this->celula('angajat-1', true),
                $this->celula('angajat-2', true),
            ]),
            $this->zi([
                $this->celula('angajat-1', false, esteViitoare: true),
                $this->celula('angajat-2', false, esteViitoare: true),
            ]),
        ];

        $rezultat = (new CalculatorCompletitudineCondica())->calculeaza(
            $angajati,
            $zile,
            1,
            false,
        );

        self::assertSame(0, $rezultat['procentCompletare']);
        self::assertFalse($rezultat['esteCompleta']);
        self::assertFalse($rezultat['poateFiInchisa']);
        self::assertSame([
            CalculatorCompletitudineCondica::MOTIV_LUNA_NEINCHEIATA,
            CalculatorCompletitudineCondica::MOTIV_CALENDARE_LIPSA,
            CalculatorCompletitudineCondica::MOTIV_PROFILURI_LIPSA,
            CalculatorCompletitudineCondica::MOTIV_PREZENTE_LIPSA,
            CalculatorCompletitudineCondica::MOTIV_ZILE_VIITOARE,
        ], $rezultat['motiveBlocareInchidere']);
    }

    public function testNuPermiteInghetareaUneiLuniGoale(): void
    {
        $angajati = [$this->angajat('angajat-1')];

        $rezultat = (new CalculatorCompletitudineCondica())->calculeaza(
            $angajati,
            [],
            1,
            true,
        );

        self::assertFalse($rezultat['esteCompleta']);
        self::assertFalse($rezultat['poateFiInchisa']);
        self::assertSame(
            [CalculatorCompletitudineCondica::MOTIV_FARA_ZILE_SCADENTE],
            $rezultat['motiveBlocareInchidere']
        );
    }

    /** @return array<string, mixed> */
    private function angajat(string $id, bool $areDataAngajare = true): array
    {
        return [
            'idAngajat' => $id,
            'areDataAngajare' => $areDataAngajare,
        ];
    }

    /** @param list<array<string, mixed>> $celule */
    private function zi(array $celule): array
    {
        return ['celule' => $celule];
    }

    /** @return array<string, mixed> */
    private function celula(
        string $idAngajat,
        bool $esteLipsa,
        ?string $cod = 'P',
        bool $inainteDeAngajare = false,
        bool $esteZiLucratoare = true,
        bool $esteViitoare = false,
    ): array {
        return [
            'idAngajat' => $idAngajat,
            'codPrezenta' => $cod,
            'esteZiLucratoare' => $esteZiLucratoare,
            'areDataAngajare' => true,
            'esteInainteDeAngajare' => $inainteDeAngajare,
            'esteViitoare' => $esteViitoare,
            'esteLipsa' => $esteLipsa,
        ];
    }
}
