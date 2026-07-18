<?php

declare(strict_types=1);

namespace PhpOffice\PhpSpreadsheet;

use DOMDocument;
use DOMElement;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use ZipArchive;

class IOFactory
{
    public static function createReader(string $type): Reader
    {
        if ($type !== 'Xlsx') {
            throw new RuntimeException('The test double supports only Xlsx.');
        }

        return new Reader();
    }
}

class Reader
{
    private bool $readDataOnly = false;

    public function setReadDataOnly(bool $readDataOnly): void
    {
        $this->readDataOnly = $readDataOnly;
    }

    public function load(string $path): Spreadsheet
    {
        if (!$this->readDataOnly) {
            throw new RuntimeException('The production parser must request calculated values only.');
        }

        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Invalid XLSX fixture.');
        }

        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (!is_string($xml)) {
            throw new RuntimeException('The XLSX fixture has no active worksheet.');
        }

        $document = new DOMDocument();
        $document->loadXML($xml);
        $cells = [];

        foreach ($document->getElementsByTagName('c') as $cellNode) {
            if (!$cellNode instanceof DOMElement) {
                continue;
            }

            $coordinate = $cellNode->getAttribute('r');
            $type = $cellNode->getAttribute('t');
            $style = $cellNode->getAttribute('s');

            if ($type === 'inlineStr') {
                $textNodes = $cellNode->getElementsByTagName('t');
                $value = $textNodes->length > 0 ? $textNodes->item(0)?->textContent : '';
            } else {
                $valueNodes = $cellNode->getElementsByTagName('v');
                $raw = $valueNodes->length > 0 ? $valueNodes->item(0)?->textContent : null;
                $value = $type === 'n' && is_numeric($raw) ? (float) $raw : $raw;
            }

            $cells[$coordinate] = new Cell($value, $style === '1');
        }

        return new Spreadsheet(new Worksheet($cells));
    }
}

class Spreadsheet
{
    /** @var Worksheet[] */
    private array $worksheets;

    public function __construct(?Worksheet $worksheet = null)
    {
        $this->worksheets = [$worksheet ?? new Worksheet()];
    }

    public function getActiveSheet(): Worksheet
    {
        return $this->worksheets[0];
    }

    public function createSheet(): Worksheet
    {
        $worksheet = new Worksheet();
        $this->worksheets[] = $worksheet;

        return $worksheet;
    }

    /** @return Worksheet[] */
    public function getAllSheets(): array
    {
        return $this->worksheets;
    }

    public function disconnectWorksheets(): void
    {
    }
}

class Cell
{
    public function __construct(private mixed $value, private bool $dateTime)
    {
    }

    public function getCalculatedValue(): mixed
    {
        return $this->value;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function isDateTime(): bool
    {
        return $this->dateTime;
    }
}

namespace PhpOffice\PhpSpreadsheet\Worksheet;

use PhpOffice\PhpSpreadsheet\Cell;

class Worksheet
{
    private string $title = 'Worksheet';

    /** @param array<string, Cell> $cells */
    public function __construct(private array $cells = [])
    {
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param array<int, mixed> $values
     */
    public function fromArray(array $values, mixed $nullValue = null, string $startCell = 'A1'): self
    {
        preg_match('/^([A-Z]+)(\d+)$/i', $startCell, $matches);
        $startColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($matches[1] ?? 'A');
        $startRow = (int) ($matches[2] ?? 1);

        foreach ($values as $offset => $value) {
            $coordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startColumn + $offset) . $startRow;
            $this->cells[$coordinate] = new Cell($value === $nullValue ? null : $value, false);
        }

        return $this;
    }

    public function getHighestDataColumn(): string
    {
        $highest = 1;

        foreach (array_keys($this->cells) as $coordinate) {
            preg_match('/^([A-Z]+)/', $coordinate, $matches);
            $highest = max($highest, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($matches[1]));
        }

        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($highest);
    }

    public function getHighestColumn(): string
    {
        return $this->getHighestDataColumn();
    }

    public function getHighestDataRow(): int
    {
        $highest = 1;

        foreach (array_keys($this->cells) as $coordinate) {
            preg_match('/(\d+)$/', $coordinate, $matches);
            $highest = max($highest, (int) $matches[1]);
        }

        return $highest;
    }

    public function getCell(string $coordinate): Cell
    {
        return $this->cells[$coordinate] ?? new Cell(null, false);
    }

    /** @return array<string, Cell> */
    public function getCells(): array
    {
        return $this->cells;
    }

    /** @return array<int, array<int, mixed>> */
    public function toArray(
        mixed $nullValue = null,
        bool $calculateFormulas = true,
        bool $formatData = true,
        bool $returnCellRef = false
    ): array {
        $rows = [];
        $highestColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($this->getHighestDataColumn());

        for ($row = 1; $row <= $this->getHighestDataRow(); $row++) {
            $values = [];

            for ($column = 1; $column <= $highestColumn; $column++) {
                $coordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column) . $row;
                $values[] = $this->cells[$coordinate]->getCalculatedValue() ?? $nullValue;
            }

            $rows[] = $values;
        }

        return $rows;
    }

    public function getColumnDimensionByColumn(int $column): ColumnDimension
    {
        return new ColumnDimension();
    }

    public function getColumnDimension(string $column): ColumnDimension
    {
        return new ColumnDimension();
    }
}

class ColumnDimension
{
    public function setAutoSize(bool $autoSize): self
    {
        return $this;
    }
}

namespace PhpOffice\PhpSpreadsheet\Writer;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use ZipArchive;

class Xlsx
{
    public function __construct(private Spreadsheet $spreadsheet)
    {
    }

    public function save(string $target): void
    {
        $path = tempnam(sys_get_temp_dir(), 'phpspreadsheet-test-double-');

        if ($path === false) {
            throw new RuntimeException('Unable to create the test XLSX.');
        }

        try {
            $zip = new ZipArchive();

            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to open the test XLSX.');
            }

            $sheets = $this->spreadsheet->getAllSheets();
            $zip->addFromString('[Content_Types].xml', $this->contentTypesXml(count($sheets)));
            $zip->addFromString('_rels/.rels', $this->packageRelationshipsXml());
            $zip->addFromString('xl/workbook.xml', $this->workbookXml($sheets));
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml(count($sheets)));

            foreach ($sheets as $index => $sheet) {
                $zip->addFromString('xl/worksheets/sheet' . ($index + 1) . '.xml', $this->worksheetXml($sheet));
            }

            $zip->close();
            $contents = file_get_contents($path);

            if (!is_string($contents)) {
                throw new RuntimeException('Unable to read the test XLSX.');
            }

            if ($target === 'php://output') {
                echo $contents;
            } else {
                file_put_contents($target, $contents);
            }
        } finally {
            @unlink($path);
        }
    }

    private function contentTypesXml(int $sheetCount): string
    {
        $worksheets = '';

        for ($index = 1; $index <= $sheetCount; $index++) {
            $worksheets .= '<Override PartName="/xl/worksheets/sheet' . $index . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            $worksheets . '</Types>';
    }

    private function packageRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>';
    }

    /** @param Worksheet[] $sheets */
    private function workbookXml(array $sheets): string
    {
        $sheetXml = '';

        foreach ($sheets as $index => $sheet) {
            $sheetNumber = $index + 1;
            $sheetXml .= '<sheet name="' . $this->escape($sheet->getTitle()) . '" sheetId="' . $sheetNumber . '" r:id="rId' . $sheetNumber . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets>' . $sheetXml . '</sheets></workbook>';
    }

    private function workbookRelationshipsXml(int $sheetCount): string
    {
        $relationships = '';

        for ($index = 1; $index <= $sheetCount; $index++) {
            $relationships .= '<Relationship Id="rId' . $index . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $index . '.xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            $relationships . '</Relationships>';
    }

    private function worksheetXml(Worksheet $sheet): string
    {
        $rows = [];

        foreach ($sheet->getCells() as $coordinate => $cell) {
            preg_match('/^([A-Z]+)(\d+)$/', $coordinate, $matches);
            $row = (int) ($matches[2] ?? 1);
            $rows[$row][$coordinate] = $cell->getValue();
        }

        ksort($rows);
        $rowXml = '';

        foreach ($rows as $row => $cells) {
            uksort(
                $cells,
                static fn (string $left, string $right): int =>
                    Coordinate::columnIndexFromString(preg_replace('/\d+$/', '', $left) ?? '') <=>
                    Coordinate::columnIndexFromString(preg_replace('/\d+$/', '', $right) ?? '')
            );
            $cellXml = '';

            foreach ($cells as $coordinate => $value) {
                if (is_int($value) || is_float($value)) {
                    $cellXml .= '<c r="' . $coordinate . '" t="n"><v>' . $value . '</v></c>';
                } else {
                    $cellXml .= '<c r="' . $coordinate . '" t="inlineStr"><is><t>' . $this->escape((string) $value) . '</t></is></c>';
                }
            }

            $rowXml .= '<row r="' . $row . '">' . $cellXml . '</row>';
        }

        $highestColumn = $sheet->getHighestDataColumn();
        $highestRow = $sheet->getHighestDataRow();

        return '<?xml version="1.0" encoding="UTF-8"?>' .
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<dimension ref="A1:' . $highestColumn . $highestRow . '"/>' .
            '<sheetData>' . $rowXml . '</sheetData></worksheet>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}

namespace PhpOffice\PhpSpreadsheet\Cell;

class Coordinate
{
    public static function columnIndexFromString(string $column): int
    {
        $result = 0;

        foreach (str_split(strtoupper($column)) as $character) {
            $result = ($result * 26) + ord($character) - 64;
        }

        return $result;
    }

    public static function stringFromColumnIndex(int $index): string
    {
        $result = '';

        while ($index > 0) {
            $index--;
            $result = chr(65 + ($index % 26)) . $result;
            $index = intdiv($index, 26);
        }

        return $result;
    }
}

namespace PhpOffice\PhpSpreadsheet\Shared;

use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Cell;

class Date
{
    public static function isDateTime(Cell $cell): bool
    {
        return $cell->isDateTime();
    }

    public static function excelToDateTimeObject(float $value): DateTimeImmutable
    {
        return (new DateTimeImmutable('1899-12-30'))->modify('+' . (int) $value . ' days');
    }
}
