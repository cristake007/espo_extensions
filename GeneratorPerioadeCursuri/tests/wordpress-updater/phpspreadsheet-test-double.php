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
    public function __construct(private Worksheet $worksheet)
    {
    }

    public function getActiveSheet(): Worksheet
    {
        return $this->worksheet;
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

    public function isDateTime(): bool
    {
        return $this->dateTime;
    }
}

namespace PhpOffice\PhpSpreadsheet\Worksheet;

use PhpOffice\PhpSpreadsheet\Cell;

class Worksheet
{
    /** @param array<string, Cell> $cells */
    public function __construct(private array $cells)
    {
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
