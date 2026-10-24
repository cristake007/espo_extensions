<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Dompdf\Dompdf;
use Dompdf\Options;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Language\LanguageFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

/** Reda proiectia inghetata a condicii in formatele de document acceptate. */
class GeneratorExportCondica
{
    /** Limita pastreaza aproximativ 20 de angajati in maximum patru pagini A4 portret. */
    private const int ANGAJATI_PE_PAGINA_PDF = 5;
    private Language $limba;

    public function __construct(
        LanguageFactory $fabricaLimbi,
        private FurnizorLogoCondica $furnizorLogo,
    ) {
        $this->limba = $fabricaLimbi->create('ro_RO');
    }

    /**
     * @param array<string, mixed> $condica
     * @param array<string, string> $culori
     * @return array{nume: string, continut: string, tip: string}
     */
    public function genereazaXlsx(array $condica, array $culori = []): array
    {
        $registru = new Spreadsheet();
        $foaie = $registru->getActiveSheet();
        $foaie->setTitle($this->limba->translateLabel('foaieCondica', 'labels', 'CondicaPrezenta'));
        $angajati = is_array($condica['angajati'] ?? null) ? $condica['angajati'] : [];
        $zile = is_array($condica['zile'] ?? null) ? $condica['zile'] : [];
        $remotePeAngajati = $this->obtineRemotePeAngajati($angajati, $zile);
        $ultimaColoana = Coordinate::stringFromColumnIndex(max(1, count($angajati) * 2 + 1));
        $titlu = $this->obtineTitlu($condica);

        $foaie->setCellValueExplicit('A1', $titlu, DataType::TYPE_STRING);
        $foaie->mergeCells('A1:' . $ultimaColoana . '1');
        $foaie->setCellValue('A3', $this->limba->translateLabel('dataExport', 'labels', 'CondicaPrezenta'));

        foreach ($angajati as $indice => $angajat) {
            $coloanaOre = Coordinate::stringFromColumnIndex($indice * 2 + 2);
            $coloanaPrezenta = Coordinate::stringFromColumnIndex($indice * 2 + 3);
            $foaie->mergeCells($coloanaOre . '3:' . $coloanaPrezenta . '3');
            $foaie->setCellValueExplicit(
                $coloanaOre . '3',
                $this->obtineAntetAngajat(
                    (string) ($angajat['numeAngajat'] ?? ''),
                    $remotePeAngajati[$indice] ?? false
                ),
                DataType::TYPE_STRING
            );
            $foaie->setCellValue($coloanaOre . '4', $this->limba->translateLabel('oreExport', 'labels', 'CondicaPrezenta'));
            $foaie->setCellValue($coloanaPrezenta . '4', $this->limba->translateLabel('prezentaExport', 'labels', 'CondicaPrezenta'));
        }

        $foaie->mergeCells('A3:A4');

        foreach ($zile as $indiceZi => $zi) {
            $rand = $indiceZi + 5;
            $data = (string) ($zi['data'] ?? '');
            $foaie->setCellValueExplicit('A' . $rand, $this->formateazaData($data), DataType::TYPE_STRING);

            foreach (($zi['celule'] ?? []) as $indiceCelula => $celula) {
                $coloanaOre = Coordinate::stringFromColumnIndex($indiceCelula * 2 + 2);
                $coloanaPrezenta = Coordinate::stringFromColumnIndex($indiceCelula * 2 + 3);
                $foaie->setCellValue($coloanaOre . $rand, (float) ($celula['orePlanificate'] ?? 0.0));
                $foaie->setCellValueExplicit(
                    $coloanaPrezenta . $rand,
                    $this->obtineStareCelula($celula),
                    DataType::TYPE_STRING
                );
            }
        }

        $ultimulRand = max(4, count($zile) + 4);
        $zonaTabel = 'A3:' . $ultimaColoana . $ultimulRand;
        $foaie->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 15],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $paleta = $this->normalizeazaCulori($culori);
        $foaie->getStyle('A3:' . $ultimaColoana . '4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF' . substr($paleta['principala'], 1)],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $foaie->getRowDimension(3)->setRowHeight(28);
        $foaie->getStyle($zonaTabel)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $foaie->getStyle('A5:' . $ultimaColoana . $ultimulRand)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $foaie->getColumnDimension('A')->setWidth(13);

        for ($coloana = 2; $coloana <= count($angajati) * 2 + 1; $coloana += 2) {
            $foaie->getColumnDimensionByColumn($coloana)->setWidth(8);
            $foaie->getColumnDimensionByColumn($coloana + 1)->setWidth(14);
        }

        $foaie->freezePane('B5');
        $foaie->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);

        ob_start();
        (new Xlsx($registru))->save('php://output');
        $continut = ob_get_clean();
        $registru->disconnectWorksheets();

        if (!is_string($continut) || $continut === '') {
            throw new RuntimeException('Nu s-a putut genera fisierul XLSX al condicii.');
        }

        return [
            'nume' => $this->obtineNumeFisier($condica, 'xlsx'),
            'continut' => $continut,
            'tip' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    /**
     * @param array<string, mixed> $condica
     * @param array<string, string> $culori
     * @return array{nume: string, continut: string, tip: string}
     */
    public function genereazaPdf(array $condica, array $culori = []): array
    {
        $optiuni = new Options();
        $optiuni->set('isRemoteEnabled', false);
        $pdf = new Dompdf($optiuni);
        $pdf->setPaper('A4', 'portrait');
        $pdf->loadHtml($this->pregatesteHtmlPdf($condica, $culori), 'UTF-8');
        $pdf->render();
        $continut = $pdf->output();

        if ($continut === '') {
            throw new RuntimeException('Nu s-a putut genera fisierul PDF al condicii.');
        }

        return [
            'nume' => $this->obtineNumeFisier($condica, 'pdf'),
            'continut' => $continut,
            'tip' => 'application/pdf',
        ];
    }

    /**
     * @internal Expus pentru verificarea structurii documentului inainte de randarea Dompdf.
     * @param array<string, mixed> $condica
     * @param array<string, string> $culori
     */
    public function pregatesteHtmlPdf(array $condica, array $culori = []): string
    {
        $angajati = is_array($condica['angajati'] ?? null) ? $condica['angajati'] : [];
        $zile = is_array($condica['zile'] ?? null) ? $condica['zile'] : [];
        $remotePeAngajati = $this->obtineRemotePeAngajati($angajati, $zile);
        $grupuri = $this->imparteAngajatiPePagini($angajati);
        $sectiuni = [];

        $paleta = $this->normalizeazaCulori($culori);
        $logo = $this->furnizorLogo->obtineDataUri();

        foreach ($grupuri ?: [[]] as $indiceGrup => $grup) {
            $indici = array_keys($grup);
            $antet = '';
            $coloane = '<col style="width:20mm"><col style="width:24mm">';

            foreach ($grup as $indiceAngajat => $angajat) {
                $antet .= '<th colspan="2">' .
                    $this->html((string) ($angajat['numeAngajat'] ?? '')) .
                    (($remotePeAngajati[$indiceAngajat] ?? false) ?
                        '<span class="detaliu-angajat">' .
                        $this->html($this->limba->translateLabel(
                            'remote',
                            'labels',
                            'CondicaPrezenta'
                        )) . '</span>' : '') .
                    '</th>';
                $coloane .= '<col style="width:9mm"><col style="width:21mm">';
            }

            $subantet = '';
            foreach ($grup as $_) {
                $subantet .= '<th>' . $this->html($this->limba->translateLabel('oreExport', 'labels', 'CondicaPrezenta')) . '</th>';
                $subantet .= '<th>' . $this->html($this->limba->translateLabel('prezentaExport', 'labels', 'CondicaPrezenta')) . '</th>';
            }

            $randuri = '';
            foreach ($zile as $zi) {
                $celule = is_array($zi['celule'] ?? null) ? $zi['celule'] : [];
                $randIntrare = '<tr><td rowspan="2">' .
                    $this->html($this->formateazaData((string) ($zi['data'] ?? ''))) .
                    '</td><td>' . $this->html($this->limba->translateLabel(
                        'oraIntrareExport',
                        'labels',
                        'CondicaPrezenta'
                    )) . '</td>';
                $randIesire = '<tr><td>' . $this->html($this->limba->translateLabel(
                    'oraIesireExport',
                    'labels',
                    'CondicaPrezenta'
                )) . '</td>';

                foreach ($indici as $indice) {
                    $celula = is_array($celule[$indice] ?? null) ? $celule[$indice] : [];
                    $stare = $this->obtineStareCelula($celula);
                    $stil = $stare !== '' ? ' style="background:' .
                        $this->obtineCuloareStare((string) ($celula['codPrezenta'] ?? ''), $paleta) .
                        '"' : '';
                    $randIntrare .= '<td>' . $this->html((string) ($celula['oraIntrare'] ?? '')) .
                        '</td><td rowspan="2"' . $stil . '>' . $this->html($stare) . '</td>';
                    $randIesire .= '<td>' . $this->html((string) ($celula['oraIesire'] ?? '')) . '</td>';
                }

                $randuri .= $randIntrare . '</tr>' . $randIesire . '</tr>';
            }

            $latimeTabel = 44 + count($grup) * 30;
            $clasa = $indiceGrup > 0 ? ' class="pagina-noua"' : '';
            $logoHtml = $logo ? '<img class="logo" src="' . $this->html($logo) . '" alt="">' : '';
            $sectiuni[] = '<section' . $clasa . '><header>' . $logoHtml .
                '<h2>' . $this->html($this->obtineTitlu($condica)) . '</h2></header>' .
                '<table style="width:' . $latimeTabel . 'mm"><colgroup>' . $coloane .
                '</colgroup><thead><tr><th rowspan="2">' .
                $this->html($this->limba->translateLabel('dataExport', 'labels', 'CondicaPrezenta')) .
                '</th><th rowspan="2">' .
                $this->html($this->limba->translateLabel('programExport', 'labels', 'CondicaPrezenta')) .
                '</th>' . $antet . '</tr><tr>' . $subantet . '</tr></thead><tbody>' .
                $randuri . '</tbody></table></section>';
        }

        return '<!doctype html><html><head><meta charset="UTF-8"><style>' .
            '@page{size:A4 portrait;margin:18px}body{font-family:DejaVu Sans,sans-serif;font-size:7px;line-height:1.1;color:' .
            $paleta['text'] . '}' .
            'section{page-break-inside:avoid}header{position:relative;min-height:10mm;margin:0 0 4px}' .
            'h2{text-align:center;font-size:11px;margin:0;padding-top:3mm;color:' .
            $paleta['accent'] . '}' .
            '.logo{position:absolute;left:0;top:0;max-width:24mm;max-height:9mm}' .
            'table{border-collapse:collapse;table-layout:fixed;margin:0 auto}' .
            'th,td{box-sizing:border-box;border:1px solid ' . $paleta['contur'] .
            ';padding:2px;text-align:center;vertical-align:middle;word-wrap:break-word;overflow:hidden}' .
            'th{background:' . $paleta['principala'] . ';color:#fff}' .
            '.detaliu-angajat{display:block;font-size:6px;font-weight:normal;margin-top:1px}' .
            'thead{display:table-header-group}tr{page-break-inside:avoid}' .
            '.pagina-noua{page-break-before:always}' .
            '</style></head><body>' . implode('', $sectiuni) . '</body></html>';
    }

    /**
     * Distribuie angajatii echilibrat intre pagini, fara a depasi limita unei pagini.
     * Fiecare element ramane un bloc complet de coloane Ora + Prezenta.
     *
     * @param array<int|string, mixed> $angajati
     * @return array<int, array<int|string, mixed>>
     */
    private function imparteAngajatiPePagini(array $angajati): array
    {
        $numarAngajati = count($angajati);

        if ($numarAngajati === 0) {
            return [[]];
        }

        $numarPagini = (int) ceil($numarAngajati / self::ANGAJATI_PE_PAGINA_PDF);
        $dimensiuneMinima = intdiv($numarAngajati, $numarPagini);
        $paginiMaiMari = $numarAngajati % $numarPagini;
        $grupuri = [];
        $pozitie = 0;

        for ($pagina = 0; $pagina < $numarPagini; $pagina++) {
            $dimensiune = $dimensiuneMinima + ($pagina < $paginiMaiMari ? 1 : 0);
            $grupuri[] = array_slice($angajati, $pozitie, $dimensiune, true);
            $pozitie += $dimensiune;
        }

        return $grupuri;
    }

    /**
     * @param array<string, string> $culori
     * @return array{principala: string, accent: string, text: string, contur: string, prezent: string, delegatie: string, medical: string, cic: string, concediu: string}
     */
    private function normalizeazaCulori(array $culori): array
    {
        $implicite = [
            'principala' => '#326567',
            'accent' => '#326567',
            'text' => '#333333',
            'contur' => '#777777',
            'prezent' => '#C6EFCE',
            'delegatie' => '#FFEB9C',
            'medical' => '#F4CCCC',
            'cic' => '#D9EAF7',
            'concediu' => '#E4DCF4',
        ];

        foreach ($implicite as $nume => $valoare) {
            $candidat = $culori[$nume] ?? null;

            if (is_string($candidat) && preg_match('/^#[0-9a-fA-F]{6}$/', $candidat)) {
                $implicite[$nume] = in_array(
                    $nume,
                    ['prezent', 'delegatie', 'medical', 'cic', 'concediu'],
                    true
                ) ? $this->deschideCuloare($candidat) : strtoupper($candidat);
            }
        }

        return $implicite;
    }

    private function deschideCuloare(string $culoare): string
    {
        $rezultat = '#';

        for ($indice = 1; $indice <= 5; $indice += 2) {
            $componenta = hexdec(substr($culoare, $indice, 2));
            $componenta = (int) round($componenta + (255 - $componenta) * 0.68);
            $rezultat .= strtoupper(str_pad(dechex($componenta), 2, '0', STR_PAD_LEFT));
        }

        return $rezultat;
    }

    /**
     * @param array{principala: string, accent: string, text: string, contur: string, prezent: string, delegatie: string, medical: string, cic: string, concediu: string} $paleta
     */
    private function obtineCuloareStare(string $cod, array $paleta): string
    {
        return match ($cod) {
            'P' => $paleta['prezent'],
            'D' => $paleta['delegatie'],
            'CM' => $paleta['medical'],
            'CIC' => $paleta['cic'],
            'CO' => $paleta['concediu'],
            default => '#FFFFFF',
        };
    }

    /** @param array<string, mixed> $celula */
    private function obtineStareCelula(array $celula): string
    {
        $cod = (string) ($celula['codPrezenta'] ?? '');

        if ($cod === '') {
            return '';
        }

        $stare = $this->limba->translateOption($cod, 'codPrezenta', 'Prezenta');

        if (($celula['esteRemote'] ?? false) === true && $cod === 'P') {
            $stare .= ' · ' . $this->limba->translateLabel(
                'remote',
                'labels',
                'CondicaPrezenta'
            );
        }

        return $stare;
    }

    /**
     * Marcajul din antet arata configuratia Remote, independent de codurile
     * zilnice. In celulele zilnice, Remote este afisat separat numai pentru P.
     *
     * @param array<int|string, mixed> $angajati
     * @param array<int|string, mixed> $zile
     * @return array<int|string, bool>
     */
    private function obtineRemotePeAngajati(array $angajati, array $zile): array
    {
        $pozitiiDupaId = [];
        $rezultat = [];

        foreach ($angajati as $indice => $angajat) {
            if (!is_array($angajat)) {
                continue;
            }

            $idAngajat = (string) ($angajat['idAngajat'] ?? '');

            if ($idAngajat !== '') {
                $pozitiiDupaId[$idAngajat] = $indice;
            }

            $rezultat[$indice] = ($angajat['esteRemote'] ?? false) === true;
        }

        foreach ($zile as $zi) {
            if (!is_array($zi) || !is_array($zi['celule'] ?? null)) {
                continue;
            }

            foreach ($zi['celule'] as $indiceCelula => $celula) {
                if (
                    !is_array($celula) ||
                    ($celula['esteRemote'] ?? false) !== true
                ) {
                    continue;
                }

                $idAngajat = (string) ($celula['idAngajat'] ?? '');
                $indiceAngajat = $pozitiiDupaId[$idAngajat] ?? $indiceCelula;

                if (array_key_exists($indiceAngajat, $rezultat)) {
                    $rezultat[$indiceAngajat] = true;
                }
            }
        }

        return $rezultat;
    }

    private function obtineAntetAngajat(string $nume, bool $esteRemote): string
    {
        if (!$esteRemote) {
            return $nume;
        }

        return $nume . "\n" .
            $this->limba->translateLabel('remote', 'labels', 'CondicaPrezenta');
    }

    /** @param array<string, mixed> $condica */
    private function obtineTitlu(array $condica): string
    {
        $luna = (string) ($condica['luna'] ?? '');
        $numarLuna = substr($luna, 5, 2);
        $lunaAfisata = $this->limba->translateLabel($numarLuna, 'luni', 'Prezenta');

        return $this->limba->translateLabel('titluExport', 'labels', 'CondicaPrezenta') . ' ' .
            $lunaAfisata . ' ' . substr($luna, 0, 4);
    }

    /** @param array<string, mixed> $condica */
    private function obtineNumeFisier(array $condica, string $extensie): string
    {
        $luna = preg_replace('/[^0-9-]/', '', (string) ($condica['luna'] ?? ''));
        $revizie = (int) ($condica['cicluLunar']['revizie'] ?? 0);

        return 'condica-prezenta_' . $luna . '_revizia-' . $revizie . '.' . $extensie;
    }

    private function formateazaData(string $data): string
    {
        $parti = explode('-', $data);

        return count($parti) === 3 ? $parti[2] . '.' . $parti[1] . '.' . $parti[0] : $data;
    }

    private function html(string $valoare): string
    {
        return htmlspecialchars($valoare, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
