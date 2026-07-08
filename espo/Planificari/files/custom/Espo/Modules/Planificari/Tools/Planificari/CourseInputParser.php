<?php

namespace Espo\Modules\Planificari\Tools\Planificari;

use Espo\Core\Exceptions\BadRequest;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class CourseInputParser
{
    private const MAX_UPLOAD_BYTES = 20971520;
    private const MAX_COURSE_ROWS = 5000;
    private const MAX_COLUMNS = 50;
    private const REQUIRED_COLUMNS = [
        'title' => 'Title',
        'durata curs' => 'Durata Curs',
        'permalink' => 'Permalink',
    ];

    /**
     * @return array<int, array<string, int|string>>
     */
    public function parse(string $contents, string $fileName): array
    {
        if ($contents === '') {
            throw new BadRequest('Fisierul sursa este gol.');
        }

        if (strlen($contents) > self::MAX_UPLOAD_BYTES) {
            throw new BadRequest('Fisierul sursa trebuie sa aiba cel mult 20 MB.');
        }

        $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            return $this->parseRows($this->readCsvRows($contents));
        }

        if ($extension === 'xlsx') {
            return $this->parseRows($this->readXlsxRows($contents));
        }

        throw new BadRequest('Fisierul sursa trebuie sa fie CSV sau XLSX.');
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function readCsvRows(string $contents): array
    {
        if (!mb_check_encoding($contents, 'UTF-8')) {
            throw new BadRequest('Fisierul CSV trebuie sa foloseasca UTF-8.');
        }

        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $delimiter = $this->detectCsvDelimiter($contents);
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new BadRequest('Fisierul CSV nu a putut fi citit.');
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
            throw new BadRequest('Fisierul XLSX nu are o structura valida.');
        }

        $path = tempnam(sys_get_temp_dir(), 'planificari-xlsx-');

        if ($path === false) {
            throw new BadRequest('Fisierul XLSX nu a putut fi citit.');
        }

        try {
            file_put_contents($path, $contents);

            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();

            return $rows;
        } catch (Throwable $e) {
            throw new BadRequest('Fisierul XLSX nu a putut fi citit.');
        } finally {
            @unlink($path);
        }
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     * @return array<int, array<string, int|string>>
     */
    private function parseRows(array $rows): array
    {
        if ($rows === []) {
            throw new BadRequest('Fisierul nu contine un antet.');
        }

        $header = array_map(fn ($value): string => $this->stringify($value), $rows[0]);

        if (count($header) > self::MAX_COLUMNS) {
            throw new BadRequest('Fisierul poate avea cel mult 50 coloane.');
        }

        $normalized = [];

        foreach ($header as $index => $name) {
            if ($name === '') {
                continue;
            }

            $normalized[mb_strtolower($name)] = $index;
        }

        $missing = [];

        foreach (self::REQUIRED_COLUMNS as $source => $canonical) {
            if (!array_key_exists($source, $normalized)) {
                $missing[] = $canonical;
            }
        }

        if ($missing !== []) {
            throw new BadRequest('Lipsesc coloanele obligatorii: ' . implode(', ', $missing));
        }

        $investmentIndex = $normalized['investitie'] ?? null;
        $courses = [];

        foreach (array_slice($rows, 1) as $offset => $row) {
            $sourceRow = $offset + 2;

            if (count($row) > self::MAX_COLUMNS) {
                throw new BadRequest("Randul {$sourceRow} depaseste limita de coloane.");
            }

            if (!$this->rowHasValue($row)) {
                continue;
            }

            if (count($courses) >= self::MAX_COURSE_ROWS) {
                throw new BadRequest('Fisierul poate contine cel mult 5000 cursuri.');
            }

            $title = $this->cell($row, $normalized['title']);
            $durationLabel = $this->cell($row, $normalized['durata curs']);
            $permalink = $this->cell($row, $normalized['permalink']);

            if ($title === '') {
                throw new BadRequest("Randul {$sourceRow}: Title este obligatoriu.");
            }

            if ($durationLabel === '') {
                throw new BadRequest("Randul {$sourceRow}: Durata Curs este obligatorie.");
            }

            if ($permalink === '') {
                throw new BadRequest("Randul {$sourceRow}: Permalink este obligatoriu.");
            }

            if (!preg_match('/(\d+)/', $durationLabel, $matches)) {
                throw new BadRequest("Randul {$sourceRow}: Durata Curs nu contine un numar valid.");
            }

            $duration = (int) $matches[1];

            if ($duration < 1 || $duration > 366) {
                throw new BadRequest("Randul {$sourceRow}: durata trebuie sa fie intre 1 si 366 zile.");
            }

            if (!$this->isValidHttpUrl($permalink)) {
                throw new BadRequest("Randul {$sourceRow}: Permalink trebuie sa fie un URL HTTP(S) valid.");
            }

            $courses[] = [
                'sourceRow' => $sourceRow,
                'originalOrder' => count($courses),
                'title' => $title,
                'durationLabel' => $durationLabel,
                'duration' => $duration,
                'permalink' => $permalink,
                'investment' => $investmentIndex !== null ? $this->cell($row, $investmentIndex) : '',
            ];
        }

        if ($courses === []) {
            throw new BadRequest('Fisierul nu contine niciun curs valid.');
        }

        return $courses;
    }

    private function detectCsvDelimiter(string $contents): string
    {
        $firstLine = strtok(substr($contents, 0, 8192), "\r\n") ?: '';

        foreach (['@', ';', "\t", '|', ','] as $delimiter) {
            if (str_contains($firstLine, $delimiter)) {
                return $delimiter;
            }
        }

        return ',';
    }

    /**
     * @param array<int, mixed> $row
     */
    private function rowHasValue(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->stringify($value) !== '') {
                return true;
            }
        }

        return false;
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
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private function isValidHttpUrl(string $value): bool
    {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }
}
