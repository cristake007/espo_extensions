<?php

namespace Espo\Modules\Planificari\Tools\Planificari;

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

class WordConversionService
{
    private const WORD_MIME_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    private const MAX_WORD_BYTES = 20971520;
    private const MAX_SCHEDULE_ROWS = 5000;
    private const MAX_TEXT_LENGTH = 1000;
    private const MAX_DATE_LENGTH = 50;
    private const MIN_MATCH_SCORE = 88.0;
    private const MIN_TOKEN_COVERAGE = 70.0;
    private const MIN_MATCH_GAP = 8.0;
    private const WORD_ONLY_GENERATE_SCORE = 65.0;
    private const GENERATED_ROW_OFFSET = 1000000;

    private const MONTH_NAMES = [
        1 => ['ianuarie', 'january'],
        2 => ['februarie', 'february'],
        3 => ['martie', 'march'],
        4 => ['aprilie', 'april'],
        5 => ['mai', 'may'],
        6 => ['iunie', 'june'],
        7 => ['iulie', 'july'],
        8 => ['august'],
        9 => ['septembrie', 'september'],
        10 => ['octombrie', 'october'],
        11 => ['noiembrie', 'november'],
        12 => ['decembrie', 'december'],
    ];

    public function __construct(
        private EntityManager $entityManager,
        private FileStorageManager $fileStorageManager
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function convert(string $id, ?object $input = null, string $entityType = 'PlanificariWordMatcher'): array
    {
        [$record, $wordBytes, $scheduleRows] = $this->loadConversionInput($id, $entityType);
        $matches = $input && property_exists($input, 'matches') ? $this->parseMatches($input->matches) : null;

        if ($matches === null) {
            throw new BadRequest('Verifica si atribuie toate randurile inainte de generarea documentului Word.');
        }

        [$outputBytes, $matchedCount, $skippedCount] = $this->applyMatches($wordBytes, $scheduleRows, $matches);

        /** @var Attachment $convertedAttachment */
        $convertedAttachment = $this->entityManager->getRDBRepositoryByClass(Attachment::class)->getNew();
        $convertedAttachment
            ->setName($this->createFileName((string) ($record->get('name') ?? 'planificari')))
            ->setType(self::WORD_MIME_TYPE)
            ->setRole(Attachment::ROLE_EXPORT_FILE)
            ->setContents($outputBytes);

        $this->entityManager->saveEntity($convertedAttachment);

        $convertedAt = gmdate('Y-m-d H:i:s');

        $record->set('wordConvertedFileId', $convertedAttachment->getId());
        $record->set('wordConvertedAt', $convertedAt);
        $this->entityManager->saveEntity($record);

        return [
            'success' => true,
            'message' => 'Documentul Word a fost convertit.',
            'matchedCount' => $matchedCount,
            'skippedCount' => $skippedCount,
            'record' => [
                'id' => $record->getId(),
                'wordConvertedFileId' => $convertedAttachment->getId(),
                'wordConvertedAt' => $convertedAt,
            ],
            'downloadUrl' => '?entryPoint=download&id=' . $convertedAttachment->getId(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(string $id, string $entityType = 'PlanificariWordMatcher'): array
    {
        [, $wordBytes, $scheduleRows] = $this->loadConversionInput($id, $entityType);
        $wordRows = $this->readWordRows($wordBytes);
        $scheduleContext = $this->buildScheduleContext($scheduleRows);
        $rows = [];
        $matchedCount = 0;

        foreach ($wordRows as $wordIndex => $wordRow) {
            $scoredMatches = $this->scoreMatches($wordRow['title'], $scheduleRows);
            $exactMatch = $this->exactMatchFromScores($scoredMatches);
            $selectedRowIndex = $exactMatch ? $exactMatch['rowIndex'] : null;
            $generatedOption = $selectedRowIndex === null ?
                $this->buildGeneratedOption($wordIndex, $wordRow, $scheduleContext, $scoredMatches) :
                null;

            if ($selectedRowIndex !== null) {
                $matchedCount++;
            }

            $rows[] = [
                'wordRowIndex' => $wordIndex,
                'wordTitle' => $wordRow['title'],
                'duration' => $wordRow['duration'],
                'durationLabel' => $wordRow['durationLabel'],
                'selectedRowIndex' => $selectedRowIndex,
                'status' => $selectedRowIndex !== null ? 'matched' : 'needsReview',
                'candidates' => array_map(
                    fn (array $match): array => $this->candidatePayload($match),
                    array_slice($scoredMatches, 0, 5)
                ),
                'generatedOption' => $generatedOption,
            ];
        }

        return [
            'success' => true,
            'rows' => $rows,
            'scheduleOptions' => array_map(
                fn (array $row): array => [
                    'rowIndex' => $row['rowIndex'],
                    'title' => $row['title'],
                    'dates' => $row['dates'],
                    'generated' => false,
                ],
                $scheduleRows
            ),
            'matchedCount' => $matchedCount,
            'skippedCount' => count($rows) - $matchedCount,
        ];
    }

    /**
     * @return array{0: object, 1: string, 2: array<int, array{rowIndex: int, title: string, normalizedTitle: string, dates: array<int, string>}>}
     */
    private function loadConversionInput(string $id, string $entityType): array
    {
        $record = $this->entityManager->getEntityById($entityType, $id);

        if (!$record) {
            throw new BadRequest('Inregistrarea nu a fost gasita.');
        }

        $wordTemplateId = $record->get('wordTemplateFileId');
        $scheduleFileId = $record->get('wordScheduleFileId');

        if (!is_string($wordTemplateId) || $wordTemplateId === '') {
            throw new BadRequest('Incarca documentul Word inainte de conversie.');
        }

        if (!is_string($scheduleFileId) || $scheduleFileId === '') {
            throw new BadRequest('Incarca fisierul Excel generat inainte de conversia Word.');
        }

        /** @var ?Attachment $wordAttachment */
        $wordAttachment = $this->entityManager->getEntityById(Attachment::ENTITY_TYPE, $wordTemplateId);
        /** @var ?Attachment $scheduleAttachment */
        $scheduleAttachment = $this->entityManager->getEntityById(Attachment::ENTITY_TYPE, $scheduleFileId);

        if (!$wordAttachment) {
            throw new BadRequest('Documentul Word nu a fost gasit.');
        }

        if (!$scheduleAttachment) {
            throw new BadRequest('Fisierul Excel generat nu a fost gasit.');
        }

        return [
            $record,
            $this->fileStorageManager->getContents($wordAttachment),
            $this->readScheduleRows($this->fileStorageManager->getContents($scheduleAttachment)),
        ];
    }

    /**
     * @return array<int, array{rowIndex: int, title: string, normalizedTitle: string, dates: array<int, string>}>
     */
    private function readScheduleRows(string $contents): array
    {
        if ($contents === '' || !str_starts_with($contents, 'PK')) {
            throw new BadRequest('Exportul XLSX nu are o structura valida.');
        }

        $path = tempnam(sys_get_temp_dir(), 'planificari-word-schedule-');

        if ($path === false) {
            throw new BadRequest('Exportul XLSX nu a putut fi citit.');
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
            throw new BadRequest('Exportul XLSX nu a putut fi citit.');
        } finally {
            @unlink($path);
        }

        if ($rows === []) {
            throw new BadRequest('Exportul XLSX nu contine un antet.');
        }

        $header = array_map(fn ($value): string => $this->cellText($value), $rows[0]);
        $normalized = [];

        foreach ($header as $index => $name) {
            if ($name !== '') {
                $normalized[$this->normalizeHeader($name)] = $index;
            }
        }

        $titleIndex = $normalized['nume curs'] ?? $normalized['course name'] ?? $normalized['title'] ?? null;

        if ($titleIndex === null) {
            throw new BadRequest('Exportul XLSX trebuie sa contina coloana cu numele cursului.');
        }

        $monthIndexes = $this->getMonthIndexes($normalized);

        if ($monthIndexes === []) {
            throw new BadRequest('Exportul XLSX trebuie sa contina coloane lunare.');
        }

        $scheduleRows = [];

        foreach (array_slice($rows, 1) as $offset => $row) {
            if (count($scheduleRows) >= self::MAX_SCHEDULE_ROWS) {
                throw new BadRequest('Exportul XLSX poate contine cel mult 5000 cursuri.');
            }

            $title = $this->cellText($row[$titleIndex] ?? '');

            if ($title === '') {
                continue;
            }

            if (mb_strlen($title) > self::MAX_TEXT_LENGTH) {
                throw new BadRequest('Exportul XLSX contine un titlu prea lung.');
            }

            $dates = [];

            foreach ($monthIndexes as $monthIndex) {
                $value = $this->cellText($row[$monthIndex] ?? '');

                if ($value === '' || mb_strtolower($value) === 'nan') {
                    continue;
                }

                if (mb_strlen($value) > self::MAX_DATE_LENGTH) {
                    throw new BadRequest('Exportul XLSX contine o perioada prea lunga.');
                }

                $dates[] = $value;

                if (count($dates) === 3) {
                    break;
                }
            }

            while (count($dates) < 3) {
                $dates[] = '';
            }

            $scheduleRows[] = [
                'rowIndex' => $offset,
                'title' => $title,
                'normalizedTitle' => $this->normalizeTitle($title),
                'dates' => $dates,
            ];
        }

        if ($scheduleRows === []) {
            throw new BadRequest('Exportul XLSX nu contine cursuri valide.');
        }

        return $scheduleRows;
    }

    /**
     * @param array<string, int> $normalized
     * @return int[]
     */
    private function getMonthIndexes(array $normalized): array
    {
        $indexes = [];

        foreach (self::MONTH_NAMES as $aliases) {
            foreach ($aliases as $alias) {
                if (isset($normalized[$alias])) {
                    $indexes[] = $normalized[$alias];
                    break;
                }
            }
        }

        sort($indexes);

        return $indexes;
    }

    /**
     * @param array<int, array{rowIndex: int, title: string, normalizedTitle: string, dates: array<int, string>}> $scheduleRows
     * @param ?array<int, int> $matches
     * @return array{0: string, 1: int, 2: int}
     */
    private function applyMatches(string $wordBytes, array $scheduleRows, ?array $matches = null): array
    {
        if ($wordBytes === '' || strlen($wordBytes) > self::MAX_WORD_BYTES || !str_starts_with($wordBytes, 'PK')) {
            throw new BadRequest('Documentul Word nu are o structura valida.');
        }

        $path = tempnam(sys_get_temp_dir(), 'planificari-word-docx-');

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

            $wordRows = $this->getWordRows($xpath);

            if ($wordRows === []) {
                throw new BadRequest('Documentul Word nu contine randuri de curs compatibile.');
            }

            if ($matches === null || count($matches) !== count($wordRows)) {
                throw new BadRequest('Atribuie toate randurile inainte de generarea documentului Word.');
            }

            $matchedCount = 0;
            $scheduleByIndex = [];

            foreach ($scheduleRows as $scheduleRow) {
                $scheduleByIndex[$scheduleRow['rowIndex']] = $scheduleRow;
            }

            $generatedByIndex = [];
            $scheduleContext = $this->buildScheduleContext($scheduleRows);

            foreach ($wordRows as $wordIndex => $wordRow) {
                $generatedRow = $this->buildGeneratedScheduleRow($wordIndex, $wordRow, $scheduleContext);

                if ($generatedRow) {
                    $generatedByIndex[$generatedRow['rowIndex']] = $generatedRow;
                }
            }

            foreach ($wordRows as $wordIndex => $wordRow) {
                if (!array_key_exists($wordIndex, $matches)) {
                    throw new BadRequest('Atribuie toate randurile inainte de generarea documentului Word.');
                }
            }

            foreach ($matches as $wordIndex => $scheduleIndex) {
                if ($wordIndex >= count($wordRows)) {
                    throw new BadRequest('O selectie indica un rand Word inexistent.');
                }

                if (!isset($scheduleByIndex[$scheduleIndex]) && !isset($generatedByIndex[$scheduleIndex])) {
                    throw new BadRequest('O selectie indica un rand de program inexistent.');
                }
            }

            foreach ($wordRows as $wordIndex => $wordRow) {
                $scheduleIndex = $matches[$wordIndex];
                $scheduleRow = $scheduleByIndex[$scheduleIndex] ?? $generatedByIndex[$scheduleIndex] ?? null;

                if (!$scheduleRow) {
                    throw new BadRequest('O selectie indica un rand de program inexistent.');
                }

                foreach ([3, 4, 5] as $offset => $cellIndex) {
                    $this->setCellText($document, $wordRow['cells'][$cellIndex], $scheduleRow['dates'][$offset] ?? '');
                }

                $matchedCount++;
            }

            $zip->addFromString('word/document.xml', $document->saveXML());
            $zip->close();
            $zipOpen = false;
            $output = file_get_contents($path);

            return [
                is_string($output) ? $output : '',
                $matchedCount,
                count($wordRows) - $matchedCount,
            ];
        } finally {
            if ($zipOpen) {
                $zip->close();
            }

            @unlink($path);
        }
    }

    /**
     * @return array<int, array{title: string, normalizedTitle: string, duration: ?int, durationLabel: string}>
     */
    private function readWordRows(string $wordBytes): array
    {
        if ($wordBytes === '' || strlen($wordBytes) > self::MAX_WORD_BYTES || !str_starts_with($wordBytes, 'PK')) {
            throw new BadRequest('Documentul Word nu are o structura valida.');
        }

        $path = tempnam(sys_get_temp_dir(), 'planificari-word-preview-');

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
            $wordRows = array_map(
                fn (array $row): array => [
                    'title' => $row['title'],
                    'normalizedTitle' => $row['normalizedTitle'],
                    'duration' => $row['duration'],
                    'durationLabel' => $row['durationLabel'],
                ],
                $this->getWordRows($xpath)
            );

            if ($wordRows === []) {
                throw new BadRequest('Documentul Word nu contine randuri de curs compatibile.');
            }

            return $wordRows;
        } finally {
            if ($zipOpen) {
                $zip->close();
            }

            @unlink($path);
        }
    }

    /**
     * @return array<int, array{title: string, normalizedTitle: string, duration: ?int, durationLabel: string, cells: array<int, DOMElement>}>
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

            $durationLabel = trim($this->wordCellText($xpath, $cells[1]));

            $rows[] = [
                'title' => $title,
                'normalizedTitle' => $this->normalizeTitle($title),
                'duration' => $this->parseDuration($durationLabel),
                'durationLabel' => $durationLabel,
                'cells' => $cells,
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

    private function setCellText(DOMDocument $document, DOMElement $cell, string $text): void
    {
        $tcPr = null;

        foreach ($cell->childNodes as $childNode) {
            if ($childNode instanceof DOMElement && $childNode->localName === 'tcPr') {
                $tcPr = $childNode->cloneNode(true);
                break;
            }
        }

        while ($cell->firstChild) {
            $cell->removeChild($cell->firstChild);
        }

        if ($tcPr) {
            $cell->appendChild($tcPr);
        }

        $paragraph = $document->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:p');
        $run = $document->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:r');
        $textNode = $document->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:t');
        $textNode->appendChild($document->createTextNode($text));
        $run->appendChild($textNode);
        $paragraph->appendChild($run);
        $cell->appendChild($paragraph);
    }

    /**
     * @param array<int, array{rowIndex: int, title: string, normalizedTitle: string, dates: array<int, string>}> $scheduleRows
     * @return ?array{rowIndex: int, title: string, normalizedTitle: string, dates: array<int, string>}
     */
    private function confidentMatch(string $wordTitle, array $scheduleRows): ?array
    {
        return $this->confidentMatchFromScores($wordTitle, $this->scoreMatches($wordTitle, $scheduleRows));
    }

    /**
     * @param array<int, array{rowIndex: int, title: string, normalizedTitle: string, dates: array<int, string>}> $scheduleRows
     * @return array<int, array{entry: array, score: float, wordCoverage: float, scheduleCoverage: float, exact: bool}>
     */
    private function scoreMatches(string $wordTitle, array $scheduleRows): array
    {
        $wordNormalized = $this->normalizeTitle($wordTitle);

        if ($wordNormalized === '') {
            return [];
        }

        $exactMatches = array_values(array_filter(
            $scheduleRows,
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

        foreach ($scheduleRows as $row) {
            $scheduleNormalized = $row['normalizedTitle'];
            $scored[] = [
                'entry' => $row,
                'score' => $this->combinedTitleScore($wordNormalized, $scheduleNormalized),
                'wordCoverage' => $this->tokenCoverage($wordNormalized, $scheduleNormalized),
                'scheduleCoverage' => $this->tokenCoverage($scheduleNormalized, $wordNormalized),
                'exact' => false,
            ];
        }

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $scored;
    }

    /**
     * @param array<int, array{entry: array, score: float, wordCoverage: float, scheduleCoverage: float, exact: bool}> $scored
     * @return ?array{rowIndex: int, title: string, normalizedTitle: string, dates: array<int, string>}
     */
    private function confidentMatchFromScores(string $wordTitle, array $scored): ?array
    {
        if ($scored === []) {
            return null;
        }

        if (!empty($scored[0]['exact'])) {
            return $scored[0]['entry'];
        }

        $wordNormalized = $this->normalizeTitle($wordTitle);
        $best = $scored[0] ?? null;
        $second = $scored[1] ?? null;

        if (!$best) {
            return null;
        }

        $belowThreshold = $best['score'] < self::MIN_MATCH_SCORE ||
            $best['wordCoverage'] < self::MIN_TOKEN_COVERAGE ||
            $best['scheduleCoverage'] < self::MIN_TOKEN_COVERAGE;

        if ($belowThreshold && !$this->standardCodeBreaksTie($wordNormalized, $best, $second, true)) {
            return null;
        }

        if ($second && $best['score'] - $second['score'] < self::MIN_MATCH_GAP &&
            !$this->standardCodeBreaksTie($wordNormalized, $best, $second, false)
        ) {
            return null;
        }

        return $best['entry'];
    }

    /**
     * @param array<int, array{entry: array, score: float, wordCoverage: float, scheduleCoverage: float, exact: bool}> $scored
     * @return ?array{rowIndex: int, title: string, normalizedTitle: string, dates: array<int, string>}
     */
    private function exactMatchFromScores(array $scored): ?array
    {
        if ($scored === []) {
            return null;
        }

        $best = $scored[0];

        if (!empty($best['exact'])) {
            return $best['entry'];
        }

        return null;
    }

    /**
     * @param array{entry: array, score: float, wordCoverage: float, scheduleCoverage: float, exact: bool} $match
     * @return array<string, mixed>
     */
    private function candidatePayload(array $match): array
    {
        return [
            'rowIndex' => $match['entry']['rowIndex'],
            'title' => $match['entry']['title'],
            'dates' => $match['entry']['dates'],
            'score' => round($match['score'], 1),
            'wordCoverage' => round($match['wordCoverage'], 1),
            'scheduleCoverage' => round($match['scheduleCoverage'], 1),
            'exact' => (bool) $match['exact'],
        ];
    }

    /**
     * @return array<int, int>
     */
    private function parseMatches(mixed $value): array
    {
        if (!is_array($value)) {
            throw new BadRequest('Selectiile pentru conversia Word trebuie trimise ca lista.');
        }

        if (count($value) === 0) {
            throw new BadRequest('Selecteaza cel putin un rand inainte de generarea documentului Word.');
        }

        if (count($value) > self::MAX_SCHEDULE_ROWS) {
            throw new BadRequest('Selectiile pentru conversia Word sunt prea multe.');
        }

        $matches = [];

        foreach ($value as $item) {
            if (!is_object($item) && !is_array($item)) {
                throw new BadRequest('O selectie pentru conversia Word are structura invalida.');
            }

            $wordRowIndex = is_object($item) ? ($item->wordRowIndex ?? null) : ($item['wordRowIndex'] ?? null);
            $scheduleRowIndex = is_object($item) ? ($item->scheduleRowIndex ?? null) : ($item['scheduleRowIndex'] ?? null);

            if (!is_int($wordRowIndex) || !is_int($scheduleRowIndex) || $wordRowIndex < 0 || $scheduleRowIndex < 0) {
                throw new BadRequest('O selectie pentru conversia Word contine index invalid.');
            }

            if (isset($matches[$wordRowIndex])) {
                throw new BadRequest('Selectiile pentru conversia Word contin randuri duplicate.');
            }

            $matches[$wordRowIndex] = $scheduleRowIndex;
        }

        return $matches;
    }

    private function buildGeneratedOption(int $wordIndex, array $wordRow, ?array $scheduleContext, array $scoredMatches): ?array
    {
        $bestScore = (float) ($scoredMatches[0]['score'] ?? 0.0);

        if ($bestScore >= self::MIN_MATCH_SCORE) {
            return null;
        }

        $generatedRow = $this->buildGeneratedScheduleRow($wordIndex, $wordRow, $scheduleContext);

        if (!$generatedRow) {
            return null;
        }

        $mode = $bestScore < self::WORD_ONLY_GENERATE_SCORE ? 'primary' : 'secondary';
        $duration = (int) $wordRow['duration'];
        $label = $mode === 'primary' ?
            'Genereaza perioade din durata Word' :
            'Genereaza in locul sugestiilor';

        return [
            'rowIndex' => $generatedRow['rowIndex'],
            'title' => $label . ' - ' . $duration . ($duration === 1 ? ' zi' : ' zile'),
            'dates' => $generatedRow['dates'],
            'generated' => true,
            'generationMode' => $mode,
            'duration' => $duration,
            'score' => round($bestScore, 1),
        ];
    }

    private function buildGeneratedScheduleRow(int $wordIndex, array $wordRow, ?array $scheduleContext): ?array
    {
        $duration = $wordRow['duration'] ?? null;

        if (!is_int($duration) || $duration < 1 || $duration > 366 || !$scheduleContext) {
            return null;
        }

        $dates = $this->generateDatesFromDuration(
            (string) $wordRow['normalizedTitle'],
            $wordIndex,
            $duration,
            $scheduleContext
        );

        if ($dates === [] || count(array_filter($dates)) === 0) {
            return null;
        }

        while (count($dates) < 3) {
            $dates[] = '';
        }

        return [
            'rowIndex' => self::GENERATED_ROW_OFFSET + $wordIndex,
            'title' => 'Generat din Word: ' . (string) $wordRow['title'],
            'normalizedTitle' => (string) $wordRow['normalizedTitle'],
            'dates' => array_slice($dates, 0, 3),
        ];
    }

    /**
     * @param array<int, array{rowIndex: int, title: string, normalizedTitle: string, dates: array<int, string>}> $scheduleRows
     * @return ?array{year: int, months: array<int, int>}
     */
    private function buildScheduleContext(array $scheduleRows): ?array
    {
        $periods = [];
        $yearCounts = [];

        foreach ($scheduleRows as $scheduleRow) {
            foreach ($scheduleRow['dates'] as $offset => $dateValue) {
                if (!is_string($dateValue) || trim($dateValue) === '') {
                    continue;
                }

                $dateContext = $this->dateContextFromRange($dateValue);

                if (!$dateContext) {
                    continue;
                }

                $periods[$offset] ??= $dateContext['month'];
                $yearCounts[$dateContext['year']] = ($yearCounts[$dateContext['year']] ?? 0) + 1;
            }
        }

        if ($periods === [] || $yearCounts === []) {
            return null;
        }

        arsort($yearCounts);
        ksort($periods);

        return [
            'year' => (int) array_key_first($yearCounts),
            'months' => array_values($periods),
        ];
    }

    /**
     * @return ?array{month: int, year: int}
     */
    private function dateContextFromRange(string $value): ?array
    {
        if (!preg_match_all('/\b\d{1,2}\.(\d{1,2})\.(\d{4})\b/u', $value, $matches, PREG_SET_ORDER)) {
            return null;
        }

        $last = end($matches);

        if (!$last) {
            return null;
        }

        $month = (int) $last[1];
        $year = (int) $last[2];

        if ($month < 1 || $month > 12 || $year < 2025 || $year > 2031) {
            return null;
        }

        return ['month' => $month, 'year' => $year];
    }

    /**
     * @param array{year: int, months: array<int, int>} $scheduleContext
     * @return string[]
     */
    private function generateDatesFromDuration(string $normalizedTitle, int $wordIndex, int $duration, array $scheduleContext): array
    {
        $scheduler = new CourseScheduler($scheduleContext['year'], []);
        $dates = [];

        foreach (array_slice($scheduleContext['months'], 0, 3) as $month) {
            $availableDates = $scheduler->getAvailableStartDays($month, $duration);

            if ($availableDates === []) {
                $dates[] = '';

                continue;
            }

            $seed = abs((int) crc32($normalizedTitle . ':' . $wordIndex . ':' . $month));
            $startDate = $availableDates[$seed % count($availableDates)];
            $dates[] = $scheduler->formatDateRange($startDate, $duration);
        }

        return $dates;
    }

    private function parseDuration(string $value): ?int
    {
        if (!preg_match('/\b(\d{1,3})\s*(?:zi|zile)\b/iu', $value, $matches)) {
            return null;
        }

        $duration = (int) $matches[1];

        if ($duration < 1 || $duration > 366) {
            return null;
        }

        return $duration;
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

    /**
     * @param array{entry: array, score: float, wordCoverage: float, scheduleCoverage: float} $best
     * @param ?array{entry: array, score: float, wordCoverage: float, scheduleCoverage: float} $second
     */
    private function standardCodeBreaksTie(string $wordNormalized, array $best, ?array $second, bool $thresholdFallback): bool
    {
        $wordCodes = $this->standardCodeTokens($wordNormalized);

        if ($wordCodes === []) {
            return false;
        }

        $bestCodes = $this->standardCodeTokens($best['entry']['normalizedTitle']);
        $secondCodes = $second ? $this->standardCodeTokens($second['entry']['normalizedTitle']) : [];
        $codesMatchBest = $this->isSubset($wordCodes, $bestCodes) && !$this->isSubset($wordCodes, $secondCodes);

        if (!$codesMatchBest) {
            return false;
        }

        if ($thresholdFallback) {
            return $best['score'] >= 70 && $best['wordCoverage'] >= 60;
        }

        return $best['wordCoverage'] >= 60;
    }

    /**
     * @return string[]
     */
    private function standardCodeTokens(string $value): array
    {
        preg_match_all('/\b\d{4,5}\b/', $value, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    /**
     * @param string[] $source
     * @param string[] $target
     */
    private function isSubset(array $source, array $target): bool
    {
        $targetMap = array_flip($target);

        foreach ($source as $item) {
            if (!isset($targetMap[$item])) {
                return false;
            }
        }

        return true;
    }

    private function normalizeHeader(string $value): string
    {
        return mb_strtolower(trim($value));
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

    private function createFileName(string $recordName): string
    {
        $base = trim($recordName) !== '' ? $recordName : 'planificari';
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', $base) ?: 'planificari';
        $base = trim($base, '-_.') ?: 'planificari';

        return $base . '-word-convertit.docx';
    }
}
