<?php

namespace Espo\Modules\Planificari\Tools\Planificari;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\ORM\EntityManager;
use Espo\Entities\Attachment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class XlsxExportService
{
    private const MIME_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /**
     * @param array<int, array<string, int|string|bool>> $rows
     * @param int[] $months
     */
    public function __construct(private EntityManager $entityManager) {}

    /**
     * @param array<int, array<string, int|string|bool>> $rows
     * @param int[] $months
     * @param string[] $holidays
     */
    public function createAttachment(array $rows, array $months, array $holidays, string $recordName, int $year): Attachment
    {
        $contents = $this->createContents($rows, $months, $holidays);
        $name = $this->createFileName($recordName, $year);

        /** @var Attachment $attachment */
        $attachment = $this->entityManager->getRDBRepositoryByClass(Attachment::class)->getNew();

        $attachment
            ->setName($name)
            ->setType(self::MIME_TYPE)
            ->setRole(Attachment::ROLE_EXPORT_FILE)
            ->setContents($contents);

        $this->entityManager->saveEntity($attachment);

        return $attachment;
    }

    public function getExportUrl(string $id): string
    {
        $record = $this->entityManager->getEntityById('Planificari', $id);

        if (!$record) {
            throw new BadRequest('Inregistrarea Planificari nu a fost gasita.');
        }

        $exportFileId = $record->get('exportFileId');

        if (!is_string($exportFileId) || $exportFileId === '') {
            throw new BadRequest('Exportul XLSX nu este disponibil. Genereaza planificarea inainte de export.');
        }

        return '?entryPoint=download&id=' . $exportFileId;
    }

    /**
     * @param array<int, array<string, int|string|bool>> $rows
     * @param int[] $months
     * @param string[] $holidays
     */
    private function createContents(array $rows, array $months, array $holidays): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Program');

        $monthNames = [
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

        $headers = ['Rand', 'Nume curs', 'Permalink', 'Durata Curs', 'Investitie'];

        foreach ($months as $month) {
            $headers[] = $monthNames[$month] ?? (string) $month;
        }

        $sheet->fromArray($headers, null, 'A1');

        $courseRows = $this->groupRowsByCourse($rows);
        $line = 2;
        $index = 1;

        foreach ($courseRows as $course) {
            $values = [
                $index,
                $this->safeExcelText($course['courseTitle']),
                $this->safeExcelText($course['permalink']),
                $this->safeExcelText($course['durationLabel']),
                $this->safeExcelText($course['investment']),
            ];

            foreach ($months as $month) {
                $values[] = $this->safeExcelText($course['months'][(string) $month] ?? '');
            }

            $sheet->fromArray($values, null, 'A' . $line);

            $line++;
            $index++;
        }

        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($column = 1; $column <= $highestColumnIndex; $column++) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }

        $this->addHolidaysSheet($spreadsheet, $holidays);

        $writer = new Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');
        $contents = ob_get_clean();

        $spreadsheet->disconnectWorksheets();

        return is_string($contents) ? $contents : '';
    }

    /**
     * @param array<int, array<string, int|string|bool>> $rows
     * @return array<int, array{originalOrder: int, courseTitle: string, permalink: string, durationLabel: string, investment: string, months: array<string, string>}>
     */
    private function groupRowsByCourse(array $rows): array
    {
        $map = [];

        foreach ($rows as $row) {
            $key = (string) ($row['sourceRow'] ?? $row['originalOrder'] ?? $row['courseTitle'] ?? '');

            if (!isset($map[$key])) {
                $map[$key] = [
                    'originalOrder' => (int) ($row['originalOrder'] ?? 0),
                    'courseTitle' => (string) ($row['courseTitle'] ?? ''),
                    'permalink' => (string) ($row['permalink'] ?? ''),
                    'durationLabel' => (string) ($row['durationLabel'] ?? ''),
                    'investment' => (string) ($row['investment'] ?? ''),
                    'months' => [],
                ];
            }

            $map[$key]['months'][(string) ($row['month'] ?? '')] = (string) ($row['dateRange'] ?? '');
        }

        usort(
            $map,
            fn (array $a, array $b): int => $a['originalOrder'] <=> $b['originalOrder']
        );

        return $map;
    }

    /**
     * @param string[] $holidays
     */
    private function addHolidaysSheet(Spreadsheet $spreadsheet, array $holidays): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Zile nelucratoare');
        $sheet->fromArray(['Rand', 'Data'], null, 'A1');

        $line = 2;

        foreach ($holidays as $index => $holiday) {
            $sheet->fromArray([$index + 1, $this->safeExcelText($holiday)], null, 'A' . $line);
            $line++;
        }

        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
    }

    private function safeExcelText(mixed $value): string
    {
        $text = trim((string) $value);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text) ?? $text;

        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@'], true)) {
            return "'" . $text;
        }

        return $text;
    }

    private function createFileName(string $recordName, int $year): string
    {
        $base = trim($recordName) !== '' ? $recordName : 'planificari';
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', $base) ?: 'planificari';
        $base = trim($base, '-_.') ?: 'planificari';

        return $base . '-' . $year . '.xlsx';
    }
}
