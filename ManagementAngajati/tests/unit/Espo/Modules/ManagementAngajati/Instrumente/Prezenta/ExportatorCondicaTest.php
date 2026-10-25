<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Language\LanguageFactory;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\CondicaPrezentaLunara;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\ExportatorCondica;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\FurnizorLogoCondica;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\GeneratorExportCondica;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\TestCase;

class ExportatorCondicaTest extends TestCase
{
    public function testRefuzaExportulUneiLuniDeschise(): void
    {
        $condica = $this->createStub(CondicaPrezentaLunara::class);
        $condica->method('obtine')->willReturn([
            'cicluLunar' => [
                'esteInchisa' => false,
                'permiteExportCondicaIncompleta' => false,
            ],
            'statistici' => ['zileLipsa' => 0],
        ]);
        $exportator = new ExportatorCondica(
            $condica,
            $this->createStub(GeneratorExportCondica::class)
        );

        $this->expectException(Conflict::class);
        $exportator->exporta('2026-09', 'xlsx');
    }

    public function testPermiteExportulUneiLuniDeschiseSiIncompleteCandRegulaEsteActiva(): void
    {
        $date = [
            'cicluLunar' => [
                'esteInchisa' => false,
                'permiteExportCondicaIncompleta' => true,
            ],
            'statistici' => ['zileLipsa' => 7],
        ];
        $condica = $this->createStub(CondicaPrezentaLunara::class);
        $condica->method('obtine')->willReturn($date);
        $generator = $this->createMock(GeneratorExportCondica::class);
        $generator
            ->expects(self::once())
            ->method('genereazaXlsx')
            ->with($date, [])
            ->willReturn([
                'nume' => 'condica-incompleta.xlsx',
                'continut' => 'continut',
                'tip' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);

        $rezultat = (new ExportatorCondica($condica, $generator))
            ->exporta('2026-09', 'xlsx');

        self::assertSame('condica-incompleta.xlsx', $rezultat['nume']);
    }

    public function testGenereazaXlsxSiPdfDinReviziaInghetata(): void
    {
        $furnizorLogo = $this->createStub(FurnizorLogoCondica::class);
        $furnizorLogo->method('obtineDataUri')->willReturn(
            'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAAB' .
            'CAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        );
        $fabricaLimbi = $this->createMock(LanguageFactory::class);
        $fabricaLimbi
            ->expects(self::once())
            ->method('create')
            ->with('ro_RO')
            ->willReturn($this->creeazaLimba());
        $generator = new GeneratorExportCondica($fabricaLimbi, $furnizorLogo);
        $condica = $this->creeazaCondica();
        $culori = [
            'principala' => '#164194',
            'accent' => '#D41131',
            'text' => '#304253',
            'contur' => '#CFD7DF',
            'prezent' => '#58C97A',
            'delegatie' => '#EDC555',
            'medical' => '#D41131',
            'cic' => '#6FA8D6',
        ];
        $xlsx = $generator->genereazaXlsx($condica, $culori);
        $pdf = $generator->genereazaPdf($condica, $culori);
        $htmlPdf = $generator->pregatesteHtmlPdf($condica, $culori);

        self::assertSame('condica-prezenta_2026-09_revizia-2.xlsx', $xlsx['nume']);
        self::assertSame('PK', substr($xlsx['continut'], 0, 2));
        self::assertSame('condica-prezenta_2026-09_revizia-2.pdf', $pdf['nume']);
        self::assertSame('%PDF', substr($pdf['continut'], 0, 4));
        self::assertStringContainsString('<table style="width:104mm">', $htmlPdf);
        self::assertStringContainsString('<col style="width:20mm"><col style="width:24mm">', $htmlPdf);
        self::assertStringContainsString('<col style="width:9mm"><col style="width:21mm">', $htmlPdf);
        self::assertStringContainsString('data:image/png;base64,', $htmlPdf);
        self::assertStringContainsString('background:#164194', $htmlPdf);
        self::assertStringContainsString('Ora intrare', $htmlPdf);
        self::assertStringContainsString('Ora ieșire', $htmlPdf);
        self::assertStringContainsString(
            'Ana Test<span class="detaliu-angajat">Remote</span>',
            $htmlPdf
        );
        self::assertStringContainsString(
            'Maria Test<span class="detaliu-angajat">Remote</span>',
            $htmlPdf
        );
        self::assertSame(2, substr_count($htmlPdf, '>Remote<'));
        self::assertStringContainsString('Prezent · Remote', $htmlPdf);
        self::assertStringContainsString('rowspan="2"', $htmlPdf);
        self::assertStringContainsString('@page{size:A4 portrait;margin:18px}', $htmlPdf);
        self::assertStringContainsString('section{page-break-inside:avoid}', $htmlPdf);
        self::assertStringContainsString('font-size:7px', $htmlPdf);
        self::assertStringNotContainsString('border-bottom:', $htmlPdf);
        self::assertStringNotContainsString('table{width:100%', $htmlPdf);
        self::assertStringContainsString('table{border-collapse:collapse;table-layout:fixed;margin:0 auto}', $htmlPdf);

        $cale = tempnam(sys_get_temp_dir(), 'condica-xlsx-');
        self::assertIsString($cale);

        try {
            file_put_contents($cale, $xlsx['continut']);
            $foaie = IOFactory::load($cale)->getActiveSheet();
            self::assertSame("Ana Test\nRemote", $foaie->getCell('B3')->getValue());
            self::assertSame("Maria Test\nRemote", $foaie->getCell('D3')->getValue());
            self::assertSame('8', (string) $foaie->getCell('B5')->getValue());
            self::assertSame('Prezent · Remote', $foaie->getCell('C5')->getValue());
            self::assertSame('CIC', $foaie->getCell('E5')->getValue());
        } finally {
            @unlink($cale);
        }
    }

    public function testPdfPastreazaLunaCompletaCuCinciAngajatiPeOPaginaA4Portret(): void
    {
        $furnizorLogo = $this->createStub(FurnizorLogoCondica::class);
        $fabricaLimbi = $this->createStub(LanguageFactory::class);
        $fabricaLimbi->method('create')->willReturn($this->creeazaLimba());
        $generator = new GeneratorExportCondica($fabricaLimbi, $furnizorLogo);
        $condica = $this->creeazaCondica();
        $condica['angajati'] = [];

        for ($indiceAngajat = 1; $indiceAngajat <= 5; $indiceAngajat++) {
            $condica['angajati'][] = [
                'idAngajat' => 'a' . $indiceAngajat,
                'numeAngajat' => 'Angajat ' . $indiceAngajat,
            ];
        }

        $condica['zile'] = [];

        for ($zi = 1; $zi <= 23; $zi++) {
            $celule = [];

            for ($indiceAngajat = 1; $indiceAngajat <= 5; $indiceAngajat++) {
                $celule[] = [
                    'idAngajat' => 'a' . $indiceAngajat,
                    'codPrezenta' => 'P',
                    'orePlanificate' => 8.0,
                    'oraIntrare' => '09:00',
                    'oraIesire' => '17:00',
                    'esteRemote' => false,
                ];
            }

            $condica['zile'][] = [
                'data' => sprintf('2026-09-%02d', $zi),
                'celule' => $celule,
            ];
        }

        $pdf = $generator->genereazaPdf($condica);
        $numarPagini = preg_match_all('/\/Type\s*\/Page\b/', $pdf['continut']);

        self::assertSame(1, $numarPagini);
    }

    public function testPdfNuDesparteColoaneleAngajatilorIntrePagini(): void
    {
        $furnizorLogo = $this->createStub(FurnizorLogoCondica::class);
        $fabricaLimbi = $this->createStub(LanguageFactory::class);
        $fabricaLimbi->method('create')->willReturn($this->creeazaLimba());
        $generator = new GeneratorExportCondica($fabricaLimbi, $furnizorLogo);
        $condica = $this->creeazaCondica();
        $condica['angajati'] = [];
        $celule = [];

        for ($indiceAngajat = 1; $indiceAngajat <= 20; $indiceAngajat++) {
            $condica['angajati'][] = [
                'idAngajat' => 'a' . $indiceAngajat,
                'numeAngajat' => 'Angajat ' . $indiceAngajat,
            ];
            $celule[] = [
                'idAngajat' => 'a' . $indiceAngajat,
                'codPrezenta' => 'P',
                'orePlanificate' => 8.0,
                'oraIntrare' => '09:00',
                'oraIesire' => '17:00',
                'esteRemote' => false,
            ];
        }

        $condica['zile'] = [];

        for ($zi = 1; $zi <= 23; $zi++) {
            $condica['zile'][] = [
                'data' => sprintf('2026-09-%02d', $zi),
                'celule' => $celule,
            ];
        }

        $pdf = $generator->genereazaPdf($condica);
        $numarPagini = preg_match_all('/\/Type\s*\/Page\b/', $pdf['continut']);

        self::assertSame(4, $numarPagini);
    }

    private function creeazaLimba(): Language
    {
        $limba = $this->createStub(Language::class);
        $limba->method('translateLabel')->willReturnCallback(
            static fn (string $eticheta): string => match ($eticheta) {
                'foaieCondica' => 'Condica',
                'titluExport' => 'Condica de prezenta',
                'revizieExport' => 'Revizia',
                'dataExport' => 'Data',
                'programExport' => 'Program',
                'oraIntrareExport' => 'Ora intrare',
                'oraIesireExport' => 'Ora ieșire',
                'oreExport' => 'Ore',
                'prezentaExport' => 'Prezenta',
                'remote' => 'Remote',
                '09' => 'Septembrie',
                default => $eticheta,
            }
        );
        $limba->method('translateOption')->willReturnCallback(
            static fn (string $valoare): string => match ($valoare) {
                'P' => 'Prezent',
                default => $valoare,
            }
        );

        return $limba;
    }

    /** @return array<string, mixed> */
    private function creeazaCondica(): array
    {
        return [
            'luna' => '2026-09',
            'cicluLunar' => ['esteInchisa' => true, 'revizie' => 2],
            'statistici' => ['zileLipsa' => 0],
            'angajati' => [
                ['idAngajat' => 'a1', 'numeAngajat' => 'Ana Test', 'esteRemote' => true],
                ['idAngajat' => 'a2', 'numeAngajat' => 'Maria Test', 'esteRemote' => true],
            ],
            'zile' => [[
                'data' => '2026-09-01',
                'celule' => [
                    [
                        'idAngajat' => 'a1',
                        'codPrezenta' => 'P',
                        'orePlanificate' => 8.0,
                        'oraIntrare' => '09:00',
                        'oraIesire' => '17:00',
                        'esteRemote' => true,
                    ],
                    [
                        'idAngajat' => 'a2',
                        'codPrezenta' => 'CIC',
                        'orePlanificate' => 6.0,
                        'oraIntrare' => '10:00',
                        'oraIesire' => '16:00',
                        'esteRemote' => true,
                    ],
                ],
            ]],
        ];
    }
}
