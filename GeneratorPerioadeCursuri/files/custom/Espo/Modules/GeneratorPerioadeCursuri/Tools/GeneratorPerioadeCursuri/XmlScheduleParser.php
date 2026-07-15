<?php

namespace Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri;

use Espo\Core\Exceptions\BadRequest;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class XmlScheduleParser
{
    private const MAX_UPLOAD_BYTES = 20 * 1024 * 1024;
    private const MAX_COURSE_ROWS = 5000;
    private const MAX_COLUMNS = 50;

    private const TITLE_HEADERS = [
        'title',
        'nume curs',
        'course name',
    ];

    private const ROMANIAN_MONTHS = [
        'ianuarie',
        'februarie',
        'martie',
        'aprilie',
        'mai',
        'iunie',
        'iulie',
        'august',
        'septembrie',
        'octombrie',
        'noiembrie',
        'decembrie',
    ];

    private const ENGLISH_MONTHS = [
        'january',
        'february',
        'march',
        'april',
        'may',
        'june',
        'july',
        'august',
        'september',
        'october',
        'november',
        'december',
    ];

    /**
     * @return array<int, array{courseTitle: string, dateRange: string, permalink: string, sourceRow: int, sourceColumn: int}>
     */
    public function parse(string $contents, string $fileName): array
    {
        if ($contents === '') {
            throw new BadRequest('The source file is empty.');
        }

        if (strlen($contents) > self::MAX_UPLOAD_BYTES) {
            throw new BadRequest('The source file may be at most 20 MiB.', 413);
        }

        $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            return $this->parseRows($this->readCsvRows($contents));
        }

        if ($extension === 'xlsx') {
            return $this->parseRows($this->readXlsxRows($contents));
        }

        throw new BadRequest('The source file must be CSV or XLSX.');
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function readCsvRows(string $contents): array
    {
        if (!mb_check_encoding($contents, 'UTF-8')) {
            throw new BadRequest('The CSV file must use UTF-8.');
        }

        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $delimiter = $this->detectCsvDelimiter($contents);
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new BadRequest('The CSV file could not be read.');
        }

        fwrite($stream, $contents);
        rewind($stream);
        $rows = [];

        while (($row = fgetcsv($stream, 0, $delimiter)) !== false) {
            $rows[] = $row;
        }

        fclose($stream);

        return $rows;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readXlsxRows(string $contents): array
    {
        if (!str_starts_with($contents, 'PK')) {
            throw new BadRequest('The XLSX file does not have a valid structure.');
        }

        $path = tempnam(sys_get_temp_dir(), 'generator-perioade-cursuri-xml-');

        if ($path === false) {
            throw new BadRequest('The XLSX file could not be read.');
        }

        try {
            if (file_put_contents($path, $contents) === false) {
                throw new BadRequest('The XLSX file could not be read.');
            }

            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();

            return $rows;
        } catch (BadRequest $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new BadRequest('The XLSX file could not be read.');
        } finally {
            @unlink($path);
        }
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     * @return array<int, array{courseTitle: string, dateRange: string, permalink: string, sourceRow: int, sourceColumn: int}>
     */
    private function parseRows(array $rows): array
    {
        if ($rows === []) {
            throw new BadRequest('Missing required columns: Title, Permalink');
        }

        $header = array_map(fn (mixed $value): string => $this->stringify($value), $rows[0]);

        if (count($header) > self::MAX_COLUMNS) {
            throw new BadRequest('The input file may contain at most 50 columns.');
        }

        $normalized = [];

        foreach ($header as $index => $name) {
            if ($name !== '') {
                $normalized[mb_strtolower($name)] = $index;
            }
        }

        $titleIndex = $this->findTitleIndex($normalized);
        $missing = [];

        if ($titleIndex === null) {
            $missing[] = 'Title';
        }

        if (!array_key_exists('permalink', $normalized)) {
            $missing[] = 'Permalink';
        }

        if ($missing !== []) {
            throw new BadRequest('Missing required columns: ' . implode(', ', $missing));
        }

        $monthColumns = [];

        foreach ($normalized as $name => $index) {
            if ($this->isMonthHeader($name)) {
                $monthColumns[] = $index;
            }
        }

        if ($monthColumns === []) {
            throw new BadRequest(
                'No supported date columns found. Use Romanian or English month columns, or Luna columns (Luna 1-Luna 12).'
            );
        }

        $permalinkIndex = $normalized['permalink'];
        $events = [];
        $courseRows = 0;

        foreach (array_slice($rows, 1) as $offset => $row) {
            $sourceRow = $offset + 2;
            $title = $this->cell($row, $titleIndex);

            if ($title === '') {
                continue;
            }

            $courseRows++;

            if ($courseRows > self::MAX_COURSE_ROWS) {
                throw new BadRequest('The input file may contain at most 5000 courses.');
            }

            $permalink = $this->cell($row, $permalinkIndex);

            foreach ($monthColumns as $monthIndex) {
                $dateRange = $this->cell($row, $monthIndex);

                if ($dateRange === '' || mb_strtolower($dateRange) === 'nan') {
                    continue;
                }

                $events[] = [
                    'courseTitle' => $title,
                    'dateRange' => $dateRange,
                    'permalink' => $permalink,
                    'sourceRow' => $sourceRow,
                    'sourceColumn' => $monthIndex + 1,
                ];
            }
        }

        if ($events === []) {
            throw new BadRequest('No valid course data found in the input file.');
        }

        return $events;
    }

    /**
     * @param array<string, int> $normalized
     */
    private function findTitleIndex(array $normalized): ?int
    {
        foreach (self::TITLE_HEADERS as $header) {
            if (array_key_exists($header, $normalized)) {
                return $normalized[$header];
            }
        }

        return null;
    }

    private function isMonthHeader(string $header): bool
    {
        if (in_array($header, self::ROMANIAN_MONTHS, true) ||
            in_array($header, self::ENGLISH_MONTHS, true)) {
            return true;
        }

        return preg_match('/^luna (?:[1-9]|1[0-2])$/', $header) === 1;
    }

    private function detectCsvDelimiter(string $contents): string
    {
        $sample = substr($contents, 0, 8192);
        $lines = preg_split('/\r\n|\n|\r/', $sample) ?: [];
        $bestDelimiter = ',';
        $bestScore = -1;

        foreach ([',', ';', '|', "\t", '@'] as $delimiter) {
            $widths = [];

            foreach (array_slice($lines, 0, 20) as $line) {
                if ($line === '') {
                    continue;
                }

                $row = str_getcsv($line, $delimiter);
                $widths[] = count($row);
            }

            if ($widths === []) {
                continue;
            }

            $frequency = array_count_values($widths);
            $stableRows = max($frequency);
            $columnCount = (int) array_search($stableRows, $frequency, true);
            $score = $columnCount > 1 ? ($stableRows * 1000) + $columnCount : 0;

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestDelimiter = $delimiter;
            }
        }

        return $bestDelimiter;
    }

    /**
     * @param array<int, mixed> $row
     */
    private function cell(array $row, int $index): string
    {
        return array_key_exists($index, $row) ? $this->stringify($row[$index]) : '';
    }

    private function stringify(mixed $value): string
    {
        if ($value === null || (is_float($value) && is_nan($value))) {
            return '';
        }

        return trim((string) $value);
    }
}
