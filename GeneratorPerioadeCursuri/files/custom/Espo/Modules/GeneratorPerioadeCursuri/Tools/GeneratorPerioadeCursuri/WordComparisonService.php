<?php

namespace Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Core\ORM\EntityManager;
use Espo\Entities\Attachment;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;
use ZipArchive;

class WordComparisonService
{
    private const MAX_WORD_BYTES = 20971520;
    private const MAX_SCHEDULE_ROWS = 5000;
    private const MAX_TEXT_LENGTH = 1000;
    private const MIN_MATCH_SCORE = 88.0;
    private const MIN_TOKEN_COVERAGE = 70.0;
    private const MIN_MATCH_GAP = 8.0;

    public function __construct(
        private EntityManager $entityManager,
        private FileStorageManager $fileStorageManager
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function compare(string $id, string $entityType = 'GeneratorPerioadeCursuriWordComparator'): array
    {
        $record = $this->entityManager->getEntityById($entityType, $id);

        if (!$record) {
            throw new BadRequest('Inregistrarea nu a fost gasita.');
        }

        $wordTemplateId = $record->get('wordTemplateFileId');
        $scheduleFileId = $record->get('wordScheduleFileId');

        if (!is_string($wordTemplateId) || $wordTemplateId === '') {
            throw new BadRequest('Incarca documentul Word inainte de comparare.');
        }

        if (!is_string($scheduleFileId) || $scheduleFileId === '') {
            throw new BadRequest('Incarca fisierul Excel cu cursurile de pe site inainte de comparare.');
        }

        /** @var ?Attachment $wordAttachment */
        $wordAttachment = $this->entityManager->getEntityById(Attachment::ENTITY_TYPE, $wordTemplateId);
        /** @var ?Attachment $scheduleAttachment */
        $scheduleAttachment = $this->entityManager->getEntityById(Attachment::ENTITY_TYPE, $scheduleFileId);

        if (!$wordAttachment) {
            throw new BadRequest('Documentul Word nu a fost gasit.');
        }

        if (!$scheduleAttachment) {
            throw new BadRequest('Fisierul Excel nu a fost gasit.');
        }

        $wordRows = $this->readWordRows($this->fileStorageManager->getContents($wordAttachment));
        $excelRows = $this->readExcelRows($this->fileStorageManager->getContents($scheduleAttachment));

        return $this->buildComparison($wordRows, $excelRows);
    }

    /**
     * @param array<int, array{title: string, normalizedTitle: string, duration: string, price: string}> $wordRows
     * @param array<int, array{rowIndex: int, title: string, normalizedTitle: string, duration: string, price: string}> $excelRows
     * @return array<string, mixed>
     */
    private function buildComparison(array $wordRows, array $excelRows): array
    {
        $matchedExcelIndexes = [];
        $rows = [];
        $differentCount = 0;

        foreach ($wordRows as $wordRow) {
            $scored = $this->scoreMatches($wordRow['normalizedTitle'], $excelRows);
            $match = $this->confidentMatchFromScores($wordRow['normalizedTitle'], $scored);

            if ($match === null) {
                $rows[] = [
                    'wordTitle' => $wordRow['title'],
                    'excelTitle' => null,
                    'matched' => false,
                    'title' => $this->fieldDiff($wordRow['title'], null),
                    'duration' => $this->fieldDiff($wordRow['duration'], null),
                    'price' => $this->fieldDiff($wordRow['price'], null),
                ];

                continue;
            }

            $matchedExcelIndexes[$match['rowIndex']] = true;

            $titleDiff = $this->fieldDiff($wordRow['title'], $match['title']);
            $durationDiff = $this->fieldDiff($wordRow['duration'], $match['duration']);
            $priceDiff = $this->fieldDiff($wordRow['price'], $match['price']);

            if ($titleDiff['status'] !== 'same' || $durationDiff['status'] !== 'same' || $priceDiff['status'] !== 'same') {
                $differentCount++;
            }

            $rows[] = [
                'wordTitle' => $wordRow['title'],
                'excelTitle' => $match['title'],
                'matched' => true,
                'title' => $titleDiff,
                'duration' => $durationDiff,
                'price' => $priceDiff,
            ];
        }

        $excelOnly = [];

        foreach ($excelRows as $excelRow) {
            if (isset($matchedExcelIndexes[$excelRow['rowIndex']])) {
                continue;
            }

            $excelOnly[] = [
                'title' => $excelRow['title'],
                'duration' => $excelRow['duration'],
                'price' => $excelRow['price'],
            ];
        }

        return [
            'success' => true,
            'rows' => $rows,
            'excelOnly' => $excelOnly,
            'wordCount' => count($wordRows),
            'excelCount' => count($excelRows),
            'matchedCount' => count($matchedExcelIndexes),
            'differentCount' => $differentCount,
            'wordOnlyCount' => count(array_filter($rows, fn (array $row): bool => !$row['matched'])),
            'excelOnlyCount' => count($excelOnly),
        ];
    }

    /**
     * @return array{status: string, word: ?string, excel: ?string}
     */
    private function fieldDiff(string $wordValue, ?string $excelValue): array
    {
        $word = trim($wordValue);
        $excel = $excelValue === null ? null : trim($excelValue);

        if ($excel === null) {
            $status = $word === '' ? 'missingBoth' : 'missingExcel';
        } elseif ($word === '' && $excel === '') {
            $status = 'missingBoth';
        } elseif ($word === '') {
            $status = 'missingWord';
        } elseif ($excel === '') {
            $status = 'missingExcel';
        } elseif ($this->valuesMatch($word, $excel)) {
            $status = 'same';
        } else {
            $status = 'different';
        }

        return [
            'status' => $status,
            'word' => $word,
            'excel' => $excel,
        ];
    }

    private function valuesMatch(string $a, string $b): bool
    {
        $numberA = $this->extractNumber($a);
        $numberB = $this->extractNumber($b);

        if ($numberA !== null && $numberB !== null) {
            return abs($numberA - $numberB) < 0.005;
        }

        return $this->normalizeForCompare($a) === $this->normalizeForCompare($b);
    }

    private function extractNumber(string $value): ?float
    {
        if (!preg_match('/-?\d[\d.,]*/', $value, $matches)) {
            return null;
        }

        $digits = str_replace(' ', '', $matches[0]);

        if (substr_count($digits, ',') > 0 && substr_count($digits, '.') > 0) {
            $digits = str_replace('.', '', $digits);
            $digits = str_replace(',', '.', $digits);
        } elseif (substr_count($digits, ',') === 1 && substr_count($digits, '.') === 0) {
            $digits = str_replace(',', '.', $digits);
        } else {
            $digits = str_replace(',', '', $digits);
        }

        return is_numeric($digits) ? (float) $digits : null;
    }

    private function normalizeForCompare(string $value): string
    {
        $text = mb_strtolower(trim($value));

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @return array<int, array{title: string, normalizedTitle: string, duration: string, price: string}>
     */
    private function readWordRows(string $wordBytes): array
    {
        if ($wordBytes === '' || strlen($wordBytes) > self::MAX_WORD_BYTES || !str_starts_with($wordBytes, 'PK')) {
            throw new BadRequest('Documentul Word nu are o structura valida.');
        }

        $path = tempnam(sys_get_temp_dir(), 'generator-perioade-cursuri-word-compare-');

        if ($path === false) {
            throw new BadRequest('Documentul Word nu a putut fi citit.');
        }

        file_put_contents($path, $wordBytes);

        $zip = new ZipArchive();
        $zipOpen = false;

        try {
            if ($zip->open($path) !== true) {
                throw new BadRequest('Documentul Word nu a putut fi citit.');
            }

            $zipOpen = true;
            $documentXml = $zip->getFromName('word/document.xml');

            if (!is_string($documentXml) || $documentXml === '') {
                throw new BadRequest('Documentul Word nu contine continut editabil.');
            }

            $document = new DOMDocument();
            $document->preserveWhiteSpace = false;

            if (!$document->loadXML($documentXml)) {
                throw new BadRequest('Documentul Word nu a putut fi citit.');
            }

            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

            $rows = $this->getWordRows($xpath);

            if ($rows === []) {
                throw new BadRequest('Documentul Word nu contine randuri de curs compatibile.');
            }

            return $rows;
        } finally {
            if ($zipOpen) {
                $zip->close();
            }

            @unlink($path);
        }
    }

    /**
     * @return array<int, array{title: string, normalizedTitle: string, duration: string, price: string}>
     */
    private function getWordRows(DOMXPath $xpath): array
    {
        $rows = [];
        $tableRows = $xpath->query('//w:tbl//w:tr');

        if (!$tableRows) {
            return [];
        }

        foreach ($tableRows as $tableRow) {
            if (!$tableRow instanceof DOMElement) {
                continue;
            }

            $cells = [];

            foreach ($tableRow->childNodes as $childNode) {
                if ($childNode instanceof DOMElement && $childNode->localName === 'tc') {
                    $cells[] = $childNode;
                }
            }

            if (count($cells) < 6 || $this->cellHasGridSpan($xpath, $cells[0])) {
                continue;
            }

            $title = trim($this->wordCellText($xpath, $cells[0]));

            if ($title === '' || mb_strlen($title) > self::MAX_TEXT_LENGTH) {
                continue;
            }

            $filledCells = 0;

            foreach ($cells as $cell) {
                if (trim($this->wordCellText($xpath, $cell)) !== '') {
                    $filledCells++;
                }
            }

            if ($filledCells <= 1) {
                continue;
            }

            $rows[] = [
                'title' => $title,
                'normalizedTitle' => $this->normalizeTitle($title),
                'duration' => trim($this->wordCellText($xpath, $cells[1])),
                'price' => trim($this->wordCellText($xpath, $cells[2])),
            ];
        }

        return $rows;
    }

    private function cellHasGridSpan(DOMXPath $xpath, DOMElement $cell): bool
    {
        $gridSpan = $xpath->query('./w:tcPr/w:gridSpan', $cell);

        return $gridSpan && $gridSpan->length > 0;
    }

    private function wordCellText(DOMXPath $xpath, DOMElement $cell): string
    {
        $texts = [];
        $nodes = $xpath->query('.//w:t', $cell);

        if (!$nodes) {
            return '';
        }

        foreach ($nodes as $node) {
            $texts[] = $node->textContent;
        }

        return implode('', $texts);
    }

    /**
     * @return array<int, array{rowIndex: int, title: string, normalizedTitle: string, duration: string, price: string}>
     */
    private function readExcelRows(string $contents): array
    {
        if ($contents === '' || !str_starts_with($contents, 'PK')) {
            throw new BadRequest('Fisierul Excel nu are o structura valida.');
        }

        $path = tempnam(sys_get_temp_dir(), 'generator-perioade-cursuri-word-compare-excel-');

        if ($path === false) {
            throw new BadRequest('Fisierul Excel nu a putut fi citit.');
        }

        try {
            file_put_contents($path, $contents);

            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getSheetByName('Program') ?? $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();
        } catch (Throwable $e) {
            throw new BadRequest('Fisierul Excel nu a putut fi citit.');
        } finally {
            @unlink($path);
        }

        if ($rows === []) {
            throw new BadRequest('Fisierul Excel nu contine un antet.');
        }

        $header = array_map(fn ($value): string => $this->cellText($value), $rows[0]);
        $normalized = [];

        foreach ($header as $index => $name) {
            if ($name !== '') {
                $normalized[CourseTitleHeaderResolver::normalizeHeader($name)] = $index;
            }
        }

        try {
            $titleResolver = new CourseTitleHeaderResolver($header);
        } catch (CourseTitleHeaderException $e) {
            throw $this->titleHeaderBadRequest($e);
        }

        if (!$titleResolver->hasTitleHeader()) {
            throw new BadRequest('Fisierul Excel trebuie sa contina coloana cu numele cursului.');
        }

        if (!isset($normalized['durata curs'])) {
            throw new BadRequest('Fisierul Excel trebuie sa contina coloana "Durata Curs".');
        }

        if (!isset($normalized['investitie'])) {
            throw new BadRequest('Fisierul Excel trebuie sa contina coloana "Investitie".');
        }

        $durationIndex = $normalized['durata curs'];
        $priceIndex = $normalized['investitie'];
        $excelRows = [];

        foreach (array_slice($rows, 1) as $offset => $row) {
            if (count($excelRows) >= self::MAX_SCHEDULE_ROWS) {
                throw new BadRequest('Fisierul Excel poate contine cel mult 5000 cursuri.');
            }

            try {
                $title = $titleResolver->resolveTitle($row, $offset + 2);
            } catch (CourseTitleHeaderException $e) {
                throw $this->titleHeaderBadRequest($e);
            }

            if ($title === '') {
                continue;
            }

            if (mb_strlen($title) > self::MAX_TEXT_LENGTH) {
                throw new BadRequest('Fisierul Excel contine un titlu prea lung.');
            }

            $excelRows[] = [
                'rowIndex' => $offset,
                'title' => $title,
                'normalizedTitle' => $this->normalizeTitle($title),
                'duration' => $this->cellText($row[$durationIndex] ?? ''),
                'price' => $this->cellText($row[$priceIndex] ?? ''),
            ];
        }

        if ($excelRows === []) {
            throw new BadRequest('Fisierul Excel nu contine cursuri valide.');
        }

        return $excelRows;
    }

    /**
     * @param array<int, array{rowIndex: int, title: string, normalizedTitle: string, duration: string, price: string}> $excelRows
     * @return array<int, array{entry: array, score: float, wordCoverage: float, scheduleCoverage: float, exact: bool}>
     */
    private function scoreMatches(string $wordNormalized, array $excelRows): array
    {
        if ($wordNormalized === '') {
            return [];
        }

        $exactMatches = array_values(array_filter(
            $excelRows,
            fn (array $row): bool => $row['normalizedTitle'] === $wordNormalized
        ));

        if (count($exactMatches) === 1) {
            return [[
                'entry' => $exactMatches[0],
                'score' => 100.0,
                'wordCoverage' => 100.0,
                'scheduleCoverage' => 100.0,
                'exact' => true,
            ]];
        }

        $scored = [];

        foreach ($excelRows as $row) {
            $scored[] = [
                'entry' => $row,
                'score' => $this->combinedTitleScore($wordNormalized, $row['normalizedTitle']),
                'wordCoverage' => $this->tokenCoverage($wordNormalized, $row['normalizedTitle']),
                'scheduleCoverage' => $this->tokenCoverage($row['normalizedTitle'], $wordNormalized),
                'exact' => false,
            ];
        }

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $scored;
    }

    /**
     * @param array<int, array{entry: array, score: float, wordCoverage: float, scheduleCoverage: float, exact: bool}> $scored
     * @return ?array{rowIndex: int, title: string, normalizedTitle: string, duration: string, price: string}
     */
    private function confidentMatchFromScores(string $wordNormalized, array $scored): ?array
    {
        if ($scored === []) {
            return null;
        }

        if (!empty($scored[0]['exact'])) {
            return $scored[0]['entry'];
        }

        $best = $scored[0];
        $second = $scored[1] ?? null;

        $belowThreshold = $best['score'] < self::MIN_MATCH_SCORE ||
            $best['wordCoverage'] < self::MIN_TOKEN_COVERAGE ||
            $best['scheduleCoverage'] < self::MIN_TOKEN_COVERAGE;

        if ($belowThreshold) {
            return null;
        }

        if ($second && $best['score'] - $second['score'] < self::MIN_MATCH_GAP) {
            return null;
        }

        return $best['entry'];
    }

    private function combinedTitleScore(string $wordTitle, string $scheduleTitle): float
    {
        return 0.45 * $this->similarity($wordTitle, $scheduleTitle) +
            0.25 * $this->tokenSortSimilarity($wordTitle, $scheduleTitle) +
            0.15 * $this->tokenCoverage($wordTitle, $scheduleTitle) +
            0.15 * $this->tokenCoverage($scheduleTitle, $wordTitle);
    }

    private function similarity(string $a, string $b): float
    {
        similar_text($a, $b, $percent);

        return $percent;
    }

    private function tokenSortSimilarity(string $a, string $b): float
    {
        $aTokens = explode(' ', $a);
        $bTokens = explode(' ', $b);
        sort($aTokens);
        sort($bTokens);

        return $this->similarity(implode(' ', $aTokens), implode(' ', $bTokens));
    }

    private function tokenCoverage(string $source, string $target): float
    {
        $sourceTokens = array_values(array_filter(explode(' ', $source)));
        $targetTokens = array_flip(array_values(array_filter(explode(' ', $target))));

        if ($sourceTokens === []) {
            return 0.0;
        }

        $matched = 0;

        foreach (array_unique($sourceTokens) as $token) {
            if (isset($targetTokens[$token])) {
                $matched++;
            }
        }

        return $matched / count(array_unique($sourceTokens)) * 100;
    }

    private function titleHeaderBadRequest(CourseTitleHeaderException $exception): BadRequest
    {
        if ($exception->getReason() === CourseTitleHeaderException::DUPLICATE_HEADER) {
            return new BadRequest(sprintf(
                'Fisierul Excel contine antetul duplicat "%s".',
                (string) $exception->getHeader()
            ));
        }

        return new BadRequest(sprintf(
            'Randul %d din fisierul Excel contine valori diferite pentru coloanele "title" si "nume curs".',
            (int) $exception->getSourceRow()
        ));
    }

    private function normalizeTitle(string $value): string
    {
        $text = html_entity_decode(str_replace("\xc2\xa0", ' ', $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = mb_strtolower(trim($text));
        $text = strtr($text, [
            'ă' => 'a',
            'â' => 'a',
            'î' => 'i',
            'ș' => 's',
            'ş' => 's',
            'ț' => 't',
            'ţ' => 't',
        ]);
        $text = preg_replace('/[^\p{L}\p{N}_\s]+/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function cellText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }
}
