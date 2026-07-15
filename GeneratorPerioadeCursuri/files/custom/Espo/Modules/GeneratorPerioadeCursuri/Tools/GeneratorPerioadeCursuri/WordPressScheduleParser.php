<?php

declare(strict_types=1);

namespace Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri;

use DateTimeInterface;
use InvalidArgumentException;
use LengthException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;
use Throwable;

class WordPressScheduleParser
{
    private const MAX_UPLOAD_BYTES = 20 * 1024 * 1024;
    private const MAX_COURSE_ROWS = 5000;
    private const MAX_COLUMNS = 50;

    private const TITLE_HEADERS = [
        'title',
        'nume curs',
        'course name',
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

    /**
     * @return array<int, array{sourceRow: int, title: string, permalink: string, slug: string, excelDates: array<int, string>}>
     */
    public function parse(string $contents, string $fileName): array
    {
        if ($contents === '') {
            throw new InvalidArgumentException('The source file is empty.');
        }

        if (strlen($contents) > self::MAX_UPLOAD_BYTES) {
            throw new LengthException('The source file may be at most 20 MiB.');
        }

        $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => $this->parseRows($this->readCsvRows($contents)),
            'xlsx' => $this->parseRows($this->readXlsxRows($contents)),
            default => throw new InvalidArgumentException('The source file must be CSV or XLSX.'),
        };
    }

    public function extractSlug(string $permalink): string
    {
        $path = parse_url(trim($permalink), PHP_URL_PATH);

        if (!is_string($path)) {
            return '';
        }

        $parts = array_values(array_filter(
            explode('/', trim($path, "/ \t\n\r\0\x0B")),
            static fn (string $part): bool => $part !== ''
        ));

        return $parts === [] ? '' : (string) end($parts);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readCsvRows(string $contents): array
    {
        if (!mb_check_encoding($contents, 'UTF-8')) {
            throw new InvalidArgumentException('The CSV file must use UTF-8.');
        }

        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $delimiter = $this->detectCsvDelimiter($contents);
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new InvalidArgumentException('The CSV file could not be read.');
        }

        try {
            fwrite($stream, $contents);
            rewind($stream);
            $rows = [];

            while (($row = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
                $rows[] = $row;
            }

            return $rows;
        } finally {
            fclose($stream);
        }
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readXlsxRows(string $contents): array
    {
        if (!str_starts_with($contents, 'PK')) {
            throw new InvalidArgumentException('The XLSX file does not have a valid structure.');
        }

        $path = tempnam(sys_get_temp_dir(), 'generator-perioade-cursuri-wp-');

        if ($path === false) {
            throw new InvalidArgumentException('The XLSX file could not be read.');
        }

        $spreadsheet = null;

        try {
            if (file_put_contents($path, $contents) === false) {
                throw new InvalidArgumentException('The XLSX file could not be read.');
            }

            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

            if ($highestColumn > self::MAX_COLUMNS) {
                throw new LengthException('The input file may contain at most 50 columns.');
            }

            $rows = [];

            for ($rowNumber = 1; $rowNumber <= $sheet->getHighestDataRow(); $rowNumber++) {
                $row = [];

                for ($columnNumber = 1; $columnNumber <= $highestColumn; $columnNumber++) {
                    $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($columnNumber) . $rowNumber);
                    $value = $cell->getCalculatedValue();

                    if (SpreadsheetDate::isDateTime($cell) && is_numeric($value)) {
                        $value = SpreadsheetDate::excelToDateTimeObject((float) $value)->format('d.m.Y');
                    } elseif ($value instanceof DateTimeInterface) {
                        $value = $value->format('d.m.Y');
                    }

                    $row[] = $value;
                }

                $rows[] = $row;
            }

            return $rows;
        } catch (InvalidArgumentException | LengthException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('The XLSX file could not be read.', 0, $exception);
        } finally {
            if ($spreadsheet !== null) {
                $spreadsheet->disconnectWorksheets();
            }

            @unlink($path);
        }
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     * @return array<int, array{sourceRow: int, title: string, permalink: string, slug: string, excelDates: array<int, string>}>
     */
    private function parseRows(array $rows): array
    {
        if ($rows === []) {
            throw new InvalidArgumentException('Missing required columns: Title, Permalink.');
        }

        $header = array_map(fn (mixed $value): string => $this->stringify($value), $rows[0]);

        if (count($header) > self::MAX_COLUMNS) {
            throw new LengthException('The input file may contain at most 50 columns.');
        }

        $columns = [];

        foreach ($header as $index => $name) {
            if ($name !== '') {
                $columns[mb_strtolower($name)] = $index;
            }
        }

        $titleIndex = $this->findTitleIndex($columns);
        $permalinkIndex = $columns['permalink'] ?? null;
        $missing = [];

        if ($titleIndex === null) {
            $missing[] = 'Title';
        }

        if ($permalinkIndex === null) {
            $missing[] = 'Permalink';
        }

        if ($missing !== []) {
            throw new InvalidArgumentException('Missing required columns: ' . implode(', ', $missing) . '.');
        }

        $result = [];

        foreach (array_slice($rows, 1) as $offset => $row) {
            $sourceRow = $offset + 2;

            if (count($row) > self::MAX_COLUMNS) {
                throw new LengthException("Source row {$sourceRow} may contain at most 50 columns.");
            }

            if (!$this->rowHasValue($row)) {
                continue;
            }

            if (count($result) >= self::MAX_COURSE_ROWS) {
                throw new LengthException('The input file may contain at most 5000 non-empty course rows.');
            }

            $title = $this->cell($row, $titleIndex);
            $permalink = $this->cell($row, $permalinkIndex);
            $dates = [];
            $seenDates = [];

            foreach (self::ENGLISH_MONTHS as $month => $englishHeader) {
                $value = $this->cellByHeader($row, $columns, $englishHeader);

                if ($value === '') {
                    $value = $this->cellByHeader($row, $columns, self::ROMANIAN_MONTHS[$month]);
                }

                if ($value === '' || mb_strtolower($value) === 'nan' || isset($seenDates[$value])) {
                    continue;
                }

                $dates[] = $value;
                $seenDates[$value] = true;
            }

            $result[] = [
                'sourceRow' => $sourceRow,
                'title' => $title !== '' ? $title : 'Curs fără titlu',
                'permalink' => $permalink,
                'slug' => $this->extractSlug($permalink),
                'excelDates' => $dates,
            ];
        }

        return $result;
    }

    /**
     * @param array<string, int> $columns
     */
    private function findTitleIndex(array $columns): ?int
    {
        foreach (self::TITLE_HEADERS as $header) {
            if (array_key_exists($header, $columns)) {
                return $columns[$header];
            }
        }

        return null;
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
                if ($line !== '') {
                    $widths[] = count(str_getcsv($line, $delimiter, '"', ''));
                }
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

    /** @param array<int, mixed> $row */
    private function rowHasValue(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->stringify($value) !== '') {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, mixed> $row */
    private function cell(array $row, int $index): string
    {
        return array_key_exists($index, $row) ? $this->stringify($row[$index]) : '';
    }

    /**
     * @param array<int, mixed> $row
     * @param array<string, int> $columns
     */
    private function cellByHeader(array $row, array $columns, string $header): string
    {
        return array_key_exists($header, $columns) ? $this->cell($row, $columns[$header]) : '';
    }

    private function stringify(mixed $value): string
    {
        if ($value === null || (is_float($value) && is_nan($value))) {
            return '';
        }

        if (is_scalar($value) || $value instanceof \Stringable) {
            return trim((string) $value);
        }

        return '';
    }
}
