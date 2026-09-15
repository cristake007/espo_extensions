<?php

declare(strict_types=1);

namespace Espo\Modules\AttendanceManagement\Tools\Attendance;

use DateTimeImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

final class AttendancePdfGenerator
{
    private const EMPLOYEES_PER_PAGE = 7;
    private const MONTH_NAMES = [
        1 => 'Ianuarie',
        2 => 'Februarie',
        3 => 'Martie',
        4 => 'Aprilie',
        5 => 'Mai',
        6 => 'Iunie',
        7 => 'Iulie',
        8 => 'August',
        9 => 'Septembrie',
        10 => 'Octombrie',
        11 => 'Noiembrie',
        12 => 'Decembrie',
    ];

    /**
     * @param array<string, mixed> $overview
     * @return array{filename: string, contents: string}
     */
    public function generate(array $overview): array
    {
        $month = (string) $overview['month'];
        [$year, $monthNumber] = array_map('intval', explode('-', $month));
        $monthName = self::MONTH_NAMES[$monthNumber] ?? (string) $monthNumber;
        $title = sprintf('Condica de prezenta %s %d', $monthName, $year);
        $html = $this->buildHtml($title, $overview['users'], $overview['rows']);
        $options = new Options();
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();
        $this->addPageNumbers($dompdf);
        $contents = $dompdf->output();

        if (!is_string($contents) || !str_starts_with($contents, '%PDF-')) {
            throw new RuntimeException('The attendance PDF could not be generated.');
        }

        return [
            'filename' => sprintf('Condica de prezenta_%s %d.pdf', $monthName, $year),
            'contents' => $contents,
        ];
    }

    /**
     * @param list<array<string, mixed>> $users
     * @param list<array<string, mixed>> $rows
     */
    private function buildHtml(string $title, array $users, array $rows): string
    {
        $pages = [];

        foreach (array_chunk(array_keys($users), self::EMPLOYEES_PER_PAGE) as $userIndexes) {
            $pages[] = $this->buildPage($title, $users, $rows, $userIndexes);
        }

        return '<!doctype html><html><head><meta charset="UTF-8"><style>' .
            $this->styles() . '</style></head><body>' . implode('', $pages) . '</body></html>';
    }

    /**
     * @param list<array<string, mixed>> $users
     * @param list<array<string, mixed>> $rows
     * @param list<int> $userIndexes
     */
    private function buildPage(
        string $title,
        array $users,
        array $rows,
        array $userIndexes,
    ): string {
        $columnWidth = 84.5 / max(1, count($userIndexes));
        $timeWidth = $columnWidth * 0.39;
        $statusWidth = $columnWidth * 0.61;
        $columns = '<col style="width:7%"><col style="width:8.5%">';
        $employeeHeaders = '';
        $subHeaders = '';

        foreach ($userIndexes as $userIndex) {
            $columns .= sprintf(
                '<col style="width:%.3F%%"><col style="width:%.3F%%">',
                $timeWidth,
                $statusWidth,
            );
            $employeeHeaders .= '<th colspan="2">' .
                $this->escape((string) ($users[$userIndex]['name'] ?? '')) . '</th>';
            $subHeaders .= '<th>Ora</th><th>Prezenta</th>';
        }

        $body = '';

        foreach ($rows as $row) {
            $date = (new DateTimeImmutable((string) $row['date']))->format('d.m.Y');
            $entryCells = '';
            $exitCells = '';

            foreach ($userIndexes as $userIndex) {
                $user = $users[$userIndex] ?? [];
                $schedule = $user['schedule'] ?? [];
                $cell = $row['cells'][$userIndex] ?? [];
                $status = (string) ($cell['status'] ?? '');
                $entryCells .= '<td>' . $this->escape((string) ($schedule['startTime'] ?? '')) .
                    '</td><td class="status" rowspan="2" style="background:' .
                    $this->statusColor($status) . '">' . $this->escape($this->statusLabel($status)) .
                    '</td>';
                $exitCells .= '<td>' . $this->escape((string) ($schedule['endTime'] ?? '')) . '</td>';
            }

            $body .= '<tr><td rowspan="2">' . $date . '</td><td>Ora intrare</td>' .
                $entryCells . '</tr><tr><td>Ora iesire</td>' . $exitCells . '</tr>';
        }

        return '<section class="register-page"><h1>' . $this->escape($title) .
            '</h1><table><colgroup>' . $columns . '</colgroup><thead><tr>' .
            '<th rowspan="2">Data</th><th rowspan="2">Program</th>' . $employeeHeaders .
            '</tr><tr>' . $subHeaders . '</tr></thead><tbody>' . $body .
            '</tbody></table></section>';
    }

    private function styles(): string
    {
        return <<<'CSS'
            @page { size: A4 portrait; margin: 6mm 6mm 10mm; }
            * { box-sizing: border-box; }
            body { color: #111; font-family: "DejaVu Sans", sans-serif; margin: 0; }
            .register-page { page-break-after: always; }
            .register-page:last-child { page-break-after: auto; }
            h1 { font-size: 14pt; line-height: 1; margin: 0 0 4mm; text-align: center; }
            table { border-collapse: collapse; table-layout: fixed; width: 100%; }
            th, td {
                border: 0.2mm solid #777;
                font-size: 6.5pt;
                line-height: 1.1;
                padding: 0.9mm 0.2mm;
                text-align: center;
                vertical-align: middle;
            }
            thead th {
                background: #326567;
                color: #fff;
                font-size: 7pt;
                font-weight: bold;
                height: 5.5mm;
                overflow-wrap: break-word;
            }
            tbody td { white-space: nowrap; }
        CSS;
    }

    private function addPageNumbers(Dompdf $dompdf): void
    {
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');

        if ($font === null) {
            return;
        }

        $canvas->page_text(
            $canvas->get_width() - 92,
            $canvas->get_height() - 22,
            'Pagina {PAGE_NUM} din {PAGE_COUNT}',
            $font,
            8,
            [0, 0, 0],
        );
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'AtWork' => 'La serviciu',
            'Holiday' => 'In concediu',
            'BusinessTrip' => 'In deplasare',
            default => '',
        };
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'AtWork' => '#c6efce',
            'Holiday' => '#bdd7ee',
            'BusinessTrip' => '#ffeb9c',
            default => '#ffffff',
        };
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
