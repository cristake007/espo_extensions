<?php

declare(strict_types=1);

namespace Espo\Modules\AttendanceManagement\Tools\Attendance;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

final class AttendanceXlsxGenerator
{
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
        $users = $overview['users'];
        $rows = $overview['rows'];
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Condica');
        $lastColumn = Coordinate::stringFromColumnIndex(max(2, count($users) * 2 + 2));
        $sheet->mergeCells('A1:' . $lastColumn . '1');
        $sheet->setCellValueExplicit(
            'A1',
            sprintf('Condica de prezenta %s %d', $monthName, $year),
            DataType::TYPE_STRING,
        );
        $sheet->setCellValue('A3', 'Data');
        $sheet->mergeCells('A3:A4');
        $sheet->setCellValue('B3', 'Program');
        $sheet->mergeCells('B3:B4');

        foreach ($users as $index => $user) {
            $timeColumn = $index * 2 + 3;
            $statusColumn = $timeColumn + 1;
            $timeColumnName = Coordinate::stringFromColumnIndex($timeColumn);
            $statusColumnName = Coordinate::stringFromColumnIndex($statusColumn);
            $sheet->mergeCells($timeColumnName . '3:' . $statusColumnName . '3');
            $sheet->setCellValueExplicit(
                $timeColumnName . '3',
                (string) $user['name'],
                DataType::TYPE_STRING,
            );
            $sheet->setCellValue($timeColumnName . '4', 'Ora');
            $sheet->setCellValue($statusColumnName . '4', 'Prezenta');
        }

        foreach ($rows as $rowIndex => $row) {
            $line = $rowIndex * 2 + 5;
            $exitLine = $line + 1;
            $sheet->mergeCells('A' . $line . ':A' . $exitLine);
            $sheet->setCellValueExplicit(
                'A' . $line,
                (new \DateTimeImmutable((string) $row['date']))->format('d.m.Y'),
                DataType::TYPE_STRING,
            );
            $sheet->setCellValue('B' . $line, 'Ora intrare');
            $sheet->setCellValue('B' . $exitLine, 'Ora iesire');

            foreach ($row['cells'] as $cellIndex => $cell) {
                $user = $users[$cellIndex] ?? [];
                $schedule = $user['schedule'] ?? [];
                $timeColumnName = Coordinate::stringFromColumnIndex($cellIndex * 2 + 3);
                $statusColumnName = Coordinate::stringFromColumnIndex($cellIndex * 2 + 4);
                $statusCoordinate = $statusColumnName . $line;
                $statusRange = $statusCoordinate . ':' . $statusColumnName . $exitLine;
                $sheet->setCellValueExplicit(
                    $timeColumnName . $line,
                    (string) ($schedule['startTime'] ?? ''),
                    DataType::TYPE_STRING,
                );
                $sheet->setCellValueExplicit(
                    $timeColumnName . $exitLine,
                    (string) ($schedule['endTime'] ?? ''),
                    DataType::TYPE_STRING,
                );
                $sheet->mergeCells($statusRange);
                $sheet->setCellValueExplicit(
                    $statusCoordinate,
                    $this->statusLabel($cell['status']),
                    DataType::TYPE_STRING,
                );
                $sheet->getStyle($statusRange)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($this->statusColor($cell['status']));
            }
        }

        $lastRow = count($rows) * 2 + 4;
        $tableRange = 'A3:' . $lastColumn . $lastRow;
        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('A3:' . $lastColumn . '4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF326567']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setARGB('FF777777');
        $sheet->getStyle('A5:' . $lastColumn . $lastRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(15);

        for ($column = 3; $column <= count($users) * 2 + 2; $column += 2) {
            $sheet->getColumnDimensionByColumn($column)->setWidth(11);
            $sheet->getColumnDimensionByColumn($column + 1)->setWidth(18);
        }

        $sheet->getRowDimension(1)->setRowHeight(25);
        $sheet->getRowDimension(3)->setRowHeight(34);
        $sheet->freezePane('C5');
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 4);
        $sheet->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.25)->setRight(0.25);

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $contents = ob_get_clean();
        $spreadsheet->disconnectWorksheets();

        if (!is_string($contents) || $contents === '') {
            throw new RuntimeException('The attendance XLSX could not be generated.');
        }

        return [
            'filename' => sprintf('Condica de prezenta_%s %d.xlsx', $monthName, $year),
            'contents' => $contents,
        ];
    }

    private function statusLabel(mixed $status): string
    {
        return match ($status) {
            'AtWork' => 'La serviciu',
            'Holiday' => 'In concediu',
            'BusinessTrip' => 'In deplasare',
            default => '',
        };
    }

    private function statusColor(mixed $status): string
    {
        return match ($status) {
            'AtWork' => 'FFC6EFCE',
            'Holiday' => 'FFBDD7EE',
            'BusinessTrip' => 'FFFFEB9C',
            default => 'FFFFFFFF',
        };
    }
}
